<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');
$pdo = db();
$notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50')->fetchAll();
$pageTitle = 'Notifications';
$activeNav = 'notifications';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Notifications sent</h1>
<div class="card">
  <?php foreach ($notifications as $n): ?>
    <p><strong><?= e($n['title']) ?></strong><br><span class="muted"><?= e($n['message']) ?> &middot; <?= e($n['created_at']) ?></span></p>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><p class="muted">None yet — approving a past question or timetable entry sends one automatically.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
