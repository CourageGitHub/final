<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id   = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM past_questions WHERE id = :id');
$stmt->execute(['id' => $id]);
$paper = $stmt->fetch();

if (!$paper) {
    http_response_code(404);
    die('Not found.');
}

if ($paper['status'] !== 'approved' && $user['role'] !== 'admin') {
    http_response_code(403);
    die('This paper is not available yet.');
}

$absolutePath = rtrim(app_config()['upload']['path'], '/') . '/' . $paper['file_path'];

if (!is_file($absolutePath)) {
    http_response_code(404);
    die('File missing on server.');
}

db()->prepare('UPDATE past_questions SET download_count = download_count + 1 WHERE id = :id')->execute(['id' => $id]);
audit_log($user['id'], 'question_download', "Downloaded paper #{$id}");

$safeFilename = preg_replace('/[\r\n"]/', '', $paper['original_filename']);

header('Content-Type: ' . $paper['mime_type']);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
