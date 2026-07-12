<?php
/**
 * Provider-agnostic AI wrapper.
 *
 * Call ai_complete($systemPrompt, $userPrompt) and get back the model's
 * plain-text reply, regardless of which provider is configured in
 * config/config.php under 'ai'. This is the one place that talks to an
 * external AI API - the Solver and the Study Assistant chatbot both
 * build a prompt and call this function.
 */

declare(strict_types=1);

class AiException extends Exception
{
}

function ai_complete(string $systemPrompt, string $userPrompt): string
{
    $config = app_config()['ai'] ?? null;

    if (!$config || empty($config['api_key']) || $config['api_key'] === 'REPLACE_WITH_YOUR_API_KEY') {
        throw new AiException('AI is not configured yet. Add your API key to config/config.php.');
    }

    return match ($config['provider']) {
        'anthropic' => ai_call_anthropic($config, $systemPrompt, $userPrompt),
        'openai'    => ai_call_openai($config, $systemPrompt, $userPrompt),
        default     => throw new AiException("Unknown AI provider: {$config['provider']}"),
    };
}

function ai_call_openai(array $config, string $systemPrompt, string $userPrompt): string
{
    $payload = [
        'model'       => $config['model'],
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => 0.4,
        'max_tokens'  => 1200,
    ];

    $response = ai_http_post(
        'https://api.openai.com/v1/chat/completions',
        $payload,
        ['Authorization: Bearer ' . $config['api_key']]
    );

    return trim((string) ($response['choices'][0]['message']['content'] ?? ''));
}

function ai_call_anthropic(array $config, string $systemPrompt, string $userPrompt): string
{
    $payload = [
        'model'      => $config['model'],
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'max_tokens' => 1200,
    ];

    $response = ai_http_post(
        'https://api.anthropic.com/v1/messages',
        $payload,
        [
            'x-api-key: ' . $config['api_key'],
            'anthropic-version: 2023-06-01',
        ]
    );

    $text = '';
    foreach ($response['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }

    return trim($text);
}

/** @return array<mixed> decoded JSON response body */
function ai_http_post(string $url, array $payload, array $extraHeaders): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $extraHeaders),
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $body      = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('AI request failed: ' . $curlError);
        throw new AiException('Could not reach the AI service. Check your internet connection.');
    }

    $decoded = json_decode($body, true);

    if ($httpCode >= 400) {
        $message = $decoded['error']['message'] ?? "AI service returned HTTP {$httpCode}";
        error_log('AI API error (' . $httpCode . '): ' . $body);
        throw new AiException((string) $message);
    }

    return $decoded ?? [];
}

/**
 * Runs an AI Solver request for one question item, logs it to
 * ai_interactions, and returns the saved row (including the new id,
 * needed for the Save/Feedback buttons).
 */
function ai_solve_question(array $questionItem, array $courseInfo, string $mode, int $userId): array
{
    $modes = [
        'solve_short'        => 'Give a concise, correct answer in 3-5 sentences. No preamble.',
        'solve_detailed'      => 'Give a thorough, well-structured answer with full working.',
        'explain'             => 'Explain the underlying concept and reasoning step-by-step, as if teaching a student who is stuck.',
        'similar_questions'   => 'Instead of answering, write 3 similar practice questions on the same concept, with no answers.',
    ];

    $instruction = $modes[$mode] ?? $modes['solve_detailed'];

    $systemPrompt = "You are an academic tutor helping a university student revise using past exam questions. "
        . "Be accurate, clear, and educational. If the question is ambiguous or you are not fully certain, say so "
        . "rather than guessing confidently. {$instruction}";

    $userPrompt = sprintf(
        "Course: %s (%s)\nAcademic year: %s\nQuestion %s:\n%s",
        $courseInfo['title'],
        $courseInfo['course_code'],
        $questionItem['academic_year'] ?? '',
        $questionItem['question_number'],
        $questionItem['content']
    );

    $responseText = ai_complete($systemPrompt, $userPrompt);
    $config       = app_config()['ai'];

    $stmt = db()->prepare(
        'INSERT INTO ai_interactions (user_id, past_question_id, interaction_type, prompt, response, model)
         VALUES (:user_id, :pq_id, :type, :prompt, :response, :model)'
    );
    $stmt->execute([
        'user_id'  => $userId,
        'pq_id'    => $questionItem['past_question_id'],
        'type'     => $mode,
        'prompt'   => $userPrompt,
        'response' => $responseText,
        'model'    => $config['model'],
    ]);

    return [
        'id'       => (int) db()->lastInsertId(),
        'response' => $responseText,
    ];
}
