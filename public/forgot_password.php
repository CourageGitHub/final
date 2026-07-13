<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $token = request_password_reset($email);

        if ($token !== null) {
            $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $token;

            // Best-effort - most local dev setups (XAMPP) have no SMTP configured,
            // so this will usually silently fail. That's fine for development.
            @mail($email, 'Password reset', "Reset your password here: {$link}");

            if (!empty(app_config()['debug'])) {
                $resetLink = $link; // only shown locally, so you can test without email working
            }
        }
    }

    // Same message whether or not the email was found - don't let this page
    // be used to check which addresses are registered.
    flash('success', "If that email is registered, we've sent a reset link.");
}

$pageTitle = 'Forgot password';
require __DIR__ . '/../includes/partials/header.php';
?>
<div class="auth-card">
  <h1>Forgot your password?</h1>
  <p class="muted">Enter your email and we'll send a reset link.</p>

  <?php if ($message = flash('success')): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>

  <?php if ($resetLink): ?>
    <div class="alert alert-success">
      Local dev mode (no mail server configured): <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
  </form>

  <p class="auth-switch"><a href="/login.php">Back to login</a></p>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
