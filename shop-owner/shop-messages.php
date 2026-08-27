<?php
session_start();

$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("Location: ../login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] !== 'repairshop') { header("Location: dashboard.php"); exit(); }

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Messages - Fix It Davao</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
  <style>

    /* ── LOGOUT MODAL ── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(10,15,30,0.72);
  backdrop-filter: blur(4px); display: flex; align-items: center;
  justify-content: center; z-index: 1000; opacity: 0;
  pointer-events: none; transition: opacity 0.3s ease; padding: 20px;
}
.modal-overlay.visible { opacity: 1; pointer-events: all; }
.modal-box {
  background: white; border-radius: 20px; padding: 32px 28px;
  max-width: 420px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,0.25);
  transform: scale(0.9) translateY(20px); opacity: 0;
  transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
}
.modal-overlay.visible .modal-box { transform: scale(1) translateY(0); opacity: 1; }
.modal-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 6px; font-family: "Outfit", sans-serif; }
.modal-subtitle { font-size: 13px; color: #64748b; }
.modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: center; }
.modal-btn-cancel {
  flex: 1; padding: 11px; border: 2px solid #e2e8f0; border-radius: 10px;
  background: white; font-size: 13px; font-weight: 700;
  font-family: "Outfit", sans-serif; cursor: pointer; color: #64748b; transition: all 0.2s;
}
.modal-btn-cancel:hover { background: #f8fafc; }
.modal-btn-confirm {
  flex: 1; padding: 11px; border: none; border-radius: 10px; color: white;
  font-size: 13px; font-weight: 700; font-family: "Outfit", sans-serif;
  cursor: pointer; transition: all 0.2s;
}
.modal-btn-confirm:hover { transform: translateY(-1px); opacity: 0.9; }

    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.top-bar     { animation: fadeInUp 0.4s ease both; }
.threads-col { animation: fadeInUp 0.5s ease both; }
.chat-col    { animation: fadeInUp 0.6s ease both; }

    .dashboard-content { background: #f4f6fb; }

    .msgs-page-header {
      display: flex; align-items: center; gap: 10px; margin-bottom: 4px;
    }
    .msgs-page-header .emoji { font-size: 22px; }

    .msgs-layout { display: flex; gap: 18px; height: calc(100vh - 160px); min-height: 460px; margin-top: 14px; }

    /* ── Threads column ── */
    .threads-col {
      width: 320px; flex-shrink: 0;
      background: linear-gradient(180deg, #0d1117 0%, #141c2e 100%);
      border: 1px solid rgba(245,158,11,0.15);
      border-radius: 18px;
      overflow-y: auto;
      box-shadow: 0 12px 32px rgba(13,17,23,0.18), inset 0 1px 0 rgba(255,255,255,0.04);
    }
    .threads-col-header {
      padding: 16px 18px 12px;
      font-size: .78rem; font-weight: 800; letter-spacing: .4px;
      color: #f1f5f9; text-transform: uppercase;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex; align-items: center; gap: 8px;
    }
    .threads-col-header .dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.7);
    }

    .thread-item {
      display: flex; gap: 12px; padding: 13px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      cursor: pointer; transition: background .18s ease, transform .18s ease;
      position: relative;
    }
    .thread-item:hover { background: rgba(245,158,11,0.06); }
    .thread-item.active {
      background: linear-gradient(90deg, rgba(245,158,11,0.16), rgba(245,158,11,0.03));
    }
    .thread-item.active::before {
      content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
      background: linear-gradient(180deg, #f59e0b, #fbbf24);
    }
    .thread-avatar {
      width: 44px; height: 44px; border-radius: 12px; object-fit: cover; flex-shrink: 0;
      background: #2563eb; border: 1px solid rgba(255,255,255,0.1);
    }
    .thread-info { flex: 1; min-width: 0; }
    .thread-name {
      font-size: .85rem; font-weight: 700; color: #f1f5f9;
      display: flex; align-items: center; gap: 6px;
    }
    .thread-last {
      font-size: .75rem; color: #8b98ad;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      margin-top: 3px; max-width: 200px;
    }
    .thread-badge {
      background: linear-gradient(135deg,#f59e0b,#d97706); color: #fff;
      font-size: .64rem; font-weight: 800; padding: 2px 7px; border-radius: 10px;
      margin-left: auto; box-shadow: 0 2px 6px rgba(245,158,11,0.4);
    }

    /* ── Chat column ── */
    .chat-col {
      flex: 1;
      background: #fff;
      border: 1px solid #e8ecf3;
      border-radius: 18px;
      display: flex; flex-direction: column; overflow: hidden;
      box-shadow: 0 12px 32px rgba(15,23,42,0.06);
    }
    .chat-header {
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      font-weight: 800; font-size: .92rem; color: #0f172a;
      background: linear-gradient(180deg, #fff, #fafbfd);
      display: flex; align-items: center; gap: 10px;
    }
    .chat-header::before {
      content: ''; width: 8px; height: 8px; border-radius: 50%;
      background: #d1d5db; flex-shrink: 0;
    }
    .chat-header.active::before { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.6); }

    .chat-body {
      flex: 1; overflow-y: auto; padding: 20px;
      display: flex; flex-direction: column; gap: 10px;
      background:
        radial-gradient(circle at 20% 10%, rgba(245,158,11,0.03), transparent 40%),
        #f8fafc;
    }
    .msg-row {
      display: flex; align-items: flex-end; gap: 8px; max-width: 78%;
    }
    .msg-row.mine   { align-self: flex-end; flex-direction: row-reverse; }
    .msg-row.theirs { align-self: flex-start; }
    .msg-avatar {
      width: 30px; height: 30px; border-radius: 50%; object-fit: cover;
      flex-shrink: 0; border: 1px solid #e9edf3; background: #eef2f6;
    }
    .msg-bubble {
      font-size: .85rem; line-height: 1.5;
      padding: 10px 14px; border-radius: 14px;
      white-space: pre-wrap; word-wrap: break-word;
      box-shadow: 0 2px 8px rgba(15,23,42,0.05);
    }
    .msg-bubble.mine {
      background: linear-gradient(135deg,#f59e0b,#d97706);
      color: #fff; border-bottom-right-radius: 4px;
    }
    .msg-bubble.theirs {
      background: #fff; border: 1px solid #e9edf3;
      color: #0f172a; border-bottom-left-radius: 4px;
    }

    .chat-footer {
      padding: 14px 16px; border-top: 1px solid #eef2f6;
      display: flex; gap: 10px; align-items: center;
      background: #fff;
    }
    .chat-input {
      flex: 1; border: 1.5px solid #e2e8f0; border-radius: 14px;
      padding: 11px 16px; font-size: .85rem; font-family: "Outfit", sans-serif;
      outline: none; resize: none; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .chat-input:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
    .chat-send {
      background: linear-gradient(135deg,#f59e0b,#d97706); border: none; color: #fff;
      width: 44px; height: 44px; border-radius: 14px; cursor: pointer;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(245,158,11,0.35);
      transition: transform .15s ease, opacity .15s ease;
    }
    .chat-send:hover:not(:disabled) { transform: translateY(-1px) scale(1.03); }
    .chat-send:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; }

    .chat-empty, .thread-empty {
      text-align: center; color: #94a3b8; font-size: .84rem; padding: 48px 24px;
    }
    .thread-empty { color: #64748b; }
    .thread-empty small { color: #475569; }
    .chat-empty-icon { font-size: 28px; margin-bottom: 8px; opacity: .5; }

    @media (max-width: 820px) {
      .msgs-layout { flex-direction: column; height: auto; }
      .threads-col { width: 100%; max-height: 280px; }
      .chat-col { height: 520px; }
    }

    /* ── Sidebar backdrop (para ma-close pag click outside) ── */
    .sidebar-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 900;
    }
    body.sidebar-open .sidebar-backdrop {
      display: block;
    }
    .sidebar {
      z-index: 950;
    }

.chat-empty-icon img {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

.chat-placeholder {
    margin: auto;
    text-align: center;
    opacity: .4;
}
.chat-placeholder img {
    width: 76px;
    height: 76px;
    object-fit: contain;
}
.chat-placeholder p {
    margin: 12px 0 0;
    font-size: .85rem;
    font-weight: 600;
    color: #64748b;
}

  </style>
</head>
<body class="role-repairshop">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
      <h2 class="brand-name">Fix It Davao</h2>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section" data-role="repairshop">
        <a href="shop-dashboard.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/dashboard.svg" alt="Dashboard" onerror="this.style.display='none'" /></span>
          <span class="nav-text">Dashboard</span>
        </a>
        <a href="shop-information.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/shop.svg" alt="" /></span>
          <span class="nav-text">My Shop</span>
        </a>
        <a href="shop-bookings.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/booking.svg" alt="" /></span>
          <span class="nav-text">Bookings</span>
        </a>
        <a href="shop-services.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/services.svg" alt="" /></span>
          <span class="nav-text">Services &amp; Fees</span>
        </a>
        <a href="shop-reviews.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/reviews.svg" alt="" /></span>
          <span class="nav-text">Reviews</span>
        </a>
        <a href="shop-messages.php" class="nav-item active">
          <span class="nav-icon"><img src="../assets/icons/talk.svg" alt="" /></span>
          <span class="nav-text">Messages</span>
        </a>
               <a href="shop-subscription.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="" /></span>
          <span class="nav-text">Subscription</span>
        </a>
      </div>
    </nav>
    <div class="sidebar-footer">
      <a href="../logout.php" class="nav-item" onclick="return confirmLogout(event)">
        <span class="nav-icon"><img src="../assets/icons/logout.svg" alt="" /></span>
        <span class="nav-text">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Logout Modal -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal-box" style="max-width:380px; text-align:center;">
      <div style="font-size:48px; margin-bottom:12px;">👋</div>
      <div class="modal-title">Logging Out?</div>
      <div class="modal-subtitle" style="margin-bottom:24px;">Are you sure you want to logout of Fix It Davao?</div>
      <div class="modal-actions" style="justify-content:center;">
        <button class="modal-btn-cancel" onclick="closeLogoutModal()">Cancel</button>
        <button class="modal-btn-confirm" style="background:linear-gradient(135deg,#ef4444,#dc2626);" onclick="window.location.href='../logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>
  </aside>

  <main class="main-content">
    <header class="top-bar">
      <div class="page-header">
        <h1 class="current-page-title">
          <span class="msgs-page-header"><span class="emoji"></span> Messages</span>
        </h1>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="msgs-layout">
        <div class="threads-col" id="threadsCol">
          <div class="threads-col-header"><span class="dot"></span> Conversations</div>
          <div class="thread-empty">Loading conversations…</div>
        </div>
        <div class="chat-col">
          <div class="chat-header" id="chatHeader">Select a conversation</div>
          <div class="chat-body" id="chatBody">
            <div class="chat-placeholder">
              <img src="../assets/icons/reply.svg" alt="">
              <p>Select a conversation to start messaging</p>
            </div>
          </div>
          <div class="chat-footer">
            <textarea id="chatInput" class="chat-input" rows="1" placeholder="Type a message..." disabled></textarea>
            <button id="chatSend" class="chat-send" disabled>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
    <footer class="dashboard-footer">© 2026 All Rights Reserved — Fix It Davao</footer>
  </main>

  <script>

function confirmLogout(e) {
  e.preventDefault();
  sidebar.classList.remove('active');
  document.body.classList.remove('sidebar-open');
  document.getElementById('logoutModal').classList.add('visible');
  return false;
}
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('visible');
}

    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
      });

      if (backdrop) {
        backdrop.addEventListener('click', function () {
          sidebar.classList.remove('active');
          document.body.classList.remove('sidebar-open');
        });
      }
    }

    function escHtml(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    let activeOtherId = null;
    let activeOtherName = '';
    let myAvatar = '';
    let otherAvatar = '';
    let sending = false;
    const myName = <?php echo json_encode($userName); ?>;

    function fallbackAvatar(name) {
      return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&background=2563eb&color=fff&size=64`;
    }
    function bubbleHtml(text, mine) {
      const name = mine ? myName : activeOtherName;
      const src  = (mine ? myAvatar : otherAvatar) || fallbackAvatar(name);
      return `<div class="msg-row ${mine ? 'mine' : 'theirs'}">
        <img class="msg-avatar" src="${src}" alt="" onerror="this.src='${fallbackAvatar(name)}'">
        <div class="msg-bubble ${mine ? 'mine' : 'theirs'}">${escHtml(text)}</div>
      </div>`;
    }

    async function loadThreads() {
      try {
        const res = await fetch('../api/messages.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'list' })
        });
        const data = await res.json();
        const col = document.getElementById('threadsCol');
        const headerHtml = '<div class="threads-col-header"><span class="dot"></span> Conversations</div>';
        if (!data.success || !data.threads.length) {
          col.innerHTML = headerHtml + '<div class="thread-empty">No conversations yet.<br><small>Customers can message you from your shop page.</small></div>';
          return;
        }
        col.innerHTML = headerHtml + data.threads.map(t => {
          const avatar = t.other_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(t.other_name)}&background=2563eb&color=fff&size=80`;
          const badge = t.unread_count > 0 ? `<span class="thread-badge">${t.unread_count}</span>` : '';
          const isActive = String(t.other_id) === String(activeOtherId) ? ' active' : '';
          return `
            <div class="thread-item${isActive}" data-id="${t.other_id}" onclick="openThread(${t.other_id}, '${escHtml(t.other_name)}', this)">
              <img class="thread-avatar" src="${avatar}" alt="" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(t.other_name)}&background=2563eb&color=fff&size=80'" />
              <div class="thread-info">
                <div class="thread-name">${escHtml(t.other_name)}${badge}</div>
                <div class="thread-last">${escHtml(t.last_message || '')}</div>
              </div>
            </div>`;
        }).join('');
      } catch (err) {
        document.getElementById('threadsCol').innerHTML = '<div class="threads-col-header"><span class="dot"></span> Conversations</div><div class="thread-empty">Could not load conversations.</div>';
      }
    }

    async function openThread(otherId, otherName, el) {
      activeOtherId = otherId;
      activeOtherName = otherName;
      document.querySelectorAll('.thread-item').forEach(t => t.classList.remove('active'));
      if (el) el.classList.add('active');
      const badge = el?.querySelector('.thread-badge');
      if (badge) badge.remove();

      document.getElementById('chatHeader').textContent = otherName;
      document.getElementById('chatBody').innerHTML = '<div class="chat-empty">Loading…</div>';
      document.getElementById('chatInput').disabled = false;
      document.getElementById('chatSend').disabled = false;

      try {
        const res = await fetch('../api/messages.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'thread', other_id: otherId })
        });
        const data = await res.json();
        const bodyEl = document.getElementById('chatBody');
        if (!data.success) { bodyEl.innerHTML = '<div class="chat-empty">Could not load messages.</div>'; return; }
        otherAvatar = data.other_avatar || '';
        myAvatar    = data.my_avatar || '';
        if (!data.messages.length) { bodyEl.innerHTML = '<div class="chat-empty">No messages yet.</div>'; return; }
        bodyEl.innerHTML = data.messages.map(m => bubbleHtml(m.message, m.sender_role === 'shop')).join('');
        bodyEl.scrollTop = bodyEl.scrollHeight;
      } catch (err) {
        document.getElementById('chatBody').innerHTML = '<div class="chat-empty">Network error.</div>';
      }
    }

    async function sendChatMessage() {
      const input = document.getElementById('chatInput');
      const text = input.value.trim();
      if (!text || sending || !activeOtherId) return;

      sending = true;
      document.getElementById('chatSend').disabled = true;

      const bodyEl = document.getElementById('chatBody');
      if (bodyEl.querySelector('.chat-empty')) bodyEl.innerHTML = '';
      bodyEl.insertAdjacentHTML('beforeend', bubbleHtml(text, true));
      bodyEl.scrollTop = bodyEl.scrollHeight;
      input.value = '';
      input.style.height = 'auto';

      try {
        const res = await fetch('../api/messages.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'send', other_id: activeOtherId, message: text })
        });
        const data = await res.json();
        if (!data.success) {
          bodyEl.insertAdjacentHTML('beforeend', `<div class="chat-empty">${escHtml(data.message || 'Failed to send.')}</div>`);
        } else {
          loadThreads();
        }
      } catch (err) {
        bodyEl.insertAdjacentHTML('beforeend', '<div class="chat-empty">Network error. Message not sent.</div>');
      } finally {
        sending = false;
        document.getElementById('chatSend').disabled = false;
      }
    }

    document.getElementById('chatSend').addEventListener('click', sendChatMessage);
    document.getElementById('chatInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
    });
    document.getElementById('chatInput').addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 90) + 'px';
    });

    loadThreads();
    setInterval(loadThreads, 15000); // light polling for new conversations/unread counts
  </script>
</body>
</html>