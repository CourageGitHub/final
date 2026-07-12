<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = attempt_login($email, $password);

    if ($user) {
        redirect('/index.php');
    }

    $error = 'Incorrect email or password, or too many failed attempts — try again in a few minutes.';
    set_old(['email' => $email]);
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/partials/header.php';
?>
<div class="auth-card">
  <h1>Log in</h1>

  <?php if ($message = flash('success')): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>

  <?php if ($message = flash('error')): ?>
    <div class="alert alert-error"><?= e($message) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Log in</button>
  </form>

  <p class="auth-switch">New here? <a href="/register.php">Create a student account</a></p>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
