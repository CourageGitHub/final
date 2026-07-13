<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$token  = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done   = false;
$tokenValid = $token !== '' && verify_reset_token($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    csrf_verify();
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $result = complete_password_reset($token, $password);
        if ($result['success']) {
            $done = true;
        } else {
            $errors[]   = $result['error'];
            $tokenValid = false;
        }
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/../includes/partials/header.php';
?>
<div class="auth-card">
  <?php if ($done): ?>
    <h1>Password updated</h1>
    <div class="alert alert-success">Your password has been changed.</div>
    <p class="auth-switch"><a href="/login.php">Go to login</a></p>

  <?php elseif (!$tokenValid): ?>
    <h1>Link invalid or expired</h1>
    <div class="alert alert-error">This reset link is invalid or has expired. Reset links are only valid for 30 minutes.</div>
    <p class="auth-switch"><a href="/forgot_password.php">Request a new one</a></p>

  <?php else: ?>
    <h1>Choose a new password</h1>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="form-group">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" minlength="8" required autofocus>
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Update password</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
