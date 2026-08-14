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
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . ($_SESSION['role'] === 'repairshop' ? '../shop-owner/shop-information.php' : '../shop-owner/dashboard.php'));
    exit();
}

$userName  = $_SESSION['name'];
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shop Approvals - Fix It Davao Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>

       /* ── PAGE LOAD ANIMATIONS (matches my-bookings.php / admin-dashboard.php) ── */
    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .top-bar            { animation: fadeInUp 0.4s ease both; }
    .sub-status-banner  { animation: fadeInUp 0.45s ease both; }
    .plans-grid         { animation: fadeInUp 0.5s ease both; }
    .payment-section.show { animation: fadeInUp 0.35s ease; }
    .pending-info-card  { animation: fadeInUp 0.5s ease both; }
    .dash-card          { animation: fadeInUp 0.55s ease both; }

      .top-bar          { animation: fadeInUp 0.4s ease both; }
      .approval-section { animation: fadeInUp 0.5s ease both; }

      @keyframes slideOut {
        from { opacity:1; transform:translateX(0) scale(1); }
        to   { opacity:0; transform:translateX(-40px) scale(0.96); }
      }

      .approval-section {
        background: white; border-radius: 16px; padding: 1.5rem;
        border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      }

      .approval-tabs {
        display: flex; flex-direction: row; flex-wrap: nowrap;
        gap: 4px; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem;
        overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
        margin-left: -1.5rem; margin-right: -1.5rem;
        padding-left: 1.5rem; padding-right: 1.5rem; padding-bottom: 0;
      }
      .approval-tabs::-webkit-scrollbar { display: none; }

      .tab-btn {
        padding: 0.75rem 1.25rem; background: none; border: none;
        font-size: 0.88rem; font-weight: 600; color: #64748b; cursor: pointer;
        position: relative; transition: all 0.25s ease; margin-bottom: -2px;
        white-space: nowrap; flex-shrink: 0; border-radius: 8px 8px 0 0;
        font-family: var(--font-primary);
      }
      .tab-btn::after { content:""; position:absolute; bottom:0; left:0; width:100%; height:2px; background:#f59e0b; transform:scaleX(0); transition:transform 0.25s ease; }
      .tab-btn:hover { color:#0f172a; background:#f8fafc; }
      .tab-btn.active { color:#f59e0b; }
      .tab-btn.active::after { transform:scaleX(1); }

      .tab-count {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:20px; height:20px; padding:0 6px;
        background:#f1f5f9; border-radius:10px;
        font-size:0.72rem; font-weight:700; margin-left:6px; color:#64748b;
      }
      .tab-btn.active .tab-count { background:rgba(245,158,11,0.15); color:#f59e0b; }

      .tab-content { display:none; }
      .tab-content.active { display:block; animation:fadeIn 0.3s ease; }

      .approval-list { display:flex; flex-direction:column; gap:1rem; }

      .approval-card {
        background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px;
        padding:1.25rem; transition:all 0.3s ease;
      }
      .approval-card:hover { border-color:#f59e0b; box-shadow:0 4px 16px rgba(245,158,11,0.1); }

      .approval-card-header {
        display:flex; align-items:center; gap:1rem;
        margin-bottom:1.25rem; padding-bottom:1rem;
        border-bottom:1px solid #e2e8f0; flex-wrap:wrap;
      }

      .shop-logo-small { width:56px; height:56px; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0; flex-shrink:0; background:#f1f5f9; }
      .shop-logo-small img { width:100%; height:100%; object-fit:cover; }

      .shop-basic-info { flex:1; min-width:140px; }
      .shop-basic-info h3 { font-size:1.05rem; font-weight:700; color:#0f172a; margin:0 0 4px; line-height:1.3; }
      .shop-meta { color:#64748b; font-size:0.78rem; margin:2px 0; display:flex; align-items:center; gap:6px; }
      .shop-meta img { opacity:0.55; flex-shrink:0; }

      .status-badge {
        padding:0.3rem 0.85rem; border-radius:20px; font-size:0.75rem;
        font-weight:700; white-space:nowrap; flex-shrink:0;
        display:inline-flex; align-items:center; gap:5px;
      }
      .status-badge img { width:13px; height:13px; flex-shrink:0; }
      .pending-badge  { background:#fef3c7; color:#92400e; }
      .active-badge   { background:#d1fae5; color:#065f46; }
      .rejected-badge { background:#fee2e2; color:#991b1b; }

      .info-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:0.65rem; margin-bottom:1.25rem; }
      .info-item { background:white; border-radius:8px; padding:0.6rem 0.8rem; border:1px solid #e2e8f0; }
      .info-label { font-size:0.7rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
      .info-value { font-size:0.85rem; color:#0f172a; font-weight:600; }

      .approval-card-actions {
        display:flex; gap:0.65rem; flex-wrap:wrap;
        padding-top:1rem; border-top:1px solid #e2e8f0;
      }
      .btn-approve, .btn-reject {
        flex:1; min-width:110px; padding:0.65rem 1rem;
        border:none; border-radius:10px; font-weight:700; font-size:0.82rem;
        cursor:pointer; transition:all 0.25s ease; font-family:var(--font-primary);
        display:inline-flex; align-items:center; justify-content:center; gap:7px;
      }
      .btn-approve      { background:#10b981; color:white; }
      .btn-approve:hover { background:#059669; transform:translateY(-1px); }
      .btn-approve:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
      .btn-reject       { background:#ef4444; color:white; }
      .btn-reject:hover  { background:#dc2626; transform:translateY(-1px); }
      .btn-reject:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
      .btn-approve svg, .btn-reject svg { width:17px; height:17px; flex-shrink:0; }

      .search-wrapper { position:relative; margin-bottom:1rem; max-width:400px; }
      .search-icon-img { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:15px; height:15px; opacity:0.45; pointer-events:none; }
      .search-input {
        width:100%; padding:0.65rem 1rem 0.65rem 2.2rem;
        border:2px solid #e2e8f0; border-radius:10px; font-size:0.875rem;
        font-family:var(--font-primary); background:#f8fafc; color:#0f172a; transition:border-color 0.25s;
      }
      .search-input:focus { outline:none; border-color:#f59e0b; background:white; }

      .shops-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
      .shops-table { width:100%; border-collapse:collapse; min-width:540px; }
      .shops-table thead { background:#f8fafc; }
      .shops-table th { padding:0.75rem 1rem; text-align:left; font-weight:700; font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
      .shops-table td { padding:0.75rem 1rem; border-bottom:1px solid #f1f5f9; font-size:0.85rem; color:#374151; vertical-align:middle; }
      .shops-table tr:last-child td { border-bottom:none; }
      .shops-table tbody tr:hover { background:#fafafa; }

      .shop-cell { display:flex; align-items:center; gap:10px; }
      .shop-cell-avatar { width:36px; height:36px; border-radius:8px; border:1px solid #e2e8f0; flex-shrink:0; }
      .shop-cell-name { font-weight:700; color:#0f172a; font-size:0.875rem; }
      .shop-cell-email { font-size:0.75rem; color:#94a3b8; }

      .reason-cell { max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#991b1b; font-size:0.8rem; }

      .btn-small {
        padding:0.3rem 0.75rem; border:none; border-radius:6px;
        font-size:0.75rem; font-weight:600; cursor:pointer; transition:all 0.2s ease;
        font-family:var(--font-primary); white-space:nowrap; margin-right:4px;
        display:inline-flex; align-items:center; gap:5px;
      }
      .btn-reconsider { background:#dbeafe; color:#1e40af; }
      .btn-reconsider:hover { filter:brightness(0.9); }
      .btn-re-reject  { background:#fee2e2; color:#991b1b; }
      .btn-re-reject:hover { filter:brightness(0.9); }

      .loading-state { text-align:center; padding:50px 20px; color:#94a3b8; }
      .spinner-sm { width:30px; height:30px; border:3px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 12px; }
      @keyframes spin { to { transform:rotate(360deg); } }

      .empty-state { text-align:center; padding:50px 20px; color:#94a3b8; display:none; width:100%; box-sizing:border-box; }
      .empty-state h3 { font-size:1rem; font-weight:700; color:#64748b; margin-bottom:6px; }
      .empty-state p  { font-size:0.85rem; margin:0 auto; }

      .modal-overlay {
        position:fixed; inset:0; background:rgba(10,15,30,0.72);
        backdrop-filter:blur(4px); display:flex; align-items:center;
        justify-content:center; z-index:1000; opacity:0;
        pointer-events:none; transition:opacity 0.3s ease; padding:20px;
      }
      .modal-overlay.visible { opacity:1; pointer-events:all; }
      .modal-box {
        background:white; border-radius:20px; padding:32px 28px;
        max-width:420px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,0.25);
        transform:scale(0.9) translateY(20px); opacity:0;
        transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
      }
      .modal-overlay.visible .modal-box { transform:scale(1) translateY(0); opacity:1; }
      .modal-icon {
        width:56px; height:56px; background:linear-gradient(135deg,#ef4444,#dc2626);
        border-radius:50%; display:flex; align-items:center; justify-content:center;
        margin:0 auto 16px; box-shadow:0 6px 20px rgba(239,68,68,0.3);
      }
      .modal-icon img { width:26px; height:26px; filter:brightness(0) invert(1); }
      .modal-title    { font-size:18px; font-weight:800; color:#0f172a; text-align:center; margin-bottom:6px; font-family:var(--font-primary); }
      .modal-subtitle { font-size:13px; color:#64748b; text-align:center; margin-bottom:20px; }
      .modal-shop-name { font-weight:700; color:#0f172a; }
      .modal-label    { font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; display:block; }
      .modal-textarea {
        width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:10px;
        font-size:13px; font-family:var(--font-primary); color:#0f172a;
        resize:vertical; min-height:90px; background:#f8fafc;
        transition:border-color 0.25s; box-sizing:border-box;
      }
      .modal-textarea:focus { outline:none; border-color:#ef4444; background:white; box-shadow:0 0 0 3px rgba(239,68,68,0.1); }
      .modal-textarea.error { border-color:#ef4444; background:#fff5f5; }
      .modal-error { font-size:12px; color:#ef4444; margin-top:4px; display:none; }
      .modal-error.show { display:block; }
      .modal-actions { display:flex; gap:10px; margin-top:20px; }
      .modal-btn-cancel  { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:var(--font-primary); cursor:pointer; color:#64748b; transition:all 0.2s; }
      .modal-btn-cancel:hover { background:#f8fafc; }
      .modal-btn-confirm {
        flex:1; padding:11px; border:none; border-radius:10px;
        background:linear-gradient(135deg,#ef4444,#dc2626); color:white;
        font-size:13px; font-weight:700; font-family:var(--font-primary);
        cursor:pointer; box-shadow:0 4px 14px rgba(239,68,68,0.35);
        transition:all 0.2s; display:inline-flex; align-items:center; justify-content:center; gap:7px;
      }
      .modal-btn-confirm:hover { transform:translateY(-1px); }
      .modal-btn-confirm:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

      .notif-wrapper { position:relative; }
      .notification-btn { position:relative; }
      .notif-badge {
        position:absolute; top:-3px; right:-3px;
        min-width:17px; height:17px; padding:0 4px;
        background:#ef4444; color:white; border-radius:10px;
        font-size:0.65rem; font-weight:800; display:none;
        align-items:center; justify-content:center;
        font-family:var(--font-primary); border:2px solid white; line-height:1;
      }
      .notif-badge.show { display:flex; }
      .notif-dropdown {
        position:absolute; top:calc(100% + 10px); right:0;
        width:320px; background:white; border-radius:16px;
        box-shadow:0 20px 60px rgba(0,0,0,0.18); border:1px solid #e2e8f0;
        z-index:999; opacity:0; pointer-events:none;
        transform:translateY(-8px) scale(0.97);
        transition:opacity 0.22s ease, transform 0.22s ease; overflow:hidden;
      }
      .notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }
      @media (max-width: 768px) {
        .notif-dropdown {
          position:fixed !important; top:70px !important;
          left:8px !important; right:8px !important;
          width:auto !important; max-width:100% !important;
          max-height:70vh; overflow-y:auto; z-index:1200;
        }
      }
      .notif-header { padding:14px 16px 10px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
      .notif-header-title { font-size:0.88rem; font-weight:800; color:#0f172a; font-family:var(--font-primary); }
      .notif-mark-read { font-size:.72rem; font-weight:700; color:#f59e0b; background:none; border:none; cursor:pointer; font-family:"Outfit",sans-serif; padding:3px 8px; border-radius:6px; transition:background 0.2s ease, color 0.2s ease; }
      .notif-mark-read:hover { background:#fff7e6; color:#d97706; }
      .notif-list { max-height:340px; overflow-y:auto; scrollbar-width:thin; scrollbar-color:#e2e8f0 transparent; }
      .notif-list::-webkit-scrollbar { width:4px; }
      .notif-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:4px; }
      .notif-item { display:flex; align-items:flex-start; gap:10px; padding:11px 16px; border-bottom:1px solid #f8fafc; transition:background 0.15s ease; cursor:default; }
      .notif-item:last-child { border-bottom:none; }
      .notif-item:hover { background:#fafafa; }
      .notif-item.unread { background:#fffbeb; }
      .notif-item.unread:hover { background:#fef9e7; }
      .notif-dot-icon { width:34px; height:34px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; margin-top:1px; }
      .notif-content { flex:1; min-width:0; }
      .notif-title { font-size:0.8rem; font-weight:700; color:#0f172a; margin:0 0 2px; font-family:var(--font-primary); }
      .notif-name  { font-size:0.75rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .notif-time  { font-size:0.7rem; color:#94a3b8; margin-top:3px; }
      .notif-unread-dot { width:7px; height:7px; background:#f59e0b; border-radius:50%; flex-shrink:0; margin-top:6px; }
      .notif-footer { padding:10px 16px; border-top:1px solid #f1f5f9; text-align:center; }
      .notif-footer a { font-size:0.78rem; font-weight:700; color:#f59e0b; text-decoration:none; font-family:var(--font-primary); }
      .notif-footer a:hover { text-decoration:underline; }
      .notif-empty { text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.82rem; font-family:var(--font-primary); }
      .notif-loading { text-align:center; padding:24px 20px; }
      .notif-spinner { width:22px; height:22px; border:2.5px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:notifSpin 0.8s linear infinite; margin:0 auto; }
      @keyframes notifSpin { to { transform:rotate(360deg); } }

      .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:0.5px; font-family:var(--font-primary); font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }

      @media (max-width: 768px) {
        .approval-section { padding:1rem; }
        .approval-tabs { margin-left:-1rem; margin-right:-1rem; padding-left:1rem; padding-right:1rem; }
        .tab-btn { padding:0.65rem 1rem; font-size:0.82rem; }
        .approval-card { padding:1rem; }
        .info-grid { grid-template-columns:repeat(2,1fr); }
        .search-wrapper { max-width:100%; }
      }
      @media (max-width: 480px) {
        .info-grid { grid-template-columns:1fr; }
        .tab-btn { padding:0.6rem 0.85rem; font-size:0.8rem; }
        .btn-approve, .btn-reject { min-width:90px; }
      }

      .user-avatar-initials {
        width:38px; height:38px; border-radius:12px;
        background:linear-gradient(135deg,#ff6b35,#ef4444);
        color:white; font-size:0.85rem; font-weight:800;
        display:flex; align-items:center; justify-content:center;
        font-family:var(--font-primary); flex-shrink:0; letter-spacing:0.5px;
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

    </style>
  </head>
  <body class="role-admin">
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
        <h2 class="brand-name">FIX IT DAVAO</h2>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section" data-role="admin">
          <a href="admin-dashboard.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/dashboard.svg" alt="Dashboard" /></span><span class="nav-text">Dashboard</span></a>
          <a href="admin-approvals.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/approval.svg" alt="Shop Approvals" /></span><span class="nav-text">Shop Approvals</span></a>
          <a href="admin-shops.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/shop.svg" alt="All Repair Shops" /></span><span class="nav-text">All Repair Shops</span></a>
          <a href="admin-users.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/users.svg" alt="Users" /></span><span class="nav-text">Users</span></a>
          </a>
          <a href="admin-subscriptions.php" class="nav-item">
  <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscriptions" /></span>
  <span class="nav-text">Subscriptions</span>
</a>
          <a href="../developers.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/developers.svg" alt="Developers" /></span><span class="nav-text">Developers</span></a>
        </div>
      </nav>
      <div class="sidebar-footer">
        <a href="../logout.php" class="nav-item" onclick="return confirmLogout(event)"><span class="nav-icon"><img src="../assets/icons/logout.svg" alt="Logout" /></span><span class="nav-text">Logout</span></a>
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

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">Shop Approvals</h1></div>
        <div class="top-bar-actions">
          <div class="notif-wrapper">
            <button class="icon-btn notification-btn" id="notifBtn">
              <img src="../assets/icons/bell.svg" alt="Notifications" width="20" height="20" />
              <span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-header">
                <span class="notif-header-title">Notifications</span>
                <button class="notif-mark-read" id="markAllRead">Mark all read</button>
              </div>
              <div class="notif-list" id="notifList">
                <div class="notif-loading"><div class="notif-spinner"></div></div>
              </div>
              <div class="notif-footer"><a href="admin-approvals.php">View all activity →</a></div>
            </div>
          </div>
          <div class="user-profile">
            <div class="user-avatar-initials"><?php echo $userInitials; ?></div>
            <div class="user-info">
              <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
              <span class="user-role">Administrator</span>
            </div>
          </div>
        </div>
      </header>

      <div class="dashboard-content">
        <div class="approval-section">
          <div class="approval-tabs">
            <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending')">
              Pending <span class="tab-count" id="pendingCount">—</span>
            </button>
            <button class="tab-btn" data-tab="approved" onclick="switchTab('approved')">
              Approved <span class="tab-count" id="approvedCount">—</span>
            </button>
            <button class="tab-btn" data-tab="rejected" onclick="switchTab('rejected')">
              Rejected <span class="tab-count" id="rejectedCount">—</span>
            </button>
          </div>

          <!-- Pending Tab -->
          <div class="tab-content active" id="pendingTab">
            <div class="loading-state" id="pendingLoading"><div class="spinner-sm"></div><p>Loading...</p></div>
            <div class="approval-list" id="pendingList"></div>
            <div class="empty-state" id="pendingEmpty">
              <img src="../assets/icons/store.svg" alt="No pending" width="56" height="56" />
              <h3>No Pending Shops</h3>
              <p>New shop registration requests will appear here for review.</p>
            </div>
          </div>

          <!-- Approved Tab -->
          <div class="tab-content" id="approvedTab">
            <div class="search-wrapper">
              <img src="../assets/icons/look.svg" alt="" class="search-icon-img" />
              <input type="text" class="search-input" placeholder="Search approved shops..." oninput="filterTable('approvedTableBody', this.value)" />
            </div>
            <div class="shops-table-wrap">
              <table class="shops-table">
                <thead><tr><th>Shop</th><th>Email</th><th>Registered</th><th>Approved</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="approvedTableBody"></tbody>
              </table>
            </div>
            <div class="empty-state" id="approvedEmpty">
              <img src="../assets/icons/shop.svg" alt="No approved" width="56" height="56" />
              <h3>No Approved Shops</h3>
              <p>Approved shops will appear here.</p>
            </div>
          </div>

          <!-- Rejected Tab -->
          <div class="tab-content" id="rejectedTab">
            <div class="shops-table-wrap">
              <table class="shops-table">
                <thead><tr><th>Shop</th><th>Email</th><th>Registered</th><th>Rejected</th><th>Reason</th><th>Actions</th></tr></thead>
                <tbody id="rejectedTableBody"></tbody>
              </table>
            </div>
            <div class="empty-state" id="rejectedEmpty">
              <img src="../assets/icons/store.svg" alt="No rejected" width="56" height="56" />
              <h3>No Rejected Shops</h3>
              <p>Rejected shops will appear here.</p>
            </div>
          </div>
        </div>
      </div>
      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
      <div class="modal-box">
        <div class="modal-icon"><img src="../assets/icons/remove.svg" alt="Reject" /></div>
        <div class="modal-title">Reject Shop</div>
        <div class="modal-subtitle">You are about to reject <span class="modal-shop-name" id="modalShopName"></span></div>
        <label class="modal-label">Reason for rejection <span style="color:#ef4444">*</span></label>
        <textarea class="modal-textarea" id="rejectReason" placeholder="e.g. Incomplete information, duplicate registration, does not meet requirements..."></textarea>
        <div class="modal-error" id="rejectReasonError">Please provide a reason before rejecting.</div>
        <div class="modal-actions">
          <button class="modal-btn-cancel" id="rejectCancelBtn">Cancel</button>
          <button class="modal-btn-confirm" id="rejectConfirmBtn">
            <svg viewBox="0 0 24 24" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><line x1="8" y1="8" x2="16" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/></svg>
            Reject Shop
          </button>
        </div>
      </div>
    </div>

    <script>
      // ── Sidebar ──────────────────────────────────────────────
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", e => { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }

      // ── Tab switching ─────────────────────────────────────────
      function switchTab(tabName) {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        document.querySelectorAll(".tab-content").forEach(t => t.classList.remove("active"));
        document.querySelector(`[data-tab="${tabName}"]`).classList.add("active");
        document.getElementById(`${tabName}Tab`).classList.add("active");
      }

      // ── Filter table ──────────────────────────────────────────
      function filterTable(tbodyId, query) {
        const q = query.toLowerCase();
        document.querySelectorAll(`#${tbodyId} tr`).forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(q) ? "" : "none";
        });
      }

      // ── Helpers ───────────────────────────────────────────────
      function escHtml(str) {
        return String(str ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");
      }

      function fmtDate(dt) {
        if (!dt) return "—";
        return new Date(dt).toLocaleDateString("en-PH", { year:"numeric", month:"short", day:"numeric" });
      }

      function avatarUrl(name) {
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=f59e0b&color=fff&size=64`;
      }

      // ── State ─────────────────────────────────────────────────
      let pendingShopId   = null;
      let pendingShopName = "";

      // ── Load all data ─────────────────────────────────────────
      async function loadApprovals() {
        try {
          const res  = await fetch("../api/get_approvals.php");
          const data = await res.json();
          if (data.error) throw new Error(data.error);
          renderPending(data.pending   || []);
          renderApproved(data.approved || []);
          renderRejected(data.rejected || []);
          document.getElementById("pendingCount").textContent  = (data.pending  || []).length;
          document.getElementById("approvedCount").textContent = (data.approved || []).length;
          document.getElementById("rejectedCount").textContent = (data.rejected || []).length;
        } catch(e) {
          document.getElementById("pendingLoading").innerHTML = `<p style="color:#ef4444;padding:40px 20px">Failed to load. Please refresh.</p>`;
          console.error(e);
        }
      }

      // ── Render pending ────────────────────────────────────────
      function renderPending(shops) {
        const list    = document.getElementById("pendingList");
        const empty   = document.getElementById("pendingEmpty");
        const loading = document.getElementById("pendingLoading");
        loading.style.display = "none";

        if (!shops.length) {
          list.innerHTML = "";
          empty.style.cssText = "display:block;text-align:center;";
          return;
        }
        empty.style.display = "none";
        list.innerHTML = shops.map(s => `
          <div class="approval-card" data-shop-id="${s.id}">
            <div class="approval-card-header">
              <div class="shop-logo-small">
                <img src="${avatarUrl(s.name)}" alt="${escHtml(s.name)}" />
              </div>
              <div class="shop-basic-info">
                <h3>${escHtml(s.name)}</h3>
                <div class="shop-meta">
                  <img src="../assets/icons/email.svg" width="13" height="13" alt="" />
                  ${escHtml(s.email)}
                </div>
                <div class="shop-meta">
                  <img src="../assets/icons/clock.svg" width="13" height="13" alt="" />
                  Registered: ${fmtDate(s.created_at)}
                </div>
              </div>
              <span class="status-badge pending-badge">
                <img src="../assets/icons/clock.svg" alt="" /> Pending
              </span>
            </div>
            <div class="info-grid">
              <div class="info-item"><div class="info-label">Shop ID</div><div class="info-value">#${s.id}</div></div>
              <div class="info-item"><div class="info-label">Email</div><div class="info-value" style="font-size:0.8rem">${escHtml(s.email)}</div></div>
              <div class="info-item"><div class="info-label">Location</div><div class="info-value">${escHtml(s.location || "—")}</div></div>
              <div class="info-item"><div class="info-label">Contact</div><div class="info-value">${escHtml(s.contact || "—")}</div></div>
            </div>
            <div class="approval-card-actions">
              <button class="btn-approve" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="white"/><polyline points="7,12 10.5,15.5 17,9" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Approve
              </button>
              <button class="btn-reject" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="white"/><line x1="8" y1="8" x2="16" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/></svg>
                Reject
              </button>
            </div>
          </div>
        `).join("");
      }

      // ── Render approved ───────────────────────────────────────
      function renderApproved(shops) {
        const tbody = document.getElementById("approvedTableBody");
        const empty = document.getElementById("approvedEmpty");

        if (!shops.length) {
          tbody.innerHTML = "";
          empty.style.display = "block";
          return;
        }
        empty.style.display = "none";
        tbody.innerHTML = shops.map(s => `
          <tr>
            <td>
              <div class="shop-cell">
                <img src="${avatarUrl(s.name)}" class="shop-cell-avatar" alt="${escHtml(s.name)}" />
                <div>
                  <div class="shop-cell-name">${escHtml(s.name)}</div>
                  <div class="shop-cell-email">#${s.id}</div>
                </div>
              </div>
            </td>
            <td>${escHtml(s.email)}</td>
            <td>${fmtDate(s.created_at)}</td>
            <td>${fmtDate(s.approved_at)}</td>
            <td>
              <span class="status-badge active-badge">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="#065f46"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
                Approved
              </span>
            </td>
            <td>
              <button class="btn-small btn-re-reject" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="#991b1b"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>
                Reject
              </button>
            </td>
          </tr>
        `).join("");
      }

      // ── Render rejected ───────────────────────────────────────
      function renderRejected(shops) {
        const tbody = document.getElementById("rejectedTableBody");
        const empty = document.getElementById("rejectedEmpty");

        if (!shops.length) {
          tbody.innerHTML = "";
          empty.style.display = "block";
          return;
        }
        empty.style.display = "none";
        tbody.innerHTML = shops.map(s => `
          <tr>
            <td>
              <div class="shop-cell">
                <img src="${avatarUrl(s.name)}" class="shop-cell-avatar" alt="${escHtml(s.name)}" />
                <div>
                  <div class="shop-cell-name">${escHtml(s.name)}</div>
                  <div class="shop-cell-email">#${s.id}</div>
                </div>
              </div>
            </td>
            <td>${escHtml(s.email)}</td>
            <td>${fmtDate(s.created_at)}</td>
            <td>${fmtDate(s.rejected_at)}</td>
            <td><span class="reason-cell" title="${escHtml(s.rejection_reason)}">${escHtml(s.rejection_reason || "—")}</span></td>
            <td>
              <button class="btn-small btn-reconsider" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="#1e40af"/><polyline points="12,7 12,12 15,15" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
                Reconsider
              </button>
            </td>
          </tr>
        `).join("");
      }

      // ── Approve ───────────────────────────────────────────────
      async function approveShop(id, name, btn) {
        if (!confirm(`Approve "${name}"? They will gain access to their shop dashboard.`)) return;
        btn.disabled = true;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:17px;height:17px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><polyline points="7,12 10.5,15.5 17,9" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> Approving...`;
        const siblingReject = btn.nextElementSibling;
        if (siblingReject) siblingReject.disabled = true;

        try {
          const res  = await fetch("approve_shop.php", {
            method:"POST", headers:{"Content-Type":"application/json"},
            body: JSON.stringify({ id, action:"approve" })
          });
          const data = await res.json();
          if (data.success) {
            const card = document.querySelector(`[data-shop-id="${id}"]`);
            if (card) {
              card.style.animation = "slideOut 0.3s ease forwards";
              setTimeout(() => { card.remove(); loadApprovals(); }, 320);
            }
          } else {
            alert("Error: " + (data.error || "Failed to approve."));
            btn.disabled = false;
            btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:17px;height:17px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><polyline points="7,12 10.5,15.5 17,9" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> Approve`;
            if (siblingReject) siblingReject.disabled = false;
          }
        } catch(e) {
          alert("Network error. Please try again.");
          btn.disabled = false;
          btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:17px;height:17px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><polyline points="7,12 10.5,15.5 17,9" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> Approve`;
          if (siblingReject) siblingReject.disabled = false;
        }
      }

      // ── Reject Modal ──────────────────────────────────────────
      const rejectModal  = document.getElementById("rejectModal");
      const rejectReason = document.getElementById("rejectReason");
      const reasonError  = document.getElementById("rejectReasonError");

      function openRejectModal(id, name) {
        pendingShopId   = id;
        pendingShopName = name;
        document.getElementById("modalShopName").textContent = name;
        rejectReason.value = "";
        rejectReason.classList.remove("error");
        reasonError.classList.remove("show");
        rejectModal.classList.add("visible");
        setTimeout(() => rejectReason.focus(), 400);
      }

      function closeRejectModal() { rejectModal.classList.remove("visible"); pendingShopId = null; }

      document.getElementById("rejectCancelBtn").addEventListener("click", closeRejectModal);
      rejectModal.addEventListener("click", e => { if (e.target === rejectModal) closeRejectModal(); });

      document.getElementById("rejectConfirmBtn").addEventListener("click", async () => {
        const reason = rejectReason.value.trim();
        if (!reason) {
          rejectReason.classList.add("error");
          reasonError.classList.add("show");
          rejectReason.focus();
          return;
        }
        const btn = document.getElementById("rejectConfirmBtn");
        btn.disabled = true;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><line x1="8" y1="8" x2="16" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/></svg> Rejecting...`;
        try {
          const res  = await fetch("approve_shop.php", {
            method:"POST", headers:{"Content-Type":"application/json"},
            body: JSON.stringify({ id:pendingShopId, action:"reject", reason })
          });
          const data = await res.json();
          if (data.success) {
            closeRejectModal();
            const card = document.querySelector(`[data-shop-id="${pendingShopId}"]`);
            if (card) {
              card.style.animation = "slideOut 0.3s ease forwards";
              setTimeout(() => { card.remove(); loadApprovals(); }, 320);
            } else {
              loadApprovals();
            }
          } else {
            alert("Error: " + (data.error || "Failed to reject."));
          }
        } catch(e) {
          alert("Network error. Please try again.");
        }
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="white"/><line x1="8" y1="8" x2="16" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/></svg> Reject Shop`;
      });

      // ── Reconsider ────────────────────────────────────────────
      async function reconsiderShop(id, name, btn) {
        if (!confirm(`Move "${name}" back to Pending for reconsideration?`)) return;
        btn.disabled = true;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:12px;height:12px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="#1e40af"/><polyline points="12,7 12,12 15,15" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> Moving...`;
        try {
          const res  = await fetch("approve_shop.php", {
            method:"POST", headers:{"Content-Type":"application/json"},
            body: JSON.stringify({ id, action:"reconsider" })
          });
          const data = await res.json();
          if (data.success) { loadApprovals(); switchTab("pending"); }
          else alert("Error: " + (data.error || "Failed."));
        } catch(e) { alert("Network error."); }
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:12px;height:12px;flex-shrink:0"><circle cx="12" cy="12" r="10" fill="#1e40af"/><polyline points="12,7 12,12 15,15" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> Reconsider`;
      }

      loadApprovals();
      setInterval(loadApprovals, 30000);

      // ── Event delegation for all buttons ─────────────────────
      document.addEventListener('click', function(e) {
        const approveBtn    = e.target.closest('.btn-approve');
        const rejectBtn     = e.target.closest('.btn-reject');
        const reRejectBtn   = e.target.closest('.btn-re-reject');
        const reconsiderBtn = e.target.closest('.btn-reconsider');

        if (approveBtn?.dataset.id) {
          approveShop(parseInt(approveBtn.dataset.id), approveBtn.dataset.name, approveBtn);
        }
        if (rejectBtn?.dataset.id) {
          openRejectModal(parseInt(rejectBtn.dataset.id), rejectBtn.dataset.name);
        }
        if (reRejectBtn?.dataset.id) {
          openRejectModal(parseInt(reRejectBtn.dataset.id), reRejectBtn.dataset.name);
        }
        if (reconsiderBtn?.dataset.id) {
          reconsiderShop(parseInt(reconsiderBtn.dataset.id), reconsiderBtn.dataset.name, reconsiderBtn);
        }
      });

      // ── Notifications ─────────────────────────────────────────
      const notifBtn      = document.getElementById("notifBtn");
      const notifDropdown = document.getElementById("notifDropdown");
      const notifBadge    = document.getElementById("notifBadge");
      const notifList     = document.getElementById("notifList");
      let notifOpen       = false;
      let seenIds         = JSON.parse(localStorage.getItem("notifSeenIds") || "[]");

      const notifIconCfg = {
        approved: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>` },
        rejected: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>` },
        pending:  { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>` },
        user:     { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><circle cx="12" cy="9" r="3" fill="white"/><path d="M6.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/></svg>` },
      };

      const notifLabels = {
        approved: "Shop Approved", rejected: "Shop Rejected",
        pending:  "New Shop Registered", user: "New User Registered",
      };

      function notifTimeAgo(dateStr) {
        if (!dateStr) return "";
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)    return "just now";
        if (diff < 3600)  return Math.floor(diff / 60) + "m ago";
        if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
        return Math.floor(diff / 86400) + "d ago";
      }

      function buildNotifId(ev) {
        return ev.type + "_" + ev.name + "_" + (ev.time || "");
      }

      async function loadNotifications() {
        try {
          const res    = await fetch("../api/get_dashboard_stats.php");
          const data   = await res.json();
          const events = data.recentActivity || [];

          const unread = events.filter(ev => !seenIds.includes(buildNotifId(ev))).length;
          if (unread > 0) {
            notifBadge.textContent = unread > 9 ? "9+" : unread;
            notifBadge.classList.add("show");
          } else {
            notifBadge.classList.remove("show");
          }

          if (!events.length) {
            notifList.innerHTML = `<div class="notif-empty">🎉 No recent activity yet.</div>`;
            return;
          }

          notifList.innerHTML = events.map(ev => {
            const id       = buildNotifId(ev);
            const isUnread = !seenIds.includes(id);
            const cfg      = notifIconCfg[ev.type] || notifIconCfg.user;
            const label    = notifLabels[ev.type]  || "Activity";
            return `
              <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${id}">
                <div class="notif-dot-icon">${cfg.svg}</div>
                <div class="notif-content">
                  <div class="notif-title">${label}</div>
                  <div class="notif-name">${ev.name}</div>
                  <div class="notif-time">${notifTimeAgo(ev.time)}</div>
                </div>
                ${isUnread ? '<div class="notif-unread-dot"></div>' : ''}
              </div>`;
          }).join("");
        } catch(e) {
          notifList.innerHTML = `<div class="notif-empty" style="color:#ef4444">Failed to load.</div>`;
        }
      }

      notifBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        notifOpen = !notifOpen;
        notifDropdown.classList.toggle("open", notifOpen);
        if (notifOpen) {
          loadNotifications();
          setTimeout(() => { document.getElementById("markAllRead").click(); }, 300);
        }
      });

      document.addEventListener("click", (e) => {
        if (!notifBtn.closest(".notif-wrapper").contains(e.target)) {
          notifOpen = false;
          notifDropdown.classList.remove("open");
        }
      });

      document.getElementById("markAllRead").addEventListener("click", () => {
        notifList.querySelectorAll(".notif-item[data-id]").forEach(item => {
          const id = item.dataset.id;
          if (!seenIds.includes(id)) seenIds.push(id);
          item.classList.remove("unread");
          const dot = item.querySelector(".notif-unread-dot");
          if (dot) dot.remove();
        });
        localStorage.setItem("notifSeenIds", JSON.stringify(seenIds));
        notifBadge.classList.remove("show");
      });

      loadNotifications();
      setInterval(loadNotifications, 30000);

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
    </script>
     <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
  </body>
</html>