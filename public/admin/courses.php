<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute(['id' => $id]);
        audit_log($user['id'], 'course_delete', "Deleted course #{$id}");
        flash('success', 'Course deleted.');
        redirect('/admin/courses.php');
    }

    if ($action === 'create') {
        $code   = strtoupper(trim($_POST['course_code'] ?? ''));
        $title  = trim($_POST['title'] ?? '');
        $deptId = (int) ($_POST['department_id'] ?? 0);
        $level  = trim($_POST['level'] ?? '');
        $sem    = $_POST['semester'] ?? '';
        $units  = (int) ($_POST['credit_units'] ?? 3);
        $lect   = trim($_POST['lecturer_name'] ?? '');

        if ($code === '') $errors[] = 'Enter a course code.';
        if ($title === '') $errors[] = 'Enter a course title.';
        if ($deptId <= 0) $errors[] = 'Select a department.';
        if (!in_array($level, ['100', '200', '300', '400', '500'], true)) $errors[] = 'Select a valid level.';
        if (!in_array($sem, ['first', 'second'], true)) $errors[] = 'Select a valid semester.';

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO courses (course_code, title, department_id, level, semester, credit_units, lecturer_name)
                     VALUES (:code, :title, :dept, :level, :sem, :units, :lect)'
                );
                $stmt->execute([
                    'code'  => $code,
                    'title' => $title,
                    'dept'  => $deptId,
                    'level' => $level,
                    'sem'   => $sem,
                    'units' => $units,
                    'lect'  => $lect !== '' ? $lect : null,
                ]);
                audit_log($user['id'], 'course_create', "Created course {$code}");
                flash('success', 'Course added.');
                redirect('/admin/courses.php');
            } catch (PDOException $e) {
                $errors[] = 'That course code already exists in this department.';
            }
        }
    }
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$courses = $pdo->query(
    'SELECT c.*, d.name AS department_name FROM courses c
     JOIN departments d ON d.id = c.department_id
     ORDER BY c.course_code'
)->fetchAll();

$pageTitle = 'Courses';
$activeNav = 'courses';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Courses</h1>
<p class="muted">Add courses first — they're needed before you can upload past questions or build the timetable.</p>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
  <h2>Add a course</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group"><label>Course code</label><input type="text" name="course_code" placeholder="CSC 301" required></div>
    <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
    <div class="form-group"><label>Department</label>
      <select name="department_id" required>
        <option value="">Select</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Level</label>
      <select name="level" required>
        <option value="">Select</option>
        <?php foreach (['100', '200', '300', '400', '500'] as $l): ?>
          <option value="<?= $l ?>"><?= $l ?> Level</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Semester</label>
      <select name="semester" required>
        <option value="first">First</option>
        <option value="second">Second</option>
      </select>
    </div>
    <div class="form-group"><label>Credit units</label><input type="text" name="credit_units" value="3"></div>
    <div class="form-group"><label>Lecturer (optional, just a name — not a login)</label><input type="text" name="lecturer_name"></div>
    <button type="submit" class="btn btn-primary">Add course</button>
  </form>
</div>

<div class="card">
  <h2>Existing courses</h2>
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:1px solid var(--border);">
        <th style="padding:8px;">Code</th>
        <th style="padding:8px;">Title</th>
        <th style="padding:8px;">Dept</th>
        <th style="padding:8px;">Level</th>
        <th style="padding:8px;">Semester</th>
        <th style="padding:8px;">Lecturer</th>
        <th style="padding:8px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px;"><?= e($c['course_code']) ?></td>
          <td style="padding:8px;"><?= e($c['title']) ?></td>
          <td style="padding:8px;"><?= e($c['department_name']) ?></td>
          <td style="padding:8px;"><?= e($c['level']) ?></td>
          <td style="padding:8px;"><?= e(ucfirst($c['semester'])) ?></td>
          <td style="padding:8px;"><?= e($c['lecturer_name'] ?? '') ?: '—' ?></td>
          <td style="padding:8px;">
            <form method="post" onsubmit="return confirm('Delete this course? Any past questions under it go too.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$courses): ?>
        <tr><td colspan="7" style="padding:8px;" class="muted">No courses yet — add one above.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
