<?php
/**
 * Floating "Help Assistant" chatbot widget — customer-only.
 * Include this near the end of the <body>, e.g.:
 *   <?php include __DIR__ . '/../includes/chatbot-widget.php'; ?>
 * Expects `api/chatbot.php` reachable at ../api/chatbot.php relative to the
 * page that includes this file (adjust $chatbotApiPath below if needed).
 */
$chatbotApiPath = $chatbotApiPath ?? '../api/chatbot.php';
$chatbotLogoPath = $chatbotLogoPath ?? '../assets/images/logo.png';

// ── Resolve the logged-in customer's avatar (DB profile pic, else initials) ──
if (session_status() === PHP_SESSION_NONE) session_start();
$chatbotUserAvatar = '';
if (!empty($_SESSION['user_id'])) {
    $c = @new mysqli("localhost", "root", "", "fixitdavao");
    if ($c && !$c->connect_error) {
        $st = $c->prepare("SELECT profile_picture, name FROM users WHERE id = ?");
        $st->bind_param("i", $_SESSION['user_id']);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        $st->close(); $c->close();
        $chatbotUserAvatar = $u['profile_picture']
            ?: ("https://ui-avatars.com/api/?name=" . urlencode($u['name'] ?? 'U') . "&background=2563eb&color=fff");
    }
}
?>
<style>
  /* ── Chatbot floating button ── */
  #fidChatToggle {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg,#f59e0b,#d97706);
    border: none;
    box-shadow: 0 10px 30px rgba(245,158,11,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1400;
    transition: transform .2s ease, box-shadow .2s ease;
    animation: fidBounce 0.9s ease-in-out infinite;
  }
  @keyframes fidBounce {
    0%, 100%   { transform: translateY(0); }
    50%        { transform: translateY(-10px); }
  }
  #fidChatToggle:hover { animation-play-state: paused; transform: translateY(-2px) scale(1.04); box-shadow: 0 14px 34px rgba(245,158,11,0.55); }
  #fidChatToggle.fid-chat-active { animation: none; }
  #fidChatToggle svg { width: 26px; height: 26px; }
  #fidChatToggle img { width: 32px; height: 32px; object-fit: contain; border-radius: 50%; }
  #fidChatToggle .fid-chat-dot {
    position: absolute; top: 4px; right: 4px;
    width: 10px; height: 10px; border-radius: 50%;
    background: #22c55e; border: 2px solid white;
  }

  @media (max-width: 768px) {
    #fidChatToggle { right: 16px; bottom: 16px; width: 50px; height: 50px; }
  }

  /* ── Chat panel ── */
  #fidChatPanel {
    position: fixed;
    right: 24px;
    bottom: 92px;
    width: 340px;
    max-width: calc(100vw - 32px);
    height: 460px;
    max-height: calc(100vh - 140px);
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 24px 60px rgba(2,6,23,0.28);
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1400;
    opacity: 0;
    pointer-events: none;
    transform: translateY(12px) scale(0.97);
    transition: opacity .2s ease, transform .2s ease;
    font-family: "Outfit", sans-serif;
  }
  #fidChatPanel.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

  @media (max-width: 768px) {
    #fidChatPanel {
      right: 8px; left: 8px; bottom: 78px; width: auto;
      height: min(70vh, 520px);
    }
  }

  .fid-chat-header {
    background: linear-gradient(135deg,#0f172a,#1e293b);
    color: #fff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .fid-chat-header .fid-chat-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg,#f59e0b,#d97706);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .fid-chat-header .fid-chat-avatar svg { width: 18px; height: 18px; }
  .fid-chat-header .fid-chat-avatar img { width: 22px; height: 22px; object-fit: contain; border-radius: 50%; }
  .fid-chat-title { font-size: .88rem; font-weight: 800; }
  .fid-chat-subtitle { font-size: .68rem; color: #94a3b8; margin-top: 1px; }
  .fid-chat-close {
    margin-left: auto; background: none; border: none; color: #94a3b8;
    cursor: pointer; font-size: 1.1rem; line-height: 1; padding: 4px;
  }
  .fid-chat-close:hover { color: #fff; }
  .fid-chat-clear {
    margin-left: auto; background: none; border: none; color: #94a3b8;
    cursor: pointer; line-height: 1; padding: 4px; display: flex; align-items: center;
  }
  .fid-chat-clear + .fid-chat-close { margin-left: 0; }
  .fid-chat-clear:hover { color: #fff; }

  .fid-chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8fafc;
  }
  .fid-row { display: flex; align-items: flex-end; gap: 8px; }
  .fid-row.user { flex-direction: row-reverse; }
  .fid-avatar {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
  }
  .fid-avatar.bot { background: #fff; border: 1px solid #e2e8f0; }
  .fid-avatar.bot img { width: 20px; height: 20px; object-fit: contain; border-radius: 50%; }
  .fid-avatar.user { background: linear-gradient(135deg,#f59e0b,#d97706); color: #fff; }
  .fid-avatar.user svg { width: 15px; height: 15px; }
  .fid-avatar.user img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

  .fid-msg { max-width: calc(100% - 44px); font-size: .82rem; line-height: 1.45; padding: 9px 12px; border-radius: 12px; white-space: pre-wrap; word-wrap: break-word; }
  .fid-msg.bot { background: #fff; border: 1px solid #e2e8f0; color: #0f172a; border-bottom-left-radius: 4px; }
  .fid-msg.user { background: linear-gradient(135deg,#f59e0b,#d97706); color: #fff; border-bottom-right-radius: 4px; }
  .fid-msg.typing { color: #94a3b8; font-style: italic; }

  .fid-chat-footer {
    padding: 10px;
    border-top: 1px solid #eef2f6;
    display: flex;
    gap: 8px;
    background: #fff;
  }
  .fid-chat-input {
    flex: 1;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: .82rem;
    font-family: "Outfit", sans-serif;
    outline: none;
    resize: none;
  }
  .fid-chat-input:focus { border-color: #f59e0b; }
  .fid-chat-input:disabled { background: #f1f5f9; cursor: not-allowed; }
  .fid-chat-send {
    background: linear-gradient(135deg,#f59e0b,#d97706);
    border: none;
    color: #fff;
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
  }
  .fid-chat-send:disabled { opacity: .5; cursor: not-allowed; }
</style>

<button id="fidChatToggle" aria-label="Open Help Assistant">
  <span class="fid-chat-dot"></span>
  <img src="<?php echo htmlspecialchars($chatbotLogoPath); ?>" alt="Fix It Davao" />
</button>

<div id="fidChatPanel">
  <div class="fid-chat-header">
    <div class="fid-chat-avatar">
      <img src="<?php echo htmlspecialchars($chatbotLogoPath); ?>" alt="Fix It Davao" />
    </div>
    <div>
      <div class="fid-chat-title">Fix It Davao Help</div>
      <div class="fid-chat-subtitle">Ask about booking, shops, or your account</div>
    </div>
    <button class="fid-chat-clear" id="fidChatClear" aria-label="Clear conversation" title="Clear conversation">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
      </svg>
    </button>
    <button class="fid-chat-close" id="fidChatClose" aria-label="Close">✕</button>
  </div>
  <div class="fid-chat-body" id="fidChatBody">
    <div class="fid-row bot">
      <div class="fid-avatar bot"><img src="<?php echo htmlspecialchars($chatbotLogoPath); ?>" alt="Assistant" /></div>
      <div class="fid-msg bot">Hi! I'm the Fix It Davao Help Assistant. Ask me anything about booking a repair, your account, or how the site works.</div>
    </div>
  </div>
  <div class="fid-chat-footer">
    <textarea id="fidChatInput" class="fid-chat-input" rows="1" placeholder="Type your question..."></textarea>
    <button id="fidChatSend" class="fid-chat-send" aria-label="Send">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</div>

<script>
(function () {
  const CHAT_API = <?php echo json_encode($chatbotApiPath); ?>;
  const LOGO_SRC = <?php echo json_encode($chatbotLogoPath); ?>;
  const USER_AVATAR_URL = <?php echo json_encode($chatbotUserAvatar); ?>;
  const USER_AVATAR_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

  const toggleBtn = document.getElementById('fidChatToggle');
  const panel     = document.getElementById('fidChatPanel');
  const closeBtn  = document.getElementById('fidChatClose');
  const clearBtn  = document.getElementById('fidChatClear');
  const body      = document.getElementById('fidChatBody');
  const input     = document.getElementById('fidChatInput');
  const sendBtn   = document.getElementById('fidChatSend');
  const greetingHTML = body.innerHTML;

  let history = []; // {role, content}
  let sending = false;
  let loaded  = false;
  let coolingDown = false;

  function openPanel()  { panel.classList.add('open'); toggleBtn.classList.add('fid-chat-active'); input.focus(); loadHistory(); }
  function closePanel() { panel.classList.remove('open'); toggleBtn.classList.remove('fid-chat-active'); }

  // ── Load this user's saved conversation once per page ──
  async function loadHistory() {
    if (loaded) return;
    loaded = true;
    try {
      const res = await fetch(CHAT_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'load' })
      });
      const data = await res.json();
      if (data.success && Array.isArray(data.messages) && data.messages.length) {
        data.messages.forEach(m => addMessage(m.content, m.role === 'user' ? 'user' : 'bot'));
        history = data.messages.slice(-10);
      }
    } catch (err) { /* keep greeting on failure */ }
  }

  toggleBtn.addEventListener('click', () => {
    panel.classList.contains('open') ? closePanel() : openPanel();
  });
  closeBtn.addEventListener('click', closePanel);

  // ── Clear the saved conversation ──
  async function clearHistory() {
    if (sending || coolingDown) return;
    if (!confirm('Clear this conversation? This cannot be undone.')) return;
    try {
      await fetch(CHAT_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear' })
      });
    } catch (err) { /* clear locally anyway */ }
    history = [];
    body.innerHTML = greetingHTML;
  }
  clearBtn.addEventListener('click', clearHistory);

  function makeAvatar(role) {
    const avatar = document.createElement('div');
    avatar.className = 'fid-avatar ' + (role === 'user' ? 'user' : 'bot');
    if (role === 'user') {
      if (USER_AVATAR_URL) {
        const img = document.createElement('img');
        img.src = USER_AVATAR_URL;
        img.alt = 'You';
        img.onerror = () => { avatar.innerHTML = USER_AVATAR_SVG; };
        avatar.appendChild(img);
      } else {
        avatar.innerHTML = USER_AVATAR_SVG;
      }
    } else {
      const img = document.createElement('img');
      img.src = LOGO_SRC;
      img.alt = 'Assistant';
      avatar.appendChild(img);
    }
    return avatar;
  }

  function addMessage(text, role) {
    const row = document.createElement('div');
    row.className = 'fid-row ' + (role === 'user' ? 'user' : 'bot');
    const bubble = document.createElement('div');
    bubble.className = 'fid-msg ' + (role === 'user' ? 'user' : 'bot');
    bubble.textContent = text;
    row.appendChild(makeAvatar(role));
    row.appendChild(bubble);
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
    return row;
  }

  function addTyping() {
    const row = document.createElement('div');
    row.className = 'fid-row bot';
    row.id = 'fidTypingIndicator';
    const bubble = document.createElement('div');
    bubble.className = 'fid-msg bot typing';
    bubble.textContent = 'Typing…';
    row.appendChild(makeAvatar('bot'));
    row.appendChild(bubble);
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
  }
  function removeTyping() {
    const el = document.getElementById('fidTypingIndicator');
    if (el) el.remove();
  }

  async function sendMessage() {
    const text = input.value.trim();
    if (!text || sending || coolingDown) return;

    sending = true;
    sendBtn.disabled = true;
    addMessage(text, 'user');
    input.value = '';
    input.style.height = 'auto';
    addTyping();

    try {
      const res = await fetch(CHAT_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: history })
      });
      const data = await res.json();
      removeTyping();

      if (data.success) {
        addMessage(data.reply, 'bot');
        history.push({ role: 'user', content: text });
        history.push({ role: 'assistant', content: data.reply });
        if (history.length > 10) history = history.slice(-10);
      } else if (data.rate_limited) {
        addMessage(data.message || "You're sending messages too fast. Please slow down.", 'bot');
        if (data.retry_after) {
          // Briefly disable sending until the server-side cooldown clears
          coolingDown = true;
          sendBtn.disabled = true;
          input.disabled = true;
          setTimeout(() => {
            coolingDown = false;
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
          }, (data.retry_after + 0.2) * 1000);
        }
      } else {
        addMessage(data.message || "Sorry, I couldn't process that. Please try again.", 'bot');
      }
    } catch (err) {
      removeTyping();
      addMessage("Sorry, something went wrong. Please try again.", 'bot');
    } finally {
      sending = false;
      if (!coolingDown) sendBtn.disabled = false;
    }
  }

  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 90) + 'px';
  });
})();
</script>