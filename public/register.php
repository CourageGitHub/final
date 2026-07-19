<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Basic anti-spam: block rapid repeat submissions (bots filling the form
    // in a loop). Real users only submit this form once every few seconds anyway.
    if (time() - (int) ($_SESSION['register_last_attempt'] ?? 0) < 3) {
        $errors[] = 'Please wait a moment before trying again.';
        set_old($_POST);
    } else {
        $_SESSION['register_last_attempt'] = time();
        $result = register_student($_POST);

        if ($result['success']) {
            flash('success', 'Account created. You can log in now.');
            clear_old();
            redirect('/login.php');
        }

        $errors = $result['errors'];
        set_old($_POST);
    }
}

$departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$pageTitle = 'Register';
require __DIR__ . '/../includes/partials/header.php';
?>
<div class="auth-card">
  <h1>Create your student account</h1>
  <p class="muted">Browse past questions and view your timetable.</p>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" value="<?= old('full_name') ?>" required>
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Enter your email address" value="<?= old('email') ?>" required>
    </div>

    <div class="form-group">
      <label for="identifier">Matric / registration number (optional)</label>
      <input type="text" id="identifier" name="identifier" placeholder="0324080404" value="<?= old('identifier') ?>">
    </div>

    <div class="form-group">
      <label for="department_id">Department</label>
      <select id="department_id" name="department_id" required>
        <option value="">Select department</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= (int) $dept['id'] ?>"><?= e($dept['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="level">Level</label>
      <select id="level" name="level" required>
        <option value="">Select level</option>
        <?php foreach (['100', '200', '300', '400', '500'] as $lvl): ?>
          <option value="<?= $lvl ?>"><?= $lvl ?> Level</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" minlength="8" required>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Create account</button>
  </form>

  <p class="auth-switch">Already have an account? <a href="/login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
