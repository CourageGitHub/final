<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user || $user['role'] !== 'student' || !user_is_currently_active($user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    http_response_code(419);
    echo json_encode(['error' => 'Your session expired — refresh the page and try again.']);
    exit;
}

// Simple anti-spam throttle: at most one message every 2 seconds per session.
// (A real production deployment would want a proper per-user rate limit table
// like login_attempts uses, since this endpoint calls a paid AI API.)
$now = time();
if ($now - (int) ($_SESSION['chat_last_sent'] ?? 0) < 2) {
    http_response_code(429);
    echo json_encode(['error' => 'Sending too fast — wait a moment and try again.']);
    exit;
}
$_SESSION['chat_last_sent'] = $now;

$message = trim((string) ($_POST['message'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Type a message first.']);
    exit;
}

if (mb_strlen($message) > 1000) {
    http_response_code(422);
    echo json_encode(['error' => 'Keep messages under 1000 characters.']);
    exit;
}

try {
    $reply = run_assistant_turn($message, $user);
    echo json_encode(['reply' => $reply]);
} catch (AiException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
