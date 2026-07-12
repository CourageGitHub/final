<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');
$pdo  = db();

$entries = [];
if ($user['department_id'] && $user['level']) {
    $stmt = $pdo->prepare(
        'SELECT e.*, c.course_code, c.title AS course_title, r.name AS room_name
         FROM exam_timetable_entries e
         JOIN courses c ON c.id = e.course_id
         JOIN rooms r ON r.id = e.room_id
         WHERE c.department_id = :dept AND c.level = :level
         ORDER BY e.exam_date, e.start_time'
    );
    $stmt->execute(['dept' => $user['department_id'], 'level' => $user['level']]);
    $entries = $stmt->fetchAll();
}

$pageTitle = 'Timetable';
$activeNav = 'timetable';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Examination Timetable</h1>
<p class="muted">Showing exams for your department and level.</p>

<div class="card">
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:1px solid var(--border);">
        <th style="padding:8px;">Course</th><th style="padding:8px;">Date</th>
        <th style="padding:8px;">Time</th><th style="padding:8px;">Venue</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px;"><?= e($e['course_code']) ?> — <?= e($e['course_title']) ?></td>
          <td style="padding:8px;"><?= e(date('D, d M Y', strtotime($e['exam_date']))) ?></td>
          <td style="padding:8px;"><?= e(date('g:ia', strtotime($e['start_time']))) ?>–<?= e(date('g:ia', strtotime($e['end_time']))) ?></td>
          <td style="padding:8px;"><?= e($e['room_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?>
        <tr><td colspan="4" class="muted" style="padding:8px;">No exam dates published yet — check back once admin publishes the timetable.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
