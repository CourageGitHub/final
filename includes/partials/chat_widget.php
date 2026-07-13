<?php if (($user['role'] ?? null) === 'student' && empty($hideChatWidget)): ?>
<div id="chatWidgetPanel" class="chat-widget-panel" hidden>
  <div class="chat-widget-header">
    <span>AI Study Assistant</span>
    <button type="button" id="chatWidgetClose" aria-label="Close chat">&times;</button>
  </div>
  <div id="chatWidgetLog" class="chat-widget-log">
    <?php foreach (get_chat_history() as $msg): ?>
      <div class="chat-bubble chat-bubble-<?= e($msg['role']) ?>"><?= nl2br(e($msg['content'])) ?></div>
    <?php endforeach; ?>
    <?php if (!get_chat_history()): ?>
      <p class="muted" style="font-size:0.85rem;">Ask me about your timetable or what to study.</p>
    <?php endif; ?>
  </div>
  <form id="chatWidgetForm" class="chat-widget-form">
    <input type="hidden" id="chatWidgetToken" value="<?= e(csrf_token()) ?>">
    <input type="text" id="chatWidgetInput" placeholder="Ask something…" autocomplete="off" required>
    <button type="submit" class="btn btn-primary btn-sm">Send</button>
  </form>
</div>
<button type="button" class="fab-chat" id="chatWidgetToggle" aria-label="Open AI Study Assistant">💬</button>
<script src="/assets/js/chat-widget.js"></script>
<script>
  initChat({ formId: 'chatWidgetForm', inputId: 'chatWidgetInput', logId: 'chatWidgetLog', tokenId: 'chatWidgetToken' });

  document.getElementById('chatWidgetToggle').addEventListener('click', function () {
    document.getElementById('chatWidgetPanel').hidden = false;
    this.hidden = true;
    document.getElementById('chatWidgetInput').focus();
  });
  document.getElementById('chatWidgetClose').addEventListener('click', function () {
    document.getElementById('chatWidgetPanel').hidden = true;
    document.getElementById('chatWidgetToggle').hidden = false;
  });
</script>
<?php endif; ?>
