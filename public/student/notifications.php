<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('student');
$pdo = db();
$stmt = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id IS NULL OR user_id = :uid ORDER BY created_at DESC LIMIT 50'
);
$stmt->execute(['uid' => $user['id']]);
$notifications = $stmt->fetchAll();
$pageTitle = 'Notifications';
$activeNav = 'notifications';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Notifications</h1>
<div class="card">
  <?php foreach ($notifications as $n): ?>
    <p><strong><?= e($n['title']) ?></strong><br><span class="muted"><?= e($n['message']) ?></span></p>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><p class="muted">Nothing yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
