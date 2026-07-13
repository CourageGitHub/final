<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'student'");
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    flash('error', 'Student not found.');
    redirect('/admin/students.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fullName   = trim($_POST['full_name'] ?? '');
    $deptId     = (int) ($_POST['department_id'] ?? 0);
    $level      = trim($_POST['level'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) < 2) {
        $errors[] = 'Enter a valid full name.';
    }
    if ($deptId <= 0) {
        $errors[] = 'Select a department.';
    }
    if (!in_array($level, ['100', '200', '300', '400', '500'], true)) {
        $errors[] = 'Select a valid level.';
    }

    if (!$errors) {
        $pdo->prepare(
            'UPDATE users SET full_name = :name, department_id = :dept, level = :level, identifier = :identifier WHERE id = :id'
        )->execute([
            'name'       => $fullName,
            'dept'       => $deptId,
            'level'      => $level,
            'identifier' => $identifier !== '' ? $identifier : null,
            'id'         => $id,
        ]);
        audit_log($user['id'], 'student_edit', "Updated user #{$id}");
        flash('success', 'Student updated.');
        redirect('/admin/students.php');
    }

    // Keep the submitted values on screen if validation failed.
    $student = array_merge($student, [
        'full_name' => $fullName, 'department_id' => $deptId, 'level' => $level, 'identifier' => $identifier,
    ]);
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$pageTitle = 'Edit Student';
$activeNav = 'students';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Edit Student</h1>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:480px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">

    <div class="form-group">
      <label>Full name</label>
      <input type="text" name="full_name" value="<?= e($student['full_name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Email (read-only)</label>
      <input type="text" value="<?= e($student['email']) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Department</label>
      <select name="department_id" required>
        <option value="">Select department</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) $student['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Level</label>
      <select name="level" required>
        <option value="">Select level</option>
        <?php foreach (['100', '200', '300', '400', '500'] as $lvl): ?>
          <option value="<?= $lvl ?>" <?= $student['level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?> Level</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Matric / registration number</label>
      <input type="text" name="identifier" value="<?= e($student['identifier'] ?? '') ?>">
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">Save changes</button>
      <a href="/admin/students.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
