<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();

if ($user) {
    redirect($user['role'] === 'admin' ? '/admin/index.php' : '/student/index.php');
}

$pageTitle = 'Past Questions & Timetable Portal';
require __DIR__ . '/../includes/partials/header.php';
?>
<div style="max-width:760px; margin:0 auto; text-align:center; padding:20px 0 40px;">
  <span class="ai-badge">AI-powered</span>
  <h1 style="font-size:2.1rem; margin-top:14px;">Past questions and exam timetables, in one place.</h1>
  <p class="muted" style="font-size:1.05rem; max-width:560px; margin:0 auto 28px;">
    Search past exam papers by course, get AI help solving them, and never miss an exam date again.
  </p>
  <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
    <a href="/register.php" class="btn btn-primary">Create a student account</a>
    <a href="/login.php" class="btn btn-secondary">Log in</a>
  </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; max-width:960px; margin:0 auto;">
  <div class="card">
    <h2 style="font-size:1.05rem;">📚 Past question repository</h2>
    <p class="muted">Search and download past exam papers by course, level, semester, or year.</p>
  </div>
  <div class="card">
    <h2 style="font-size:1.05rem;">🤖 AI Solver</h2>
    <p class="muted">Get a worked answer and explanation for any question in the repository.</p>
  </div>
  <div class="card">
    <h2 style="font-size:1.05rem;">🗓️ Exam timetable</h2>
    <p class="muted">See your exact exam dates, times, and venues, filtered to your class.</p>
  </div>
  <div class="card">
    <h2 style="font-size:1.05rem;">💬 Study Assistant</h2>
    <p class="muted">Ask what to study, when your next exam is, or paste a question to solve.</p>
  </div>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
