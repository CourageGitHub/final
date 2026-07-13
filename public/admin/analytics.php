<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();

$totals = [
    'students'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn(),
    'papers'     => (int) $pdo->query("SELECT COUNT(*) FROM past_questions WHERE status = 'approved'")->fetchColumn(),
    'pending'    => (int) $pdo->query("SELECT COUNT(*) FROM past_questions WHERE status = 'pending'")->fetchColumn(),
    'downloads'  => (int) $pdo->query('SELECT COALESCE(SUM(download_count), 0) FROM past_questions')->fetchColumn(),
    'ai_uses'    => (int) $pdo->query('SELECT COUNT(*) FROM ai_interactions')->fetchColumn(),
];

$mostDownloaded = $pdo->query(
    "SELECT pq.title, c.course_code, pq.download_count
     FROM past_questions pq JOIN courses c ON c.id = pq.course_id
     WHERE pq.status = 'approved' AND pq.download_count > 0
     ORDER BY pq.download_count DESC LIMIT 5"
)->fetchAll();

$mostActiveCourses = $pdo->query(
    "SELECT c.course_code, c.title, COUNT(pq.id) AS paper_count
     FROM courses c LEFT JOIN past_questions pq ON pq.course_id = c.id AND pq.status = 'approved'
     GROUP BY c.id ORDER BY paper_count DESC LIMIT 5"
)->fetchAll();

$aiByType = $pdo->query(
    'SELECT interaction_type, COUNT(*) AS total FROM ai_interactions GROUP BY interaction_type ORDER BY total DESC'
)->fetchAll();

$feedback = $pdo->query(
    "SELECT rating, COUNT(*) AS total FROM answer_feedback GROUP BY rating"
)->fetchAll();
$helpfulCount = 0;
$needsWorkCount = 0;
foreach ($feedback as $f) {
    if ($f['rating'] === 'helpful') $helpfulCount = (int) $f['total'];
    if ($f['rating'] === 'needs_improvement') $needsWorkCount = (int) $f['total'];
}
$feedbackTotal = $helpfulCount + $needsWorkCount;

$topSearches = $pdo->query(
    "SELECT query_text, COUNT(*) AS total FROM search_logs
     WHERE query_text IS NOT NULL AND query_text != ''
     GROUP BY query_text ORDER BY total DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Analytics';
$activeNav = 'analytics';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Analytics <span class="ai-badge">Live data</span></h1>

<div class="stat-grid">
  <div class="stat-card"><div class="value"><?= $totals['students'] ?></div><div class="label">Active students</div></div>
  <div class="stat-card"><div class="value"><?= $totals['papers'] ?></div><div class="label">Approved papers</div></div>
  <div class="stat-card"><div class="value"><?= $totals['pending'] ?></div><div class="label">Awaiting review</div></div>
  <div class="stat-card"><div class="value"><?= $totals['downloads'] ?></div><div class="label">Total downloads</div></div>
  <div class="stat-card"><div class="value"><?= $totals['ai_uses'] ?></div><div class="label">AI interactions</div></div>
</div>

<?php if ($totals['pending'] > 0): ?>
  <div class="alert alert-error">
    <?= $totals['pending'] ?> paper(s) waiting for review — <a href="/admin/questions.php">go review them</a>.
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-top:8px;">

  <div class="card">
    <h2 style="font-size:1.1rem;">Most downloaded papers</h2>
    <?php if ($mostDownloaded): ?>
      <table style="width:100%; border-collapse: collapse;">
        <?php foreach ($mostDownloaded as $p): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:6px 4px;"><?= e($p['course_code']) ?> — <?= e($p['title'] ?: 'Untitled') ?></td>
            <td style="padding:6px 4px; text-align:right; font-weight:600;"><?= (int) $p['download_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="muted">No downloads yet.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Courses with the most papers</h2>
    <?php if ($mostActiveCourses): ?>
      <table style="width:100%; border-collapse: collapse;">
        <?php foreach ($mostActiveCourses as $c): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:6px 4px;"><?= e($c['course_code']) ?> — <?= e($c['title']) ?></td>
            <td style="padding:6px 4px; text-align:right; font-weight:600;"><?= (int) $c['paper_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="muted">No courses yet.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">AI usage by type</h2>
    <?php if ($aiByType): ?>
      <table style="width:100%; border-collapse: collapse;">
        <?php foreach ($aiByType as $row): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:6px 4px;"><?= e(ucwords(str_replace('_', ' ', $row['interaction_type']))) ?></td>
            <td style="padding:6px 4px; text-align:right; font-weight:600;"><?= (int) $row['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="muted">No AI usage logged yet — try the Solver or Study Assistant as a student.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">AI answer feedback</h2>
    <?php if ($feedbackTotal > 0): ?>
      <p><strong><?= round($helpfulCount / $feedbackTotal * 100) ?>%</strong> marked answers helpful
         (<?= $helpfulCount ?> helpful / <?= $needsWorkCount ?> needs improvement)</p>
    <?php else: ?>
      <p class="muted">No feedback submitted yet.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Top search terms</h2>
    <?php if ($topSearches): ?>
      <table style="width:100%; border-collapse: collapse;">
        <?php foreach ($topSearches as $s): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:6px 4px;">"<?= e($s['query_text']) ?>"</td>
            <td style="padding:6px 4px; text-align:right; font-weight:600;"><?= (int) $s['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="muted">No searches logged yet.</p>
    <?php endif; ?>
  </div>

</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
