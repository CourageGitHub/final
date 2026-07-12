<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');
$pdo  = db();

$questionItemId = (int) ($_GET['question_item_id'] ?? 0);
$paperId        = (int) ($_GET['paper_id'] ?? 0);

$selectedItem = null;
$aiResult     = null;
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'solve') {
        $itemId = (int) ($_POST['question_item_id'] ?? 0);
        $mode   = $_POST['mode'] ?? 'solve_detailed';

        $itemStmt = $pdo->prepare(
            'SELECT qi.*, pq.academic_year, pq.course_id
             FROM question_items qi JOIN past_questions pq ON pq.id = qi.past_question_id
             WHERE qi.id = :id AND pq.status = "approved"'
        );
        $itemStmt->execute(['id' => $itemId]);
        $item = $itemStmt->fetch();

        if (!$item) {
            $errors[] = 'Question not found.';
        } else {
            $courseStmt = $pdo->prepare('SELECT course_code, title FROM courses WHERE id = :id');
            $courseStmt->execute(['id' => $item['course_id']]);
            $course = $courseStmt->fetch();

            try {
                $aiResult       = ai_solve_question($item, $course, $mode, $user['id']);
                $questionItemId = $itemId;
            } catch (AiException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if ($action === 'feedback') {
        $interactionId = (int) ($_POST['interaction_id'] ?? 0);
        $rating        = $_POST['rating'] ?? '';
        if (in_array($rating, ['helpful', 'needs_improvement'], true)) {
            $pdo->prepare(
                'INSERT INTO answer_feedback (ai_interaction_id, user_id, rating) VALUES (:i, :u, :r)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating)'
            )->execute(['i' => $interactionId, 'u' => $user['id'], 'r' => $rating]);
        }
        flash('success', 'Thanks for the feedback.');
        redirect('/student/solver.php?question_item_id=' . $questionItemId);
    }

    if ($action === 'save') {
        $interactionId = (int) ($_POST['interaction_id'] ?? 0);
        $pdo->prepare('INSERT IGNORE INTO saved_answers (user_id, ai_interaction_id) VALUES (:u, :i)')
            ->execute(['u' => $user['id'], 'i' => $interactionId]);
        flash('success', 'Saved to your answers.');
        redirect('/student/solver.php?question_item_id=' . $questionItemId);
    }
}

if ($questionItemId > 0) {
    $itemStmt = $pdo->prepare(
        'SELECT qi.*, pq.academic_year, pq.course_id, pq.id AS paper_id
         FROM question_items qi JOIN past_questions pq ON pq.id = qi.past_question_id
         WHERE qi.id = :id AND pq.status = "approved"'
    );
    $itemStmt->execute(['id' => $questionItemId]);
    $selectedItem = $itemStmt->fetch();
    if ($selectedItem) {
        $paperId = (int) $selectedItem['paper_id'];
    }
}

$paperItems = [];
$paper      = null;
if ($paperId > 0) {
    $paperStmt = $pdo->prepare(
        'SELECT pq.*, c.course_code, c.title AS course_title FROM past_questions pq
         JOIN courses c ON c.id = pq.course_id WHERE pq.id = :id AND pq.status = "approved"'
    );
    $paperStmt->execute(['id' => $paperId]);
    $paper = $paperStmt->fetch();

    if ($paper) {
        $itemsStmt = $pdo->prepare('SELECT * FROM question_items WHERE past_question_id = :id ORDER BY id');
        $itemsStmt->execute(['id' => $paperId]);
        $paperItems = $itemsStmt->fetchAll();
    }
}

$pageTitle = 'AI Solver';
$activeNav = 'repository';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<p><a href="/student/repository.php">&larr; Back to Past Questions</a></p>
<h1>AI Question Solver</h1>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

<?php if ($paper): ?>
<div class="card" style="margin-bottom:24px;">
  <h2><?= e($paper['course_code']) ?> &mdash; <?= e($paper['course_title']) ?> (<?= e($paper['academic_year']) ?>)</h2>
  <p class="muted">Pick a question below to get an AI-generated answer and explanation.</p>
  <div style="display:flex; flex-wrap:wrap; gap:8px;">
    <?php foreach ($paperItems as $it): ?>
      <a href="/student/solver.php?question_item_id=<?= (int) $it['id'] ?>"
         class="btn <?= $questionItemId === (int) $it['id'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
         Q<?= e($it['question_number']) ?>
      </a>
    <?php endforeach; ?>
    <?php if (!$paperItems): ?><p class="muted">No individual questions were parsed for this paper yet.</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($selectedItem): ?>
<div class="card" style="margin-bottom:24px;">
  <h2>Question <?= e($selectedItem['question_number']) ?></h2>
  <p><?= nl2br(e($selectedItem['content'])) ?></p>

  <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="solve">
    <input type="hidden" name="question_item_id" value="<?= (int) $selectedItem['id'] ?>">
    <button type="submit" name="mode" value="solve_short" class="btn btn-secondary btn-sm">Quick answer</button>
    <button type="submit" name="mode" value="solve_detailed" class="btn btn-primary btn-sm">Detailed answer</button>
    <button type="submit" name="mode" value="explain" class="btn btn-secondary btn-sm">Explain the concept</button>
    <button type="submit" name="mode" value="similar_questions" class="btn btn-secondary btn-sm">Similar practice questions</button>
  </form>
</div>
<?php endif; ?>

<?php if ($aiResult): ?>
<div class="card">
  <h2><span class="ai-badge">AI-generated</span></h2>
  <div style="white-space: pre-wrap; line-height:1.6;"><?= e($aiResult['response']) ?></div>

  <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="interaction_id" value="<?= (int) $aiResult['id'] ?>">
      <button type="submit" class="btn btn-secondary btn-sm">Save answer</button>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="feedback">
      <input type="hidden" name="interaction_id" value="<?= (int) $aiResult['id'] ?>">
      <input type="hidden" name="rating" value="helpful">
      <button type="submit" class="btn btn-secondary btn-sm">👍 Helpful</button>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="feedback">
      <input type="hidden" name="interaction_id" value="<?= (int) $aiResult['id'] ?>">
      <input type="hidden" name="rating" value="needs_improvement">
      <button type="submit" class="btn btn-secondary btn-sm">👎 Needs improvement</button>
    </form>
  </div>
  <p class="muted" style="margin-top:12px; font-size:0.8rem;">AI-generated answers can be wrong — always verify against your course material.</p>
</div>
<?php endif; ?>

<?php if (!$paper && !$selectedItem): ?>
  <div class="card muted">Pick a paper from <a href="/student/repository.php">Past Questions</a> to start solving.</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
