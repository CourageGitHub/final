<?php
/** @var array $user
 *  @var string $pageTitle
 *  @var string $activeNav
 */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dashboard') ?> &mdash; PQ &amp; Timetable</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<script>
  (function () {
    var saved = localStorage.getItem('theme');
    if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  })();
</script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="brand-mark" aria-hidden="true"></span><span>PQ &amp; Timetable</span></div>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="/admin/index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="/admin/students.php" class="<?= $activeNav === 'students' ? 'active' : '' ?>">Students</a>
      <a href="/admin/courses.php" class="<?= $activeNav === 'courses' ? 'active' : '' ?>">Courses</a>
      <a href="/admin/questions.php" class="<?= $activeNav === 'questions' ? 'active' : '' ?>">Past Questions</a>
      <a href="/admin/timetable.php" class="<?= $activeNav === 'timetable' ? 'active' : '' ?>">Timetable</a>
      <a href="/admin/analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">Analytics</a>
      <a href="/admin/notifications.php" class="<?= $activeNav === 'notifications' ? 'active' : '' ?>">Notifications</a>
      <a href="/profile.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">Profile</a>
    <?php else: ?>
      <a href="/student/index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="/student/repository.php" class="<?= $activeNav === 'repository' ? 'active' : '' ?>">Past Questions</a>
      <a href="/student/assistant.php" class="<?= $activeNav === 'assistant' ? 'active' : '' ?>">AI Assistant</a>
      <a href="/student/timetable.php" class="<?= $activeNav === 'timetable' ? 'active' : '' ?>">Timetable</a>
      <a href="/student/favorites.php" class="<?= $activeNav === 'favorites' ? 'active' : '' ?>">Favorites</a>
      <a href="/student/notifications.php" class="<?= $activeNav === 'notifications' ? 'active' : '' ?>">Notifications</a>
      <a href="/profile.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">Profile</a>
    <?php endif; ?>
  </aside>
  <div>
    <div class="topbar">
      <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙 Dark</button>
      <span class="nav-user"><?= e($user['full_name']) ?></span>
      <a href="/logout.php" class="btn btn-secondary btn-sm">Log out</a>
    </div>
    <div class="app-content">
