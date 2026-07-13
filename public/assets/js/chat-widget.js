function initChat(config) {
  var form  = document.getElementById(config.formId);
  var input = document.getElementById(config.inputId);
  var log   = document.getElementById(config.logId);
  var token = document.getElementById(config.tokenId).value;

  if (!form) return;

  function addBubble(role, text) {
    var div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble-' + role;
    div.textContent = text;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
    return div;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var message = input.value.trim();
    if (!message) return;

    addBubble('user', message);
    input.value = '';
    input.disabled = true;

    var thinking = addBubble('assistant', 'Thinking…');

    var body = new URLSearchParams();
    body.set('message', message);
    body.set('csrf_token', token);

    fetch('/api/chat.php', { method: 'POST', body: body })
      .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      })
      .then(function (result) {
        thinking.remove();
        if (result.ok) {
          addBubble('assistant', result.data.reply);
        } else {
          addBubble('assistant', 'Error: ' + (result.data.error || 'Something went wrong.'));
        }
      })
      .catch(function () {
        thinking.remove();
        addBubble('assistant', 'Network error — check your connection and try again.');
      })
      .finally(function () {
        input.disabled = false;
        input.focus();
      });
  });
}
