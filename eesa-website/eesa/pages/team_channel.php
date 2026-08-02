<?php
require_once __DIR__ . '/../config.php';
require_role(TEAM_CHANNEL_ROLES);
$pageTitle = 'Team Channel';
$me = current_user();
$csrfToken = csrf_token(); // assumes a helper that returns the raw token string; adjust if your helper differs
require __DIR__ . '/../includes/header.php';
?>
<style>
  .chat-wrap { max-width:640px; margin:0 auto; display:flex; flex-direction:column; height:calc(100vh - 160px); }
  .chat-header { padding:10px 4px 14px; }
  .chat-scroll {
    flex:1; overflow-y:auto; padding:12px; border-radius:12px;
    background:#0b141a; display:flex; flex-direction:column; gap:6px;
  }
  .bubble-row { display:flex; }
  .bubble-row.mine { justify-content:flex-end; }
  .bubble {
    max-width:75%; padding:7px 10px 8px; border-radius:9px; font-size:14.5px; line-height:1.35;
    background:#202c33; color:#e9edef; position:relative;
  }
  .bubble-row.mine .bubble { background:#005c4b; }
  .bubble .meta { display:flex; gap:6px; align-items:baseline; margin-bottom:2px; }
  .bubble .name { font-weight:600; font-size:12.5px; color:#8fd3c9; }
  .bubble-row.mine .bubble .name { display:none; }
  .bubble .role { font-size:10.5px; color:#8696a0; }
  .bubble .text { white-space:pre-wrap; word-wrap:break-word; }
  .bubble .foot { display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-top:3px; }
  .bubble .time { font-size:10.5px; color:#8696a0; }
  .bubble .del-btn {
    font-size:10.5px; color:#ff8a80; background:none; border:none; cursor:pointer; padding:0;
  }
  .chat-input-bar { display:flex; gap:8px; padding:10px 4px 0; }
  .chat-input-bar textarea {
    flex:1; resize:none; border-radius:20px; padding:10px 16px; max-height:120px;
    background:#202c33; color:#e9edef; border:1px solid #2a3942;
  }
  .chat-input-bar button {
    border-radius:50%; width:44px; height:44px; flex-shrink:0;
  }
</style>

<section class="section">
  <div class="container">
    <div class="chat-wrap">
      <div class="chat-header">
        <div class="eyebrow">Internal — team only</div>
        <h1 style="margin:2px 0 0">Team Channel</h1>
      </div>

      <div class="chat-scroll" id="chatScroll">
        <p class="muted" id="emptyState" style="display:none">No messages yet — be the first to post.</p>
      </div>

      <form class="chat-input-bar" id="chatForm">
        <textarea id="chatInput" name="message" placeholder="Message the team…" rows="1" required></textarea>
        <button class="btn btn-primary" type="submit">➤</button>
      </form>
    </div>
  </div>
</section>

<script>
(function () {
  const CSRF = <?= json_encode($csrfToken) ?>;
  const MY_ID = <?= (int)$me['id'] ?>;
  const scrollEl = document.getElementById('chatScroll');
  const emptyState = document.getElementById('emptyState');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('chatInput');

  let lastId = 0;
  let rendered = false;

  function esc(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function bubbleHtml(m) {
    const nameHtml = m.mine ? '' : `<span class="name">${esc(m.full_name)}</span><span class="role">${esc(m.role)}</span>`;
    const delHtml = m.can_delete ? `<button class="del-btn" data-id="${m.id}">Delete</button>` : '';
    return `
      <div class="bubble-row ${m.mine ? 'mine' : ''}" data-id="${m.id}">
        <div class="bubble">
          ${nameHtml ? `<div class="meta">${nameHtml}</div>` : ''}
          <div class="text">${esc(m.message)}</div>
          <div class="foot"><span class="time">${esc(m.time)}</span>${delHtml}</div>
        </div>
      </div>`;
  }

  function appendMessages(list) {
    if (!list.length) return;
    emptyState.style.display = 'none';
    const atBottom = scrollEl.scrollTop + scrollEl.clientHeight >= scrollEl.scrollHeight - 40;
    list.forEach(m => {
      scrollEl.insertAdjacentHTML('beforeend', bubbleHtml(m));
      lastId = Math.max(lastId, m.id);
    });
    if (atBottom || !rendered) scrollEl.scrollTop = scrollEl.scrollHeight;
    rendered = true;
  }

  function poll() {
    fetch('team_channel_data.php?action=list&since_id=' + lastId, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        if (lastId === 0 && data.messages.length === 0) emptyState.style.display = 'block';
        appendMessages(data.messages);
      })
      .catch(() => {});
  }

  poll();
  setInterval(poll, 1000);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    fetch('team_channel_data.php?action=post', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'message=' + encodeURIComponent(text) + '&csrf_token=' + encodeURIComponent(CSRF)
    }).then(poll);
  });

  scrollEl.addEventListener('click', function (e) {
    if (e.target.classList.contains('del-btn')) {
      const id = e.target.getAttribute('data-id');
      if (!confirm('Delete this message?')) return;
      fetch('team_channel_data.php?action=delete', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(CSRF)
      }).then(() => {
        const row = document.querySelector(`.bubble-row[data-id="${id}"]`);
        if (row) row.remove();
      });
    }
  });

  input.addEventListener('input', function () {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
