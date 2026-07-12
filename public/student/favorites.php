<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');
$pdo  = db();

$stmt = $pdo->prepare(
    "SELECT pq.*, c.course_code, c.title AS course_title
     FROM favorites f
     JOIN past_questions pq ON pq.id = f.past_question_id
     JOIN courses c ON c.id = pq.course_id
     WHERE f.user_id = :uid AND pq.status = 'approved'
     ORDER BY f.created_at DESC"
);
$stmt->execute(['uid' => $user['id']]);
$papers = $stmt->fetchAll();

$pageTitle = 'Favorites';
$activeNav = 'favorites';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Favorites</h1>
<div style="display:flex; flex-direction:column; gap:12px;">
<?php foreach ($papers as $p): ?>
  <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
    <div>
      <h3 style="margin-bottom:4px;"><?= e($p['course_code']) ?> &mdash; <?= e($p['course_title']) ?></h3>
      <p class="muted" style="margin:0;"><?= e($p['academic_year']) ?> (<?= e(ucfirst($p['semester'])) ?>)</p>
    </div>
    <div style="display:flex; gap:8px;">
      <a href="/student/solver.php?paper_id=<?= (int) $p['id'] ?>" class="btn btn-primary btn-sm">AI Solver</a>
      <a href="/download.php?id=<?= (int) $p['id'] ?>" class="btn btn-secondary btn-sm">Download</a>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$papers): ?>
  <div class="card muted">No favorites yet — save a paper from <a href="/student/repository.php">Past Questions</a>.</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
