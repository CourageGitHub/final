<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');
$pdo  = db();

$favoritesStmt = $pdo->prepare('SELECT COUNT(*) c FROM favorites WHERE user_id = :u');
$favoritesStmt->execute(['u' => $user['id']]);

$stats = [
    'available' => (int) $pdo->query("SELECT COUNT(*) c FROM past_questions WHERE status='approved'")->fetch()['c'],
    'favorites' => (int) $favoritesStmt->fetch()['c'],
];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Welcome, <?= e($user['full_name']) ?></h1>
<p class="muted">Search past questions, get AI help solving them, and keep an eye on your exam timetable.</p>

<div class="stat-grid">
  <div class="stat-card"><div class="value"><?= $stats['available'] ?></div><div class="label">Papers available</div></div>
  <div class="stat-card"><div class="value"><?= $stats['favorites'] ?></div><div class="label">Your favorites</div></div>
</div>

<div class="card">
  <h2>Quick actions</h2>
  <p>
    <a href="/student/repository.php" class="btn btn-primary btn-sm">Browse past questions</a>
    <a href="/student/timetable.php" class="btn btn-secondary btn-sm">View timetable</a>
  </p>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
