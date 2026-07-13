<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$pdo  = db();

$errors         = [];
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone    = trim((string) ($_POST['phone'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $errors[] = 'Enter a valid full name.';
        }

        $departmentId = null;
        $level        = null;

        if ($user['role'] === 'student') {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $level        = trim((string) ($_POST['level'] ?? ''));

            if ($departmentId <= 0) {
                $errors[] = 'Select a department.';
            }
            if (!in_array($level, ['100', '200', '300', '400', '500'], true)) {
                $errors[] = 'Select a valid level.';
            }
        }

        if (!$errors) {
            if ($user['role'] === 'student') {
                $pdo->prepare(
                    'UPDATE users SET full_name = :name, phone = :phone, department_id = :dept, level = :level WHERE id = :id'
                )->execute([
                    'name'  => $fullName,
                    'phone' => $phone !== '' ? $phone : null,
                    'dept'  => $departmentId,
                    'level' => $level,
                    'id'    => $user['id'],
                ]);
                $_SESSION['user']['department_id'] = $departmentId;
                $_SESSION['user']['level']         = $level;
            } else {
                $pdo->prepare('UPDATE users SET full_name = :name, phone = :phone WHERE id = :id')
                    ->execute(['name' => $fullName, 'phone' => $phone !== '' ? $phone : null, 'id' => $user['id']]);
            }

            $_SESSION['user']['full_name'] = $fullName;
            audit_log($user['id'], 'profile_update');
            flash('success', 'Profile updated.');
            redirect('/profile.php');
        }
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $result = change_password($user['id'], $current, $new, $confirm);

        if ($result['success']) {
            flash('success', 'Password changed.');
            redirect('/profile.php');
        }

        $passwordErrors[] = $result['error'];
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $user['id']]);
$fresh = $stmt->fetch();

$departments = $user['role'] === 'student'
    ? $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll()
    : [];

$pageTitle = 'Profile';
$activeNav = 'profile';
require __DIR__ . '/../includes/partials/dashboard_header.php';
?>
<h1>Profile</h1>

<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:480px; margin-bottom:20px;">
  <h2 style="font-size:1.05rem;">Your details</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">

    <div class="form-group">
      <label>Full name</label>
      <input type="text" name="full_name" value="<?= e($fresh['full_name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Email (read-only)</label>
      <input type="text" value="<?= e($fresh['email']) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input type="text" name="phone" value="<?= e($fresh['phone'] ?? '') ?>">
    </div>

    <?php if ($user['role'] === 'student'): ?>
      <div class="form-group">
        <label>Department</label>
        <select name="department_id" required>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) $fresh['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Level</label>
        <select name="level" required>
          <?php foreach (['100', '200', '300', '400', '500'] as $lvl): ?>
            <option value="<?= $lvl ?>" <?= $fresh['level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?> Level</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Matric / registration number</label>
        <input type="text" value="<?= e($fresh['identifier'] ?? '') ?>" disabled>
        <p class="muted" style="font-size:0.78rem; margin-top:4px;">Contact admin to correct your registration number.</p>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary">Save changes</button>
  </form>
</div>

<div class="card" style="max-width:480px;">
  <h2 style="font-size:1.05rem;">Change password</h2>
  <?php foreach ($passwordErrors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <div class="form-group">
      <label>Current password</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>New password</label>
      <input type="password" name="new_password" minlength="8" required>
    </div>
    <div class="form-group">
      <label>Confirm new password</label>
      <input type="password" name="confirm_password" minlength="8" required>
    </div>

    <button type="submit" class="btn btn-primary">Change password</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/partials/dashboard_footer.php'; ?>
