<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');
$pdo  = db();

$q            = trim($_GET['q'] ?? '');
$departmentId = (int) ($_GET['department_id'] ?? 0);
$level        = $_GET['level'] ?? '';
$semester     = $_GET['semester'] ?? '';
$year         = trim($_GET['academic_year'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'toggle_favorite') {
        $pqId = (int) ($_POST['id'] ?? 0);
        $existing = $pdo->prepare('SELECT id FROM favorites WHERE user_id = :u AND past_question_id = :p');
        $existing->execute(['u' => $user['id'], 'p' => $pqId]);
        if ($row = $existing->fetch()) {
            $pdo->prepare('DELETE FROM favorites WHERE id = :id')->execute(['id' => $row['id']]);
        } else {
            $pdo->prepare('INSERT INTO favorites (user_id, past_question_id) VALUES (:u, :p)')
                ->execute(['u' => $user['id'], 'p' => $pqId]);
        }
    }
    $qs = $_POST['redirect_qs'] ?? '';
    redirect('/student/repository.php' . ($qs !== '' ? '?' . $qs : ''));
}

$sql = "SELECT pq.*, c.course_code, c.title AS course_title, d.name AS department_name,
               EXISTS(SELECT 1 FROM favorites f WHERE f.user_id = :uid AND f.past_question_id = pq.id) AS is_favorite
        FROM past_questions pq
        JOIN courses c ON c.id = pq.course_id
        JOIN departments d ON d.id = c.department_id
        WHERE pq.status = 'approved'";
$params = ['uid' => $user['id']];

if ($q !== '') {
    $sql .= ' AND (MATCH(pq.title, pq.extracted_text) AGAINST (:q IN NATURAL LANGUAGE MODE) OR c.course_code LIKE :qlike OR c.title LIKE :qlike)';
    $params['q']     = $q;
    $params['qlike'] = "%{$q}%";
}
if ($departmentId > 0) {
    $sql .= ' AND c.department_id = :dept';
    $params['dept'] = $departmentId;
}
if (in_array($level, ['100', '200', '300', '400', '500'], true)) {
    $sql .= ' AND c.level = :level';
    $params['level'] = $level;
}
if (in_array($semester, ['first', 'second'], true)) {
    $sql .= ' AND pq.semester = :semester';
    $params['semester'] = $semester;
}
if ($year !== '') {
    $sql .= ' AND pq.academic_year = :year';
    $params['year'] = $year;
}
$sql .= ' ORDER BY pq.created_at DESC LIMIT 60';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$papers = $stmt->fetchAll();

if ($q !== '') {
    $pdo->prepare('INSERT INTO search_logs (user_id, query_text) VALUES (:u, :q)')
        ->execute(['u' => $user['id'], 'q' => $q]);
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$queryString = http_build_query([
    'q' => $q, 'department_id' => $departmentId ?: '', 'level' => $level,
    'semester' => $semester, 'academic_year' => $year,
]);

$pageTitle = 'Past Questions';
$activeNav = 'repository';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Past Questions</h1>

<div class="card" style="margin-bottom:24px;">
  <form method="get">
    <div class="form-group">
      <label>Search</label>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Course code, title, or keyword...">
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <div class="form-group" style="flex:1; min-width:150px;">
        <label>Department</label>
        <select name="department_id">
          <option value="">Any</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= $departmentId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:1; min-width:120px;">
        <label>Level</label>
        <select name="level">
          <option value="">Any</option>
          <?php foreach (['100', '200', '300', '400', '500'] as $l): ?>
            <option value="<?= $l ?>" <?= $level === $l ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:1; min-width:120px;">
        <label>Semester</label>
        <select name="semester">
          <option value="">Any</option>
          <option value="first" <?= $semester === 'first' ? 'selected' : '' ?>>First</option>
          <option value="second" <?= $semester === 'second' ? 'selected' : '' ?>>Second</option>
        </select>
      </div>
      <div class="form-group" style="flex:1; min-width:140px;">
        <label>Academic year</label>
        <input type="text" name="academic_year" value="<?= e($year) ?>" placeholder="2023/2024">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
</div>

<div style="display:flex; flex-direction:column; gap:12px;">
<?php foreach ($papers as $p): ?>
  <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
    <div>
      <h3 style="margin-bottom:4px;"><?= e($p['course_code']) ?> &mdash; <?= e($p['course_title']) ?></h3>
      <p class="muted" style="margin:0;">
        <?= e($p['department_name']) ?> &middot; <?= e($p['academic_year']) ?> (<?= e(ucfirst($p['semester'])) ?>) &middot; <?= e(ucfirst($p['exam_type'])) ?>
      </p>
    </div>
    <div style="display:flex; gap:8px;">
      <a href="/student/solver.php?paper_id=<?= (int) $p['id'] ?>" class="btn btn-primary btn-sm">AI Solver</a>
      <a href="/download.php?id=<?= (int) $p['id'] ?>" class="btn btn-secondary btn-sm">Download</a>
      <form method="post" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_favorite">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="redirect_qs" value="<?= e($queryString) ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><?= $p['is_favorite'] ? '★ Saved' : '☆ Save' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$papers): ?>
  <div class="card muted">No past questions match yet — try a different search, or check back once admin has approved some.</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
