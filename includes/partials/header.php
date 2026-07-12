<?php /** @var string $pageTitle */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Past Question & Timetable Portal') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<script>
  // Applied before paint so the page never flashes the wrong theme.
  (function () {
    var saved = localStorage.getItem('theme');
    if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  })();
</script>
</head>
<body>
<header class="site-header">
  <a class="brand" href="/">
    <span class="brand-mark" aria-hidden="true"></span>
    <span>PQ &amp; Timetable</span>
  </a>
  <nav class="site-nav">
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙 Dark</button>
    <?php if ($user = current_user()): ?>
      <span class="nav-user"><?= e($user['full_name']) ?> &middot; <?= e(ucfirst($user['role'])) ?></span>
      <a href="/logout.php">Log out</a>
    <?php endif; ?>
  </nav>
</header>
<main class="site-main">
<script>
  document.getElementById('themeToggle').addEventListener('click', function () {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
    }
  });
</script>
