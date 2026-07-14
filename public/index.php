<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();

if ($user) {
    redirect($user['role'] === 'admin' ? '/admin/index.php' : '/student/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HTU Repository — Past Questions &amp; Examination Timetables</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,700;1,500;1,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body class="landing">

<div class="l-hero">
  <div class="l-container">
    <nav class="l-nav">
      <a href="/" class="l-brand">
        <span class="l-brand-mark">🎓</span>
        <span class="l-brand-text"><strong>HTU Repository</strong><span>Past Questions · AI</span></span>
      </a>
      <div class="l-nav-links">
        <a href="#features">Features</a>
        <a href="#ai-assistant">AI Assistant</a>
        <a href="#how-it-works">How it works</a>
      </div>
      <div class="l-nav-actions">
        <a href="/login.php" class="l-signin">Sign in</a>
        <a href="/register.php" class="l-btn l-btn-gold">Get Started</a>
      </div>
    </nav>

    <div class="l-hero-grid">
      <div>
        <span class="l-pill">🎓 Now powered by AI · Ho Technical University</span>
        <h1>Past questions and timetables, <em>intelligently</em> organised.</h1>
        <p class="lead">Access verified past examination papers, get personalised study recommendations, and never miss a timetable change — all in one elegant academic workspace.</p>
        <div class="l-hero-actions">
          <a href="/register.php" class="l-btn l-btn-gold">Open Student Account →</a>
          <a href="/login.php" class="l-btn l-btn-outline-light">Explore Repository</a>
        </div>
      </div>

      <div class="l-hero-visual">
        <div class="l-doc-stack">📚 📄 📖</div>
        <div class="l-float-card gold">
          <span class="tag">Exam in 3 days</span>
          CSC 301 · Mon 9:00
        </div>
        <div class="l-float-card white">
          <span class="tag">AI Suggestion</span>
          <strong>Database Systems</strong>
          94% topic match for your level
        </div>
      </div>
    </div>

    <a href="/register.php" class="l-search">
      🔍 Try: <strong>CSC 301 Database Systems</strong>
    </a>

    <div class="l-stats">
      <div><div class="value">12k+</div><div class="label">Past Questions</div></div>
      <div><div class="value">48</div><div class="label">Departments</div></div>
      <div><div class="value">98%</div><div class="label">Student Match</div></div>
    </div>
  </div>
</div>

<section class="l-section" id="features">
  <div class="l-container">
    <div class="l-eyebrow-wrap"><span class="l-eyebrow">Everything in one place</span></div>
    <h2>Built for the way students <em>actually</em> study.</h2>
    <p class="l-sub">A focused workspace for past questions, timetables and AI-guided revision — designed with HTU faculty.</p>

    <div class="l-feature-grid">
      <div class="l-feature-card">
        <div class="l-feature-icon">📄</div>
        <h3>Smart Repository</h3>
        <p>Filter past questions by course, department, level, semester or year. Download in PDF, DOCX or image.</p>
      </div>
      <div class="l-feature-card">
        <div class="l-feature-icon">📅</div>
        <h3>Live Timetables</h3>
        <p>Always-current exam schedule with venue and duration. Updates appear the moment admin publishes them.</p>
      </div>
      <div class="l-feature-card">
        <div class="l-feature-icon">💬</div>
        <h3>AI Study Assistant</h3>
        <p>Ask anything: likely topics, repeated questions, study plans — or paste a question and get it solved.</p>
      </div>
      <div class="l-feature-card">
        <div class="l-feature-icon">📈</div>
        <h3>Exam Prediction</h3>
        <p>We analyse past papers to surface frequently repeated questions and probable topics.</p>
      </div>
      <div class="l-feature-card">
        <div class="l-feature-icon">🔔</div>
        <h3>Personalised Alerts</h3>
        <p>In-app reminders before exams and instant alerts the moment a timetable changes.</p>
      </div>
      <div class="l-feature-card">
        <div class="l-feature-icon">🛡️</div>
        <h3>Verified &amp; Secure</h3>
        <p>Every paper is reviewed and approved before publishing. Role-based access keeps data safe.</p>
      </div>
    </div>
  </div>
</section>

<section class="l-section l-section-navy" id="ai-assistant">
  <div class="l-container">
    <div class="l-ai-grid">
      <div>
        <span class="l-eyebrow on-navy">AI Assistant</span>
        <h2>A <em>tutor in your pocket</em>, ready 24/7.</h2>
        <p class="lead">Ask about likely exam topics, get past-paper recommendations, or ask it to solve a question outright. The assistant knows your department, level, and upcoming exams.</p>
        <ul class="l-ai-list">
          <li>Smart past-question recommendations</li>
          <li>Solves questions directly, with full explanations</li>
          <li>Real exam dates, not guesses</li>
          <li>Personalised revision reminders</li>
        </ul>
      </div>

      <div class="l-chat-mock">
        <div class="l-chat-head">
          <div class="l-chat-avatar">🤖</div>
          <div><strong>HTU Study Assistant</strong><span>● Online</span></div>
        </div>
        <div class="l-chat-body">
          <div class="l-chat-bubble user">Which past questions should I focus on for Database Systems?</div>
          <div class="l-chat-bubble bot">Based on the papers in the repository, focus on <strong>Normalisation</strong>, <strong>SQL Joins</strong>, and <strong>Transactions</strong>. I found 3 relevant papers for you.</div>
          <div class="l-chat-bubble user">When is my next exam?</div>
          <div class="l-chat-bubble bot"><strong>CSC 301</strong> — Monday, 9:00 AM at Auditorium A. Want help with a revision plan?</div>
        </div>
        <div class="l-chat-input">Ask anything… <span class="send">➤</span></div>
      </div>
    </div>
  </div>
</section>

<section class="l-section" id="how-it-works">
  <div class="l-container">
    <div class="l-eyebrow-wrap"><span class="l-eyebrow">How it works</span></div>
    <h2>From sign-up to first <em>A</em> in three steps.</h2>
    <div class="l-steps">
      <div class="l-step">
        <div class="num">01</div>
        <h3>Create your account</h3>
        <p>Sign up with your student details. We tailor the workspace to your department and level.</p>
      </div>
      <div class="l-step">
        <div class="num">02</div>
        <h3>Search &amp; download</h3>
        <p>Find any past question in seconds with smart filters and OCR-indexed search.</p>
      </div>
      <div class="l-step">
        <div class="num">03</div>
        <h3>Study with AI</h3>
        <p>Get worked answers, a study assistant on call, and timely exam reminders.</p>
      </div>
    </div>
  </div>
</section>

<footer class="l-footer">
  <div class="l-container">
    <div class="l-footer-grid">
      <div>
        <a href="/" class="l-brand">
          <span class="l-brand-mark">🎓</span>
          <span class="l-brand-text"><strong>HTU Repository</strong><span>Past Questions · AI</span></span>
        </a>
        <p class="l-footer-desc">The AI-powered past question and examination timetable system for Ho Technical University.</p>
      </div>
      <div>
        <h4>Repository</h4>
        <a href="/login.php">Past Questions</a>
        <a href="/login.php">Timetables</a>
        <a href="#features">Departments</a>
      </div>
      <div>
        <h4>Institution</h4>
        <a href="#">Ho Technical University</a>
        <a href="#">Faculty Portal</a>
        <a href="#">Support</a>
      </div>
    </div>
    <div class="l-footer-bottom">
      <span>&copy; <?= date('Y') ?> Ho Technical University. All rights reserved.</span>
      <span>Built with academic integrity &amp; care.</span>
    </div>
  </div>
  <div class="l-footer-bar"></div>
</footer>

</body>
</html>
