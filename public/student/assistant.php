<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    csrf_verify();
    clear_chat_history();
    redirect('/student/assistant.php');
}

$history = get_chat_history();
$hideChatWidget = true; // this page IS the chat - don't also show the floating bubble

$pageTitle = 'AI Study Assistant';
$activeNav = 'assistant';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>AI Study Assistant <span class="ai-badge">AI-powered</span></h1>
<p class="muted">Ask about your timetable, what to study, or get help understanding a past question.</p>

<div class="card" style="display:flex; flex-direction:column; height:60vh;">
  <div id="chatLog" class="chat-widget-log" style="flex:1; position:static; width:auto; height:auto; border:none; box-shadow:none;">
    <?php foreach ($history as $msg): ?>
      <div class="chat-bubble chat-bubble-<?= e($msg['role']) ?>"><?= nl2br(e($msg['content'])) ?></div>
    <?php endforeach; ?>
    <?php if (!$history): ?>
      <p class="muted">Try: "When is my next exam?" or "Which past questions should I study for [course code]?"</p>
    <?php endif; ?>
  </div>
  <form id="chatForm" style="display:flex; gap:8px; margin-top:12px;">
    <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
    <input type="text" id="chatInput" placeholder="Ask something…" autocomplete="off" style="flex:1;" required>
    <button type="submit" class="btn btn-primary">Send</button>
  </form>
  <form method="post" style="margin-top:8px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="btn btn-secondary btn-sm">Clear conversation</button>
  </form>
</div>
<p class="muted" style="margin-top:12px; font-size:0.8rem;">AI-generated answers can be wrong — always verify against your course material.</p>

<script src="/assets/js/chat-widget.js"></script>
<script>
  initChat({ formId: 'chatForm', inputId: 'chatInput', logId: 'chatLog', tokenId: 'csrfToken' });
</script>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
