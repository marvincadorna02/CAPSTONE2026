<?php
session_start();

// ── Session timeout (30 mins) ──
$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("../login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) { header("../login.php"); exit(); }
if ($_SESSION['role'] !== 'customer') {
    header("Location: " . ($_SESSION['role'] === 'repairshop' ? '../shop-owner/shop-information.php' : '../admin/admin-dashboard.php'));
    exit();
}

$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];
$avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>History - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>

/* ── NOTIFICATIONS (matched to admin-dashboard.php, mobile-safe) ── */
.notif-wrapper { position:relative; }
.notif-badge {
  position:absolute; top:-4px; right:-4px;
  background:#ef4444; color:white; font-size:.6rem;
  font-weight:700; min-width:18px; height:18px;
  border-radius:20px; display:flex; align-items:center;
  justify-content:center; padding:0 4px;
  font-family:"Outfit",sans-serif; pointer-events:none;
  border:2px solid white;
}
.notif-dropdown {
  position:absolute; top:calc(100% + 10px); right:0;
  width:320px; background:white; border-radius:16px;
  border:1px solid #e2e8f0; box-shadow:0 20px 60px rgba(0,0,0,.18);
  z-index:999; opacity:0; pointer-events:none;
  transform:translateY(-8px) scale(0.97);
  transition:opacity 0.22s ease, transform 0.22s ease;
  overflow:hidden;
}
.notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }

/* ← THIS is the missing piece causing the mobile overflow */
@media (max-width: 768px) {
  .notif-dropdown {
    position: fixed !important;
    top: 70px !important;
    left: 8px !important;
    right: 8px !important;
    width: auto !important;
    max-width: 100% !important;
    max-height: 70vh;
    overflow-y: auto;
    z-index: 1200;
  }
}
.notif-header {
  display:flex; justify-content:space-between; align-items:center;
  padding:.75rem 1rem; border-bottom:1px solid #f1f5f9;
}
.notif-title { font-size:.85rem; font-weight:800; color:#0f172a; }
.notif-mark-read {
  font-size:.72rem; font-weight:700; color:#f59e0b;
  background:none; border:none; cursor:pointer;
  font-family:"Outfit",sans-serif;
  padding:3px 8px; border-radius:6px;
  transition:background 0.2s ease, color 0.2s ease;
}
.notif-mark-read:hover {
  background:#fff7e6;
  color:#d97706;
}
.notif-list { max-height:340px; overflow-y:auto; }
.notif-item {
  display:flex; align-items:flex-start; gap:.75rem;
  padding:.85rem 1rem; border-bottom:1px solid #f8fafc;
  cursor:pointer; transition:background .15s;
}
.notif-item:hover { background:#f8fafc; }
.notif-item.unread { background:#eff6ff; }
.notif-item.unread:hover { background:#dbeafe; }
.notif-logo {
  width:36px; height:36px; border-radius:10px;
  object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0;
}
.notif-content { flex:1; }
.notif-message { font-size:.8rem; font-weight:600; color:#0f172a; line-height:1.4; }
.notif-message span { font-weight:800; }
.notif-time { font-size:.7rem; color:#94a3b8; margin-top:2px; }
.notif-dot {
  width:8px; height:8px; border-radius:50%;
  background:#3b82f6; flex-shrink:0; margin-top:4px;
}
.notif-loading { padding:1.5rem; text-align:center; font-size:.83rem; color:#94a3b8; }
.notif-empty { padding:2rem 1rem; text-align:center; font-size:.83rem; color:#94a3b8; }
      html { background: #f8fafc; }
      .top-bar       { animation: fadeInUp 0.4s ease both; }
      .approval-tabs { animation: fadeInUp 0.5s ease both; }
      #historyGrid   { animation: fadeInUp 0.6s ease both; }
      @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

      /* ── TABS ── */
      .approval-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1.5rem; }
      .tab-btn {
        padding:7px 16px; border-radius:20px; border:2px solid #e2e8f0;
        background:white; font-size:.8rem; font-weight:700;
        font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s;
      }
      .tab-btn:hover { border-color:#d97706; color:#d97706; }
      .tab-btn.active { background:linear-gradient(135deg,#d97706,#d97706); color:white; border-color:transparent; box-shadow:0 3px 10px rgba(37,99,235,.3); }

      /* ── CARDS ── */
      #historyGrid { display:flex; flex-direction:column; gap:.85rem; }

      .history-card {
        background:white; border-radius:16px; border:1.5px solid #e2e8f0;
        box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;
        transition:box-shadow .2s, border-color .2s;
        animation:fadeInUp .35s ease both;
      }
      .history-card:hover { box-shadow:0 6px 22px rgba(0,0,0,.1); border-color:#bfdbfe; }
      .history-card.completed { border-left:4px solid #3b82f6; }
      .history-card.cancelled { border-left:4px solid #ef4444; }

      /* Header */
      .hc-header {
        display:flex; align-items:center; justify-content:space-between;
        padding:.9rem 1.1rem; border-bottom:1px solid #f1f5f9;
        gap:1rem; flex-wrap:wrap;
      }
      .hc-shop-info { display:flex; align-items:center; gap:.75rem; }
      .hc-shop-logo { width:46px; height:46px; border-radius:12px; object-fit:cover; border:1.5px solid #e2e8f0; flex-shrink:0; }
      .hc-shop-name { font-size:.9rem; font-weight:800; color:#0f172a; }
      .hc-shop-loc  { font-size:.75rem; color:#64748b; margin-top:2px; display:flex; align-items:center; gap:4px; }
      .hc-shop-loc img { opacity:.5; }
      .hc-right { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }

      .status-badge { font-size:.72rem; font-weight:700; padding:4px 11px; border-radius:20px; white-space:nowrap; }
      .status-completed { background:#dbeafe; color:#1e40af; }
      .status-cancelled { background:#fee2e2; color:#991b1b; }

      /* Favorite button */
      .btn-fav {
        width:34px; height:34px; border-radius:10px; border:1.5px solid #e2e8f0;
        background:white; display:flex; align-items:center; justify-content:center;
        cursor:pointer; transition:all .2s; flex-shrink:0; font-size:1.1rem;
      }
      .btn-fav:hover { border-color:#fbbf24; background:#fffbeb; transform:scale(1.1); }
      .btn-fav.favorited { background:#fef3c7; border-color:#f59e0b; }
      .btn-fav:disabled { opacity:.5; cursor:not-allowed; transform:none; }

      /* Review stars (display) */
      .review-stars-display { display:flex; gap:2px; }
      .star-d { font-size:.9rem; color:#d1d5db; }
      .star-d.filled { color:#f59e0b; }

      /* Body */
      .hc-body { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem; padding:.9rem 1.1rem; }
      .hc-detail { display:flex; flex-direction:column; gap:2px; }
      .hc-label  { font-size:.67rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; }
      .hc-value  { font-size:.83rem; font-weight:600; color:#374151; }
      .hc-value.service { color:#d97706; }

      /* Problem */
      .hc-problem { padding:.6rem 1.1rem .85rem; font-size:.82rem; color:#475569; line-height:1.5; border-top:1px solid #f8fafc; }
      .hc-problem strong { color:#374151; }

      /* Review snippet on card */
      .hc-review-snippet {
        margin:.5rem 1.1rem .85rem;
        background:#fffbeb; border:1px solid #fde68a;
        border-radius:10px; padding:9px 12px;
        display:flex; align-items:flex-start; gap:8px;
      }
      .hc-review-snippet .snippet-stars { display:flex; gap:2px; flex-shrink:0; margin-top:1px; }
      .snippet-star { font-size:.8rem; color:#d1d5db; }
      .snippet-star.filled { color:#f59e0b; }
      .hc-review-snippet .snippet-text { font-size:.78rem; color:#92400e; line-height:1.45; }

      /* Footer */
      .hc-footer {
        padding:.7rem 1.1rem; border-top:1px solid #f1f5f9;
        display:flex; gap:7px; flex-wrap:wrap; align-items:center;
      }
      .hc-date-label { font-size:.72rem; color:#94a3b8; margin-left:auto; }

      .action-btn {
        padding:7px 14px; border-radius:9px; font-size:.78rem;
        font-weight:700; font-family:"Outfit",sans-serif;
        cursor:pointer; border:none; transition:all .2s;
        display:flex; align-items:center; gap:5px;
      }
      .action-btn img { width:13px; height:13px; }
      .btn-view   { background:#f1f5f9; color:#475569; }
      .btn-view:hover { background:#e2e8f0; }
      .btn-rebook { background:#dbeafe; color:#1e40af; }
      .btn-rebook:hover { background:#bfdbfe; }
      .btn-review { background:linear-gradient(135deg,#f59e0b,#d97706); color:white; box-shadow:0 3px 10px rgba(245,158,11,.25); }
      .btn-review:hover { transform:translateY(-1px); box-shadow:0 5px 14px rgba(245,158,11,.35); }
      .btn-reviewed { background:#fef3c7; color:#92400e; cursor:default; }
      .action-btn:disabled { opacity:.4; cursor:not-allowed; }

      /* ── LOADING ── */
      .loading-state { text-align:center; padding:4rem 2rem; color:#94a3b8; }
      .spinner { width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#2563eb; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 14px; }
      @keyframes spin { to { transform:rotate(360deg); } }

      /* ── EMPTY ── */
      .empty-state {
        display:flex; flex-direction:column; align-items:center;
        justify-content:center; padding:4rem 2rem; text-align:center;
        animation:fadeInUp .5s ease both;
      }
      .empty-state img  { width:72px; height:72px; opacity:.35; margin-bottom:1.25rem; }
      .empty-state h3   { font-size:1.1rem; font-weight:700; color:#64748b; margin-bottom:6px; }
      .empty-state p    { font-size:.875rem; color:#94a3b8; margin-bottom:1.5rem; }
      .btn-find-shops {
        display:inline-flex; align-items:center; gap:6px; padding:.65rem 1.5rem;
        border-radius:12px; background:linear-gradient(135deg,#2563eb,#1d4ed8);
        color:white; font-weight:700; font-size:.875rem; font-family:"Outfit",sans-serif;
        text-decoration:none; box-shadow:0 4px 14px rgba(37,99,235,.3); transition:all .2s;
      }
      .btn-find-shops:hover { transform:translateY(-2px); }

      /* ── MODALS (shared) ── */
      .modal-overlay {
        position:fixed; inset:0; background:rgba(10,15,30,.75);
        backdrop-filter:blur(4px); display:flex; align-items:center;
        justify-content:center; z-index:1000; opacity:0;
        pointer-events:none; transition:opacity .3s ease; padding:20px;
      }
      .modal-overlay.visible { opacity:1; pointer-events:all; }
      .modal-box {
        background:white; border-radius:22px; padding:0;
        max-width:480px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,.28);
        transform:scale(.9) translateY(20px); opacity:0;
        transition:transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
        overflow:hidden;
      }
      .modal-overlay.visible .modal-box { transform:scale(1) translateY(0); opacity:1; }

      /* Detail modal */
      .modal-banner { padding:1.25rem 1.5rem 1rem; display:flex; align-items:center; gap:1rem; }
      .modal-shop-logo { width:52px; height:52px; border-radius:12px; object-fit:cover; border:2px solid rgba(255,255,255,.4); flex-shrink:0; }
      .modal-shop-name { font-size:1.05rem; font-weight:800; color:white; }
      .modal-booking-id { font-size:.75rem; color:rgba(255,255,255,.75); margin-top:2px; }
      .modal-body { padding:1.25rem 1.5rem 1.5rem; }
      .modal-status-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
      .modal-section-title { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:#94a3b8; margin-bottom:8px; margin-top:1rem; }
      .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
      .detail-item { background:#f8fafc; border-radius:10px; padding:9px 12px; }
      .detail-item-label { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:3px; }
      .detail-item-value { font-size:.85rem; font-weight:600; color:#0f172a; }
      .problem-box { background:#fff7ed; border:1px solid #fde68a; border-radius:10px; padding:11px 13px; margin-top:8px; font-size:.83rem; color:#374151; line-height:1.55; }
      .modal-footer { display:flex; gap:8px; padding:0 1.5rem 1.5rem; }
      .modal-btn-close { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s; }
      .modal-btn-close:hover { background:#f8fafc; }

      /* ── REVIEW MODAL ── */
      .review-modal-box {
        background:white; border-radius:22px; padding:0;
        max-width:420px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,.28);
        transform:scale(.9) translateY(20px); opacity:0;
        transition:transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
        overflow:hidden;
      }
      .modal-overlay.visible .review-modal-box { transform:scale(1) translateY(0); opacity:1; }

      .review-modal-header {
        background:linear-gradient(135deg,#f59e0b,#d97706);
        padding:1.5rem 1.5rem 1.25rem; text-align:center; position:relative;
      }
      .review-modal-header::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:20px; background:white; border-radius:20px 20px 0 0; }
      .review-modal-icon { font-size:2.5rem; margin-bottom:.5rem; }
      .review-modal-title { font-size:1.1rem; font-weight:800; color:white; }
      .review-modal-shop  { font-size:.8rem; color:rgba(255,255,255,.85); margin-top:3px; }

      .review-modal-body { padding:1.25rem 1.5rem 1.5rem; }

      /* Star rating input */
      .star-rating-input { display:flex; justify-content:center; gap:8px; margin-bottom:1.25rem; }
      .star-btn {
        font-size:2rem; cursor:pointer; transition:transform .15s, color .15s;
        color:#d1d5db; background:none; border:none; padding:0; line-height:1;
      }
      .star-btn:hover, .star-btn.active { color:#f59e0b; transform:scale(1.2); }
      .star-label { text-align:center; font-size:.8rem; font-weight:700; color:#64748b; margin-bottom:1rem; min-height:20px; }

      .review-textarea {
        width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:10px;
        font-size:13px; font-family:"Outfit",sans-serif; color:#0f172a;
        resize:vertical; min-height:90px; background:#f8fafc; transition:border-color .25s;
        box-sizing:border-box; margin-bottom:1rem;
      }
      .review-textarea:focus { outline:none; border-color:#f59e0b; background:white; box-shadow:0 0 0 3px rgba(245,158,11,.12); }

      .review-modal-actions { display:flex; gap:10px; }
      .review-btn-cancel {
        flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px;
        background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif;
        cursor:pointer; color:#64748b; transition:all .2s;
      }
      .review-btn-cancel:hover { background:#f8fafc; }
      .review-btn-submit {
        flex:1; padding:11px; border:none; border-radius:10px;
        background:linear-gradient(135deg,#f59e0b,#d97706); color:white;
        font-size:13px; font-weight:700; font-family:"Outfit",sans-serif;
        cursor:pointer; box-shadow:0 4px 14px rgba(245,158,11,.35); transition:all .2s;
      }
      .review-btn-submit:hover { transform:translateY(-1px); }
      .review-btn-submit:disabled { opacity:.6; cursor:not-allowed; transform:none; }

      /* Logout modal */
      .modal-title    { font-size:18px; font-weight:800; color:#0f172a; margin-bottom:6px; font-family:"Outfit",sans-serif; }
      .modal-subtitle { font-size:13px; color:#64748b; }
      .modal-actions  { display:flex; gap:10px; margin-top:20px; }
      .modal-btn-cancel-lg  { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s; }
      .modal-btn-cancel-lg:hover { background:#f8fafc; }
      .modal-btn-confirm { flex:1; padding:11px; border:none; border-radius:10px; color:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; transition:all .2s; }
      .modal-btn-confirm:hover { transform:translateY(-1px); opacity:.9; }

      .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:.5px; font-family:"Outfit",sans-serif; font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }

      @media (max-width:768px) {
        .hc-body { grid-template-columns:1fr 1fr; }
        .detail-grid { grid-template-columns:1fr; }
      }
      @media (max-width:480px) {
        .hc-body { grid-template-columns:1fr; }
        .approval-tabs { gap:5px; }
        .tab-btn { padding:6px 12px; font-size:.76rem; }
      }

            /* ── Sidebar backdrop (para ma-close pag click outside) ── */
      .sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 900; /* ubos sa sidebar pero taas sa content */
      }
      body.sidebar-open .sidebar-backdrop {
        display: block;
      }

      .sidebar {
        z-index: 950; /* mas taas kaysa backdrop (900) */
      }

    </style>
  </head>
  <body class="role-customer">
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
        <h2 class="brand-name">FIX IT DAVAO</h2>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section" data-role="customer">
          <a href="../shop-owner/dashboard.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/find.svg" alt="" /></span><span class="nav-text">Find Repair Shops</span></a>
          <a href="my-bookings.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/book.svg" alt="" /></span><span class="nav-text">My Bookings</span></a>
          <a href="favorites.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/favorite.svg" alt="" /></span><span class="nav-text">Favorites</span></a>
          <a href="history.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/history.svg" alt="" /></span><span class="nav-text">History</span></a>
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
  <div class="modal-box" style="max-width:380px;text-align:center;padding:32px 28px;">
    <div style="font-size:48px;margin-bottom:12px;">👋</div>
    <div class="modal-title">Logging Out?</div>
    <div class="modal-subtitle" style="margin-bottom:24px;">Are you sure you want to logout of Fix It Davao?</div>
    <div class="modal-actions" style="justify-content:center;">
      <button class="modal-btn-cancel-lg" style="flex:1;" onclick="closeLogoutModal()">Cancel</button>
      <button class="modal-btn-confirm" style="flex:1;background:linear-gradient(135deg,#ef4444,#dc2626);" onclick="window.location.href='../logout.php'">Yes, Logout</button>
    </div>
  </div>
</div>

<!-- PROFILE MODAL -->
<div class="modal-overlay" id="profileModal">
  <div class="modal-box" style="max-width:380px;padding:0;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#1e293b,#0f172a);padding:32px 24px 24px;text-align:center;position:relative;">
      <button onclick="closeProfileModal()" style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,.1);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:14px;">✕</button>
      <div style="position:relative;display:inline-block;margin:0 auto 12px;">
        <div id="profileInitials" style="width:72px;height:72px;border-radius:16px;background:linear-gradient(135deg,#ff6b35,#ef4444);color:white;font-size:1.6rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;overflow:hidden;cursor:pointer;"
          onclick="document.getElementById('picInput').click()">
        </div>
        <div onclick="document.getElementById('picInput').click()" style="position:absolute;bottom:-4px;right:-4px;width:22px;height:22px;background:#f59e0b;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #0f172a;" title="Change photo">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="white"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
        </div>
        <input type="file" id="picInput" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handlePicUpload(event)" />
      </div>
      <div style="font-size:1.1rem;font-weight:800;color:white;margin-bottom:4px;" id="profileName"></div>
      <div style="font-size:.75rem;color:#f59e0b;font-weight:700;text-transform:uppercase;letter-spacing:1px;" id="profileRole">Customer</div>
    </div>
    <div style="padding:20px 24px;">
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
          <img src="../assets/icons/email.svg" width="16" height="16" alt="" style="opacity:.5;flex-shrink:0;" />
          <div>
            <div style="font-size:.65rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Email</div>
            <div style="font-size:.82rem;font-weight:600;color:#0f172a;" id="profileEmail"><?php echo htmlspecialchars($userEmail); ?></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
          <img src="../assets/icons/users.svg" width="16" height="16" alt="" style="opacity:.5;flex-shrink:0;" />
          <div>
            <div style="font-size:.65rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Account Type</div>
            <div style="font-size:.82rem;font-weight:600;color:#0f172a;">Customer</div>
          </div>
        </div>
      </div>
      <div id="picStatus" style="display:none;margin-top:10px;padding:8px 12px;border-radius:8px;font-size:.78rem;font-weight:600;text-align:center;"></div>
      <button onclick="confirmLogout(event)" style="width:100%;margin-top:16px;padding:11px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:10px;font-size:.85rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;">
        Logout
      </button>
    </div>
  </div>
</div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="detailModal">
      <div class="modal-box">
        <div class="modal-banner" id="modalBanner"></div>
        <div class="modal-body">
          <div class="modal-status-row">
            <span id="modalStatusBadge" class="status-badge"></span>
            <span id="modalBookingId" style="font-size:.72rem;color:#94a3b8;"></span>
          </div>
          <div class="modal-section-title">Service &amp; Device</div>
          <div class="detail-grid" id="modalDetailGrid"></div>
          <div class="modal-section-title">Problem Description</div>
          <div class="problem-box" id="modalProblem"></div>
          <div class="modal-section-title">Schedule</div>
          <div class="detail-grid" id="modalScheduleGrid"></div>
        </div>
        <div class="modal-footer">
          <button class="modal-btn-close" onclick="closeDetailModal()">Close</button>
        </div>
      </div>
    </div>

    <!-- Review Modal -->
    <div class="modal-overlay" id="reviewModal">
      <div class="review-modal-box">
        <div class="review-modal-header">
          <div class="review-modal-icon">
  <img src="../assets/icons/reviews.svg" width="40" height="40" alt="" style="opacity:.9;" />
</div>
          <div class="review-modal-title">Rate Your Experience</div>
          <div class="review-modal-shop" id="reviewShopName"></div>
        </div>
        <div class="review-modal-body">
          <div class="star-rating-input" id="starRatingInput">
            <button class="star-btn" data-val="1">★</button>
            <button class="star-btn" data-val="2">★</button>
            <button class="star-btn" data-val="3">★</button>
            <button class="star-btn" data-val="4">★</button>
            <button class="star-btn" data-val="5">★</button>
          </div>
          <div class="star-label" id="starLabel">Tap a star to rate</div>
          <textarea class="review-textarea" id="reviewComment" placeholder="Share your experience (optional) — Was the service fast? Was the staff friendly?"></textarea>
          <div class="review-modal-actions">
            <button class="review-btn-cancel" onclick="closeReviewModal()">Cancel</button>
            <button class="review-btn-submit" id="reviewSubmitBtn" onclick="submitReview()">Submit Review</button>
          </div>
        </div>
      </div>
    </div>

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">History</h1></div>
        <div class="top-bar-actions">
          <div class="notif-wrapper" style="position:relative;">
  <button class="icon-btn notification-btn" id="notifBtn" onclick="toggleNotifDropdown()">
    <img src="../assets/icons/bell.svg" alt="" width="20" height="20" />
  </button>
  <span class="notif-badge" id="notifBadge" style="display:none;"></span>
  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-header">
      <span class="notif-title">Notifications</span>
      <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
    </div>
    <div class="notif-list" id="notifList">
      <div class="notif-loading">Loading...</div>
    </div>
  </div>
</div>
          <div class="user-profile" onclick="openProfileModal()" style="cursor:pointer;">
            <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" />
            <div class="user-info">
              <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
              <span class="user-role">Customer</span>
            </div>
          </div>
        </div>
      </header>

      <div class="dashboard-content">
        <div class="approval-tabs">
          <button class="tab-btn active" data-status="all">All</button>
          <button class="tab-btn" data-status="completed">Completed</button>
          <button class="tab-btn" data-status="cancelled">Cancelled</button>
        </div>

        <div class="loading-state" id="loadingState">
          <div class="spinner"></div>
          <p>Loading your history...</p>
        </div>

        <div id="historyGrid" style="display:none;"></div>

        <div class="empty-state" id="emptyState" style="display:none;">
          <img src="../assets/icons/history.svg" alt="No history" />
          <h3>No History Yet</h3>
          <p>Your completed and cancelled bookings will appear here.</p>
          <a href="../shop-owner/dashboard.php" class="btn-find-shops">
            <img src="../assets/icons/find.svg" width="14" height="14" alt="" style="filter:brightness(0) invert(1);" />
            Find Repair Shops
          </a>
        </div>
      </div>

      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script>

      // Auto-load profile pic sa top-bar avatar
(function() {
  const saved = localStorage.getItem('profilePic_<?php echo $userId; ?>');
  if (saved) {
    const topAvatar = document.querySelector('.user-avatar');
    if (topAvatar) topAvatar.src = saved;
  }
})();
      // ── Sidebar ──────────────────────────────────────────────
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", (e) => { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }
      function confirmLogout(e) {
  e.preventDefault();
  sidebar.classList.remove('active');
  document.body.classList.remove('sidebar-open');
  document.getElementById('logoutModal').classList.add('visible');
  return false;
}
      function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('visible'); }

      // ── State ────────────────────────────────────────────────
      let allHistory    = [];
      let currentFilter = 'all';

      // review modal state
      let reviewBookingId = null;
      let reviewShopId    = null;
      let reviewRating    = 0;

      const STAR_LABELS = ['', 'Poor 😞', 'Fair 😐', 'Good 😊', 'Great 😄', 'Excellent! 🤩'];

      // ── Load data ────────────────────────────────────────────
      async function loadHistory() {
        try {
          const res  = await fetch('../api/get_my_history.php');
          const data = await res.json();
          if (data.error) throw new Error(data.error);
          allHistory = data.bookings || [];
          updateTabCounts();
          renderHistory();
        } catch(e) {
          document.getElementById('loadingState').innerHTML = `<p style="color:#ef4444;">Failed to load. Please refresh.</p>`;
        }
      }

      // ── Tab counts ───────────────────────────────────────────
      function updateTabCounts() {
        const completed = allHistory.filter(b => b.status === 'completed').length;
        const cancelled = allHistory.filter(b => b.status === 'cancelled').length;
        document.querySelectorAll('.tab-btn').forEach(btn => {
          const s = btn.dataset.status;
          const map = { all: allHistory.length, completed, cancelled };
          btn.textContent = s.charAt(0).toUpperCase() + s.slice(1) + ` (${map[s] ?? 0})`;
        });
      }

      // ── Render ───────────────────────────────────────────────
      function renderHistory() {
        document.getElementById('loadingState').style.display = 'none';
        const grid  = document.getElementById('historyGrid');
        const empty = document.getElementById('emptyState');

        const filtered = currentFilter === 'all'
          ? allHistory
          : allHistory.filter(b => b.status === currentFilter);

        if (!filtered.length) {
          grid.style.display  = 'none';
          empty.style.display = 'flex';
          const msgs = {
            all:       ['No History Yet', 'Your completed and cancelled bookings will appear here.'],
            completed: ['No Completed Bookings', 'Bookings marked as completed will appear here.'],
            cancelled: ['No Cancelled Bookings', 'Bookings you or the shop cancelled will appear here.'],
          };
          document.querySelector('#emptyState h3').textContent = msgs[currentFilter][0];
          document.querySelector('#emptyState p').textContent  = msgs[currentFilter][1];
          return;
        }

        empty.style.display = 'none';
        grid.style.display  = 'flex';

        grid.innerHTML = filtered.map((b, i) => {
          const shopLogo   = b.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
          const dateStr    = fmtDate(b.booking_date);
          const timeStr    = fmtTime(b.booking_time);
          const createdStr = fmtDatetime(b.created_at);
          const isCompleted = b.status === 'completed';
          const isFav       = b.is_favorited;
          const isReviewed  = b.is_reviewed;

          // Existing review snippet
          let reviewSnippet = '';
          if (isReviewed && b.review_rating) {
            const stars = Array.from({length:5}, (_, i) =>
              `<span class="snippet-star ${i < b.review_rating ? 'filled' : ''}">★</span>`).join('');
            reviewSnippet = `
              <div class="hc-review-snippet">
                <div class="snippet-stars">${stars}</div>
                <div class="snippet-text">${b.review_comment ? esc(b.review_comment) : '<em>No comment</em>'}</div>
              </div>`;
          }

          return `
            <div class="history-card ${esc(b.status)}" style="animation-delay:${i * 0.05}s" data-status="${esc(b.status)}" id="card-${b.id}">
              <div class="hc-header">
                <div class="hc-shop-info">
                  <img src="${shopLogo}" class="hc-shop-logo" alt="${esc(b.shop_name)}"
                    onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80'" />
                  <div>
                    <div class="hc-shop-name">${esc(b.shop_name || 'Repair Shop')}</div>
                    ${b.shop_location ? `<div class="hc-shop-loc"><img src="../assets/icons/location.svg" width="13" height="13" alt="" />${esc(b.shop_location)}</div>` : ''}
                  </div>
                </div>
                <div class="hc-right">
                  <span class="status-badge status-${esc(b.status)}">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>
                 ${isCompleted ? `
<button class="btn-fav ${isFav ? 'favorited' : ''}"
  id="fav-btn-${b.id}"
  title="${isFav ? 'Remove from Favorites' : 'Add to Favorites'}"
  onclick="toggleFavorite(${b.shop_id}, ${b.id}, this)">
  <img src="../assets/icons/puso.svg" width="16" height="16" alt="" style="vertical-align:middle;" />
</button>` : ''}
              </div>
            </div>

              <div class="hc-body">
                <div class="hc-detail"><span class="hc-label">Service</span><span class="hc-value service">${esc(b.service_name||'—')}</span></div>
                <div class="hc-detail"><span class="hc-label">Device</span><span class="hc-value">${esc(b.device_type)}${b.device_brand?' · '+esc(b.device_brand):''}</span></div>
                <div class="hc-detail"><span class="hc-label">Schedule</span><span class="hc-value">${dateStr} at ${timeStr}</span></div>
              </div>

              ${b.problem_description ? `<div class="hc-problem"><strong>Problem:</strong> ${esc(b.problem_description)}</div>` : ''}

              ${reviewSnippet}

              <div class="hc-footer">
                <button class="action-btn btn-view" data-booking='${esc2(JSON.stringify(b))}' onclick="openDetailFromBtn(this)">
                  <img src="../assets/icons/view.svg" alt="" /> View Details
                </button>
                ${isCompleted ? `
                <button class="action-btn btn-rebook" onclick="window.location.href='book-shop.php?id=${b.shop_id}'">
                  <img src="../assets/icons/again.svg" alt="" /> Book Again
                </button>
                ${isReviewed
                  ? `<button class="action-btn btn-reviewed" disabled><img src="../assets/icons/bitwin.svg" width="13" height="13" alt="" style="vertical-align:middle;margin-right:4px;" /> Reviewed</button>`
                  : `<button class="action-btn btn-review" onclick="openReviewModal(${b.id}, ${b.shop_id}, '${esc(b.shop_name)}')">
   <img src="../assets/icons/rate.svg" width="13" height="13" alt="" style="vertical-align:middle;margin-right:4px;" /> Rate & Review
 </button>`
                }` : ''}
                <span class="hc-date-label">Booked ${createdStr}</span>
              </div>
            </div>`;
        }).join('');
      }

      // ── Tabs ─────────────────────────────────────────────────
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          currentFilter = this.dataset.status;
          renderHistory();
        });
      });

      // ── Toggle Favorite ──────────────────────────────────────
      async function toggleFavorite(shopId, bookingId, btn) {
        btn.disabled = true;
        try {
          const fd = new FormData();
          fd.append('shop_id', shopId);
          const res  = await fetch('toggle_favorite.php', { method:'POST', body:fd });
          const data = await res.json();
          if (data.success) {
            // Update the booking in allHistory
            const b = allHistory.find(x => x.id === bookingId);
            if (b) b.is_favorited = data.favorited;
            btn.innerHTML = `<img src="../assets/icons/puso.svg" width="16" height="16" alt="" style="vertical-align:middle;" />`;
btn.title = data.favorited ? 'Remove from Favorites' : 'Add to Favorites';
            btn.classList.toggle('favorited', data.favorited);
            // Small bounce animation
            btn.style.transform = 'scale(1.35)';
            setTimeout(() => btn.style.transform = '', 200);
          } else {
            alert('Error: ' + (data.error || 'Failed to update favorites.'));
          }
        } catch(e) {
          alert('Network error. Please try again.');
        }
        btn.disabled = false;
      }

      // ── Review Modal ─────────────────────────────────────────
      function openReviewModal(bookingId, shopId, shopName) {
        reviewBookingId = bookingId;
        reviewShopId    = shopId;
        reviewRating    = 0;

        document.getElementById('reviewShopName').textContent = shopName;
        document.getElementById('reviewComment').value = '';
        document.getElementById('starLabel').textContent = 'Tap a star to rate';
        document.querySelectorAll('.star-btn').forEach(s => s.classList.remove('active'));
        document.getElementById('reviewSubmitBtn').disabled = false;
        document.getElementById('reviewSubmitBtn').textContent = 'Submit Review';

        document.getElementById('reviewModal').classList.add('visible');
      }

      function closeReviewModal() {
        document.getElementById('reviewModal').classList.remove('visible');
        reviewBookingId = null; reviewShopId = null; reviewRating = 0;
      }

      // Star click
      document.querySelectorAll('.star-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          reviewRating = parseInt(this.dataset.val);
          document.querySelectorAll('.star-btn').forEach((s, i) => {
            s.classList.toggle('active', i < reviewRating);
          });
          document.getElementById('starLabel').textContent = STAR_LABELS[reviewRating] || '';
        });
        // Hover preview
        btn.addEventListener('mouseenter', function() {
          const val = parseInt(this.dataset.val);
          document.querySelectorAll('.star-btn').forEach((s, i) => {
            s.style.color = i < val ? '#f59e0b' : '';
          });
        });
        btn.addEventListener('mouseleave', function() {
          document.querySelectorAll('.star-btn').forEach((s, i) => {
            s.style.color = '';
          });
        });
      });

      async function submitReview() {
        if (!reviewRating) {
          document.getElementById('starLabel').textContent = '⚠️ Please select a star rating first!';
          document.getElementById('starLabel').style.color = '#ef4444';
          return;
        }
        document.getElementById('starLabel').style.color = '';

        const btn     = document.getElementById('reviewSubmitBtn');
        const comment = document.getElementById('reviewComment').value.trim();
        btn.disabled = true; btn.textContent = 'Submitting...';

        try {
          const fd = new FormData();
          fd.append('booking_id', reviewBookingId);
          fd.append('shop_id',    reviewShopId);
          fd.append('rating',     reviewRating);
          fd.append('comment',    comment);

          const res  = await fetch('submit_review.php', { method:'POST', body:fd });
          const data = await res.json();

         if (data.success) {
  closeReviewModal();
  // Reload page after short delay to reflect changes
  setTimeout(() => window.location.reload(), 500);

          } else {
            alert('Error: ' + (data.error || 'Failed to submit review.'));
            btn.disabled = false; btn.textContent = 'Submit Review';
          }
        } catch(e) {
          alert('Network error. Please try again.');
          btn.disabled = false; btn.textContent = 'Submit Review';
        }
      }

      // Close review modal on backdrop click
      document.getElementById('reviewModal').addEventListener('click', function(e) { if(e.target===this) closeReviewModal(); });

      // ── Detail Modal ─────────────────────────────────────────
      const detailModal = document.getElementById('detailModal');
      const STATUS_BG = { completed:'linear-gradient(135deg,#3b82f6,#2563eb)', cancelled:'linear-gradient(135deg,#ef4444,#dc2626)' };

      function openDetail(b) {
        const shopLogo = b.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
        document.getElementById('modalBanner').style.background = STATUS_BG[b.status] || '#64748b';
        document.getElementById('modalBanner').innerHTML = `
          <img src="${shopLogo}" class="modal-shop-logo" alt="${esc(b.shop_name)}"
            onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80'" />
          <div>
            <div class="modal-shop-name">${esc(b.shop_name||'Repair Shop')}</div>
            <div class="modal-booking-id">Booking #${b.id}</div>
          </div>`;

        document.getElementById('modalStatusBadge').className = `status-badge status-${b.status}`;
        document.getElementById('modalStatusBadge').textContent = b.status.charAt(0).toUpperCase()+b.status.slice(1);
        document.getElementById('modalBookingId').textContent = `Submitted: ${fmtDatetime(b.created_at)}`;

        document.getElementById('modalDetailGrid').innerHTML = `
          <div class="detail-item"><div class="detail-item-label">Service</div><div class="detail-item-value" style="color:#d97706">${esc(b.service_name||'—')}</div></div>
          <div class="detail-item"><div class="detail-item-label">Device Type</div><div class="detail-item-value">${esc(b.device_type||'—')}</div></div>
          <div class="detail-item" style="grid-column:span 2"><div class="detail-item-label">Brand / Model</div><div class="detail-item-value">${esc(b.device_brand||'—')}</div></div>`;

        document.getElementById('modalProblem').textContent = b.problem_description || 'No description provided.';

        document.getElementById('modalScheduleGrid').innerHTML = `
          <div class="detail-item"><div class="detail-item-label">Date</div><div class="detail-item-value">${fmtDate(b.booking_date)}</div></div>
          <div class="detail-item"><div class="detail-item-label">Time</div><div class="detail-item-value">${fmtTime(b.booking_time)}</div></div>
          ${b.shop_contact ? `<div class="detail-item" style="grid-column:span 2"><div class="detail-item-label">Shop Contact</div><div class="detail-item-value">${esc(b.shop_contact)}</div></div>` : ''}`;

        detailModal.classList.add('visible');
      }

      function closeDetailModal() { detailModal.classList.remove('visible'); }
      detailModal.addEventListener('click', e => { if(e.target===detailModal) closeDetailModal(); });

      // ── Helpers ──────────────────────────────────────────────
      function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
      function fmtDate(d) { if(!d) return '—'; return new Date(d+'T00:00:00').toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}); }
      function fmtTime(t) { if(!t) return '—'; const[h,m]=t.split(':'); const hr=parseInt(h); return `${hr%12||12}:${m} ${hr>=12?'PM':'AM'}`; }
      function fmtDatetime(dt) { if(!dt) return '—'; return new Date(dt).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}); }

      loadHistory();

      function esc2(s) {
  return s.replace(/'/g, "&#39;").replace(/"/g, "&quot;");
}

function openDetailFromBtn(btn) {
  const raw = btn.getAttribute('data-booking')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");
  openDetail(JSON.parse(raw));
}

// ── Notifications ────────────────────────────────────────
let notifOpen = false;

async function loadNotifications() {
  try {
    const res  = await fetch('../api/get_notifications.php');
    const data = await res.json();
    if (!data.success) return;

    const badge = document.getElementById('notifBadge');
    const list  = document.getElementById('notifList');

    // Update badge
    if (data.unread_count > 0) {
      badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }

    // Render list
    if (!data.notifications.length) {
      list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      return;
    }

    const STATUS_MSG = {
  confirmed:    (shop, reply) => `<span>${shop}</span> confirmed your booking! 🎉`,
  completed:    (shop, reply) => `Your repair at <span>${shop}</span> is complete! ✅`,
  cancelled:    (shop, reply) => `<span>${shop}</span> cancelled your booking.`,
  review_reply: (shop, reply) => `<span style="font-weight:800;color:#d97706;">${shop}:</span> ${reply}`,
};

list.innerHTML = data.notifications.map(n => {
  const logo = n.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(n.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
  const msg  = STATUS_MSG[n.status]
    ? STATUS_MSG[n.status](n.shop_name || 'Shop', n.reply || '')
    : `<span>${n.shop_name || 'Shop'}:</span> ${n.reply || n.status}`;
  const time = n.time ? new Date(n.time).toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' }) : '';
  const dest = n.status === 'review_reply' ? 'history.php' : 'my-bookings.php';

  const replyHtml = (n.reply && n.status !== 'review_reply')
    ? `<div style="margin-top:6px;padding:6px 10px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;font-size:.73rem;color:#374151;line-height:1.4;">
        <span style="font-weight:700;color:#d97706;">Shop replied:</span> ${n.reply}
       </div>`
    : '';

  return `
    <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="window.location.href='${dest}'">
      <img src="${logo}" class="notif-logo" alt=""
        onerror="this.src='https://ui-avatars.com/api/?name=Shop&background=f59e0b&color=fff&size=80'" />
      <div class="notif-content">
        <div class="notif-message">${msg}</div>
        ${replyHtml}
        <div class="notif-time">${time}</div>
      </div>
      ${!n.is_read ? '<div class="notif-dot"></div>' : ''}
    </div>`;
}).join('');
  } catch(e) {
    console.error('Notif error:', e);
  }
}

function toggleNotifDropdown() {
  const dropdown = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  dropdown.classList.toggle('open', notifOpen);
  if (notifOpen) {
    loadNotifications();
    markAllRead(); // ← auto mark read pag open
  }
}

async function markAllRead() {
  await fetch('../api/get_notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mark_read: true })
  });
  document.getElementById('notifBadge').style.display = 'none';
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  document.querySelectorAll('.notif-dot').forEach(el => el.remove());
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  const wrapper = document.querySelector('.notif-wrapper');
  if (wrapper && !wrapper.contains(e.target)) {
    document.getElementById('notifDropdown')?.classList.remove('open');
    notifOpen = false;
  }
});

// Load badge count on page load
loadNotifications();
    </script>
     <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
function openProfileModal() {
  const saved = localStorage.getItem('profilePic_<?php echo $userId; ?>');
  const avatarEl = document.getElementById('profileInitials');
  if (saved) {
    avatarEl.innerHTML = `<img src="${saved}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" />`;
  } else {
    avatarEl.textContent = '<?php echo strtoupper(substr($userName, 0, 2)); ?>';
    avatarEl.style.background = 'linear-gradient(135deg,#ff6b35,#ef4444)';
  }
  document.getElementById('profileName').textContent = '<?php echo htmlspecialchars($userName); ?>';
  document.getElementById('profileModal').classList.add('visible');
}
function closeProfileModal() {
  document.getElementById('profileModal').classList.remove('visible');
}
document.getElementById('profileModal').addEventListener('click', function(e) {
  if (e.target === this) closeProfileModal();
});
function handlePicUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) { showPicStatus('Image too large. Max 2MB.', false); return; }
  const reader = new FileReader();
  reader.onload = async function(e) {
    const base64 = e.target.result;
    document.getElementById('profileInitials').innerHTML =
      `<img src="${base64}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" />`;
    localStorage.setItem('profilePic_<?php echo $userId; ?>', base64);
    const topAvatar = document.querySelector('.user-avatar');
    if (topAvatar) topAvatar.src = base64;
    showPicStatus('Uploading...', null);
    try {
      const res  = await fetch('../api/update_profile_picture.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ image: base64 })
      });
      const data = await res.json();
      if (data.success) showPicStatus('✓ Profile picture updated!', true);
      else showPicStatus('❌ ' + (data.error || 'Upload failed.'), false);
    } catch(err) { showPicStatus('❌ Network error. Saved locally.', false); }
  };
  reader.readAsDataURL(file);
}
function showPicStatus(msg, ok) {
  const el = document.getElementById('picStatus');
  el.style.display    = 'block';
  el.textContent      = msg;
  el.style.background = ok === true ? '#d1fae5' : ok === false ? '#fee2e2' : '#fef3c7';
  el.style.color      = ok === true ? '#065f46' : ok === false ? '#991b1b' : '#92400e';
  if (ok !== null) setTimeout(() => { el.style.display = 'none'; }, 3000);
}
(function() {
  const saved = localStorage.getItem('profilePic_<?php echo $userId; ?>');
  if (saved) {
    const topAvatar = document.querySelector('.user-avatar');
    if (topAvatar) topAvatar.src = saved;
  }
})();
</script>
  </body>
</html>