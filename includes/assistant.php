<?php
/**
 * AI Study Assistant - a lightweight chatbot layered on ai_complete().
 *
 * Conversation history lives in the PHP session (not a new DB table) so
 * multi-turn context works with zero migration; every turn is still
 * logged to ai_interactions (type='chat') so it shows up in analytics
 * alongside Solver usage.
 */

declare(strict_types=1);

const CHAT_HISTORY_LIMIT = 12; // messages kept as context; oldest trimmed first

function get_chat_history(): array
{
    return $_SESSION['chat_history'] ?? [];
}

function clear_chat_history(): void
{
    $_SESSION['chat_history'] = [];
}

function append_chat_message(string $role, string $content): void
{
    $_SESSION['chat_history'][] = ['role' => $role, 'content' => $content];
    if (count($_SESSION['chat_history']) > CHAT_HISTORY_LIMIT) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -CHAT_HISTORY_LIMIT);
    }
}

/** Builds a compact, factual briefing so the AI can answer from real data instead of guessing. */
function build_assistant_context(array $user): string
{
    $pdo   = db();
    $lines = [];

    $lines[] = "Student name: {$user['full_name']}";
    $lines[] = 'Level: ' . ($user['level'] ?? 'unknown');

    if ($user['department_id']) {
        $deptStmt = $pdo->prepare('SELECT name FROM departments WHERE id = :id');
        $deptStmt->execute(['id' => $user['department_id']]);
        $lines[] = 'Department: ' . ($deptStmt->fetchColumn() ?: 'unknown');

        $coursesStmt = $pdo->prepare(
            'SELECT c.course_code, c.title,
                    (SELECT COUNT(*) FROM past_questions pq WHERE pq.course_id = c.id AND pq.status = "approved") AS paper_count
             FROM courses c WHERE c.department_id = :dept AND c.level = :level ORDER BY c.course_code'
        );
        $coursesStmt->execute(['dept' => $user['department_id'], 'level' => $user['level']]);
        $courses = $coursesStmt->fetchAll();

        if ($courses) {
            $lines[] = 'Courses at this level, with how many past papers are in the repository:';
            foreach ($courses as $c) {
                $lines[] = "- {$c['course_code']} ({$c['title']}): {$c['paper_count']} paper(s)";
            }
        }

        $examStmt = $pdo->prepare(
            'SELECT c.course_code, e.exam_date, e.start_time
             FROM exam_timetable_entries e JOIN courses c ON c.id = e.course_id
             WHERE c.department_id = :dept AND c.level = :level AND e.exam_date >= CURDATE()
             ORDER BY e.exam_date, e.start_time LIMIT 5'
        );
        $examStmt->execute(['dept' => $user['department_id'], 'level' => $user['level']]);
        $exams = $examStmt->fetchAll();

        if ($exams) {
            $lines[] = 'Upcoming exams (real, published dates):';
            foreach ($exams as $ex) {
                $lines[] = "- {$ex['course_code']} on {$ex['exam_date']} at {$ex['start_time']}";
            }
        } else {
            $lines[] = 'No exam dates have been published yet.';
        }
    }

    return implode("\n", $lines);
}

/**
 * Best-effort lookup of past questions relevant to a chat message - this is
 * what lets the assistant actually solve repository content instead of just
 * pointing the student at the Solver page. Matches on:
 *   1. a course code mentioned in the message ("CSC301", "CSC 301")
 *   2. a question number mentioned ("question 3", "Q2")
 *   3. falling back to a topic/keyword full-text search of the message
 *      against question content, if neither of the above was found
 */
function find_relevant_question_items(string $message, int $limit = 3): array
{
    $pdo = db();

    $courseId = null;
    if (preg_match('/([A-Za-z]{2,6})\s?-?\s?(\d{2,4})/', $message, $m)) {
        $normalized = strtoupper($m[1] . $m[2]);
        $stmt = $pdo->prepare(
            "SELECT id FROM courses WHERE REPLACE(UPPER(course_code), ' ', '') = :code LIMIT 1"
        );
        $stmt->execute(['code' => $normalized]);
        $found = $stmt->fetchColumn();
        $courseId = $found !== false ? (int) $found : null;
    }

    $questionNumber = null;
    if (preg_match('/\b(?:question|q)\.?\s*#?\s*(\d{1,2}[a-z]?)\b/i', $message, $m)) {
        $questionNumber = $m[1];
    }

    $sql = "SELECT qi.id AS question_item_id, qi.question_number, qi.content, pq.id AS past_question_id,
                   pq.academic_year, pq.semester, c.course_code, c.title AS course_title
            FROM question_items qi
            JOIN past_questions pq ON pq.id = qi.past_question_id
            JOIN courses c ON c.id = pq.course_id
            WHERE pq.status = 'approved'";
    $params = [];

    if ($courseId !== null) {
        $sql .= ' AND pq.course_id = :course_id';
        $params['course_id'] = $courseId;
    }
    if ($questionNumber !== null) {
        $sql .= ' AND qi.question_number = :qnum';
        $params['qnum'] = $questionNumber;
    }
    if ($courseId === null && $questionNumber === null) {
        // No structured reference found - fall back to a topic search
        // against the wording of the message itself.
        $sql .= ' AND MATCH(qi.content) AGAINST (:kw IN NATURAL LANGUAGE MODE)';
        $params['kw'] = $message;
    }

    $sql .= ' ORDER BY pq.created_at DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}


/** Runs one turn: appends the user message, calls the AI with context + history, logs, returns the reply. */
function run_assistant_turn(string $userMessage, array $user): string
{
    append_chat_message('user', $userMessage);

    $matches = find_relevant_question_items($userMessage);

    $matchesText = '';
    if ($matches) {
        $matchesText = "\n\nPOSSIBLY RELEVANT PAST QUESTIONS FOUND IN THE REPOSITORY:\n";
        foreach ($matches as $i => $match) {
            $n = $i + 1;
            $matchesText .= "[{$n}] {$match['course_code']} ({$match['academic_year']}, "
                . ucfirst($match['semester']) . " semester), Question {$match['question_number']}:\n"
                . mb_substr($match['content'], 0, 800) . "\n\n";
        }
    }

    $systemPrompt = 'You are a friendly academic study assistant for a university past-question '
        . "repository and exam timetable system. Two things you're good at:\n"
        . "1. Answering practical questions using STUDENT CONTEXT below (\"when is my next exam\", "
        . "\"what should I study for X\") - answer these from the real data given, and say honestly "
        . "when you don't have enough data rather than guessing.\n"
        . "2. Actually SOLVING past exam questions: if the student pastes a question directly, solve "
        . "it with a full, clear, correct, well-explained answer - don't just point them elsewhere. "
        . "If POSSIBLY RELEVANT PAST QUESTIONS is populated below, check whether one of them is what "
        . "the student is asking about; if so, answer THAT one and say clearly which paper/question "
        . "number you used (e.g. \"From CSC301, 2023/2024, Question 3:\"). If several could match, "
        . "briefly ask which one they mean before solving. If nothing relevant was found and they "
        . "haven't pasted a full question, ask them to paste it or name the course and question number.\n"
        . "Always make clear when you're not fully certain of an answer.\n\nSTUDENT CONTEXT:\n"
        . build_assistant_context($user) . $matchesText;

    $conversationText = '';
    foreach (get_chat_history() as $msg) {
        $speaker = $msg['role'] === 'user' ? 'Student' : 'Assistant';
        $conversationText .= "{$speaker}: {$msg['content']}\n";
    }
    $conversationText .= 'Assistant:';

    $reply = ai_complete($systemPrompt, $conversationText);

    append_chat_message('assistant', $reply);

    // If every match found belongs to the same paper, log that paper against
    // this interaction so it's attributed correctly in analytics/history.
    $matchedPaperIds = array_unique(array_column($matches, 'past_question_id'));
    $matchedPaperId  = count($matchedPaperIds) === 1 ? (int) $matchedPaperIds[0] : null;

    db()->prepare(
        'INSERT INTO ai_interactions (user_id, past_question_id, interaction_type, prompt, response, model)
         VALUES (:uid, :pq_id, "chat", :prompt, :response, :model)'
    )->execute([
        'uid'      => $user['id'],
        'pq_id'    => $matchedPaperId,
        'prompt'   => $userMessage,
        'response' => $reply,
        'model'    => app_config()['ai']['model'] ?? null,
    ]);

    return $reply;
}
