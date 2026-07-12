<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();

$stats = [
    'students'  => (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'],
    'questions' => (int) $pdo->query('SELECT COUNT(*) c FROM past_questions')->fetch()['c'],
    'pending'   => (int) $pdo->query("SELECT COUNT(*) c FROM past_questions WHERE status='pending'")->fetch()['c'],
    'ai_uses'   => (int) $pdo->query('SELECT COUNT(*) c FROM ai_interactions')->fetch()['c'],
];

$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Welcome, <?= e($user['full_name']) ?></h1>
<p class="muted">Here's what's happening across the repository.</p>

<div class="stat-grid">
  <div class="stat-card"><div class="value"><?= $stats['students'] ?></div><div class="label">Students</div></div>
  <div class="stat-card"><div class="value"><?= $stats['questions'] ?></div><div class="label">Past questions</div></div>
  <div class="stat-card"><div class="value"><?= $stats['pending'] ?></div><div class="label">Pending review</div></div>
  <div class="stat-card"><div class="value"><?= $stats['ai_uses'] ?></div><div class="label">AI interactions</div></div>
</div>

<div class="card">
  <h2>Quick actions</h2>
  <p>
    <a href="/admin/courses.php" class="btn btn-secondary btn-sm">Manage courses</a>
    <a href="/admin/questions.php" class="btn btn-primary btn-sm">Upload a past question</a>
  </p>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
