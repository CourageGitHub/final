<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');
$pageTitle = 'Analytics';
$activeNav = 'analytics';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>AI Analytics Dashboard</h1>
<div class="card">
  <p class="muted">Most-solved questions, popular courses, and AI usage trends — coming shortly.</p>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
