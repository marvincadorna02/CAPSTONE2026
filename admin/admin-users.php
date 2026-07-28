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
    <title>Users Management - Fix It Davao Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>
      .top-bar { animation: fadeInUp 0.4s ease both; }
      .user-stats-grid { animation: fadeInUp 0.5s ease both; }
      .users-management-section { animation: fadeInUp 0.6s ease both; }

      .user-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
      .stat-card-user { background: white; padding: 1.25rem; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; flex-direction: column; gap: 0.75rem; transition: all 0.3s ease; }
      .stat-card-user:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: #f59e0b; }
      .stat-icon-user { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; flex-shrink: 0; }
     .stat-info-user h3 { font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Rajdhani', sans-serif !important; line-height: 1; }
      .stat-info-user p  { color: #64748b; font-size: 0.78rem; margin: 0; font-weight: 500; }

      .users-management-section { background: white; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

      .user-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
      .user-tabs::-webkit-scrollbar { display: none; }
      .user-tab-btn { padding: 0.75rem 1.25rem; background: none; border: none; font-size: 0.85rem; font-weight: 600; color: #64748b; cursor: pointer; position: relative; transition: all 0.25s ease; white-space: nowrap; margin-bottom: -2px; border-radius: 8px 8px 0 0; font-family: var(--font-primary); flex-shrink: 0; }
      .user-tab-btn::after { content: ""; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #f59e0b; transform: scaleX(0); transition: transform 0.25s ease; }
      .user-tab-btn:hover { color: #0f172a; background: #f8fafc; }
      .user-tab-btn.active { color: #f59e0b; }
      .user-tab-btn.active::after { transform: scaleX(1); }

      .users-filter-bar { margin-bottom: 1.25rem; }
      .search-wrapper { position: relative; margin-bottom: 0.75rem; }
      .search-icon-img { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; opacity: 0.5; pointer-events: none; }
      .search-input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.25rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; font-family: var(--font-primary); color: #0f172a; transition: border-color 0.25s; background: #f8fafc; }
      .search-input:focus { outline: none; border-color: #f59e0b; background: white; box-shadow: 0 0 0 3px rgba(245,158,11,0.1); }
      .filter-controls-user { display: flex; gap: 0.75rem; flex-wrap: wrap; }
      .filter-select { flex: 1; min-width: 130px; padding: 0.65rem 0.9rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; background: #f8fafc; font-family: var(--font-primary); color: #0f172a; cursor: pointer; transition: border-color 0.25s; }
      .filter-select:focus { outline: none; border-color: #f59e0b; }
      .btn-export { padding: 0.65rem 1.25rem; background: #10b981; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; gap: 6px; font-family: var(--font-primary); white-space: nowrap; flex-shrink: 0; }
      .btn-export:hover { background: #059669; transform: translateY(-1px); }

      .user-tab-content { display: none; }
      .user-tab-content.active { display: block; }
      .users-table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .users-table { width: 100%; border-collapse: collapse; min-width: 700px; }
      .users-table thead { background: #f8fafc; }
      .users-table th { padding: 0.85rem 1rem; text-align: left; font-weight: 700; font-size: 0.78rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
      .users-table td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; color: #374151; vertical-align: middle; }
      .users-table tr:last-child td { border-bottom: none; }
      .users-table tbody tr:hover { background: #fafafa; }

      .user-cell { display: flex; align-items: center; gap: 10px; }
      .user-cell-avatar { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; border: 2px solid #e2e8f0; object-fit: cover; }
      .user-cell-name { font-weight: 600; color: #0f172a; font-size: 0.875rem; }
      .user-cell-id   { font-size: 0.72rem; color: #94a3b8; }

      .role-badge, .status-badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
      .customer-badge  { background: #dbeafe; color: #1e40af; }
      .shop-badge      { background: #fef3c7; color: #92400e; }
      .admin-badge     { background: #fee2e2; color: #991b1b; }
      .active-badge    { background: #d1fae5; color: #065f46; }
      .suspended-badge { background: #fee2e2; color: #991b1b; }

      .suspend-reason-tag { display: inline-block; margin-top: 3px; background: #fee2e2; color: #991b1b; font-size: 0.7rem; font-weight: 600; padding: 2px 8px; border-radius: 6px; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: help; }

      .action-btns { display: flex; gap: 6px; align-items: center; }
      .btn-icon { width: 32px; height: 32px; border: none; border-radius: 8px; background: #f1f5f9; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
      .btn-icon img { width: 16px; height: 16px; pointer-events: none; }
      .btn-icon:hover:not(:disabled) { background: #e2e8f0; transform: scale(1.1); }
      .btn-icon:disabled { opacity: 0.3; cursor: not-allowed; }
      .btn-icon.danger:hover  { background: #fee2e2; }
      .btn-icon.success:hover { background: #d1fae5; }

      .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
      .empty-state img { opacity: 0.3; margin-bottom: 1rem; }
      .empty-state h3 { font-size: 1rem; font-weight: 700; color: #64748b; margin-bottom: 6px; }
      .empty-state p  { font-size: 0.85rem; }

      .loading-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
      .spinner-sm { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #f59e0b; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
      @keyframes spin { to { transform: rotate(360deg); } }

      .pagination { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
      .page-btn { padding: 0.6rem 1.25rem; border: 2px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-weight: 600; font-size: 0.85rem; font-family: var(--font-primary); transition: all 0.25s ease; color: #374151; }
      .page-btn:hover:not(:disabled) { background: #f59e0b; color: white; border-color: #f59e0b; }
      .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
      .page-info { font-size: 0.85rem; color: #64748b; font-weight: 500; }

      .modal-overlay { position: fixed; inset: 0; background: rgba(10,15,30,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; padding: 20px; }
      .modal-overlay.visible { opacity: 1; pointer-events: all; }
      .modal-box { background: white; border-radius: 20px; padding: 32px 28px; max-width: 420px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,0.25); transform: scale(0.9) translateY(20px); transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease; opacity: 0; }
      .modal-overlay.visible .modal-box { transform: scale(1) translateY(0); opacity: 1; }
      .modal-icon { width: 56px; height: 56px; background: linear-gradient(135deg,#ef4444,#dc2626); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 6px 20px rgba(239,68,68,0.3); }
      .modal-icon img { width: 26px; height: 26px; filter: brightness(0) invert(1); }
      .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; text-align: center; margin-bottom: 6px; font-family: var(--font-primary); }
      .modal-subtitle { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 20px; }
      .modal-user-name { font-weight: 700; color: #0f172a; }
      .modal-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
      .modal-textarea { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: var(--font-primary); color: #0f172a; resize: vertical; min-height: 90px; background: #f8fafc; transition: border-color 0.25s; box-sizing: border-box; }
      .modal-textarea:focus { outline: none; border-color: #ef4444; background: white; box-shadow: 0 0 0 3px rgba(239,68,68,0.1); }
      .modal-textarea.error { border-color: #ef4444; background: #fff5f5; }
      .modal-error { font-size: 12px; color: #ef4444; margin-top: 4px; display: none; }
      .modal-error.show { display: block; }
      .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
      .modal-btn-cancel { flex: 1; padding: 11px; border: 2px solid #e2e8f0; border-radius: 10px; background: white; font-size: 13px; font-weight: 700; font-family: var(--font-primary); cursor: pointer; color: #64748b; transition: all 0.2s; }
      .modal-btn-cancel:hover { background: #f8fafc; border-color: #cbd5e1; }
      .modal-btn-confirm { flex: 1; padding: 11px; border: none; border-radius: 10px; background: linear-gradient(135deg,#ef4444,#dc2626); color: white; font-size: 13px; font-weight: 700; font-family: var(--font-primary); cursor: pointer; box-shadow: 0 4px 14px rgba(239,68,68,0.35); transition: all 0.2s; }
      .modal-btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(239,68,68,0.4); }
      .modal-btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

      /* ── NOTIFICATION BELL ── */
      .notif-wrapper { position:relative; }
      .notification-btn { position:relative; }
      .notif-badge { position:absolute; top:-3px; right:-3px; min-width:17px; height:17px; padding:0 4px; background:#ef4444; color:white; border-radius:10px; font-size:0.65rem; font-weight:800; display:none; align-items:center; justify-content:center; font-family:var(--font-primary); border:2px solid white; line-height:1; }
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
      .notif-badge.show { display:flex; }
      .notif-dropdown { position:absolute; top:calc(100% + 10px); right:0; width:320px; background:white; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.18); border:1px solid #e2e8f0; z-index:999; opacity:0; pointer-events:none; transform:translateY(-8px) scale(0.97); transition:opacity 0.22s ease, transform 0.22s ease; overflow:hidden; }
      .notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }
      .notif-header { padding:14px 16px 10px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
      .notif-header-title { font-size:0.88rem; font-weight:800; color:#0f172a; font-family:var(--font-primary); }
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

      .dashboard-footer { text-align: center; padding: 16px 24px; font-size: 11px; color: #94a3b8; letter-spacing: 0.5px; font-family: var(--font-primary); font-weight: 500; border-top: 1px solid #e2e8f0; margin-top: auto; }

      @media (max-width: 1024px) { .user-stats-grid { grid-template-columns: repeat(2, 1fr); } }
      @media (max-width: 768px) {
        .user-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
        .stat-card-user { padding: 1rem; gap: 0.5rem; } .stat-info-user h3 { font-size: 1.5rem; }
        .users-management-section { padding: 1rem; }
        .user-tabs { gap: 0; margin-left: -1rem; margin-right: -1rem; padding: 0 1rem; }
        .user-tab-btn { padding: 0.65rem 1rem; font-size: 0.8rem; }
        .filter-controls-user { flex-direction: column; gap: 0.6rem; }
        .filter-select { min-width: unset; width: 100%; } .btn-export { width: 100%; justify-content: center; }
        .users-table { min-width: 600px; }
      }

      .user-avatar-initials {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ff6b35, #ef4444);
  color: white;
  font-size: 0.85rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-primary);
  flex-shrink: 0;
  letter-spacing: 0.5px;
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
          <a href="admin-approvals.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/approval.svg" alt="Shop Approvals" /></span><span class="nav-text">Shop Approvals</span></a>
          <a href="admin-shops.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/shop.svg" alt="All Repair Shops" /></span><span class="nav-text">All Repair Shops</span></a>
          <a href="admin-users.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/users.svg" alt="Users" /></span><span class="nav-text">Users</span></a>
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
        <div class="page-header"><h1 class="current-page-title">Users Management</h1></div>
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
        <div class="user-stats-grid">
          <div class="stat-card-user"><div class="stat-icon-user"><img src="../assets/icons/people.svg" alt="Users" width="22" height="22" /></div><div class="stat-info-user"><h3 id="statTotal">—</h3><p>Total Users</p></div></div>
          <div class="stat-card-user"><div class="stat-icon-user"><img src="../assets/icons/customer.svg" alt="Customers" width="22" height="22" /></div><div class="stat-info-user"><h3 id="statCustomers">—</h3><p>Customers</p></div></div>
          <div class="stat-card-user"><div class="stat-icon-user"><img src="../assets/icons/store.svg" alt="Shop Owners" width="22" height="22" /></div><div class="stat-info-user"><h3 id="statShops">—</h3><p>Shop Owners</p></div></div>
          <div class="stat-card-user"><div class="stat-icon-user"><img src="../assets/icons/admin.svg" alt="Admins" width="22" height="22" /></div><div class="stat-info-user"><h3 id="statAdmins">1</h3><p>Admins</p></div></div>
        </div>

        <div class="users-management-section">
          <div class="user-tabs">
            <button class="user-tab-btn active" id="tab-all"       onclick="switchUserTab('all', this)">All Users</button>
            <button class="user-tab-btn"         id="tab-customers" onclick="switchUserTab('customers', this)">Customers</button>
            <button class="user-tab-btn"         id="tab-shops"     onclick="switchUserTab('shops', this)">Shop Owners</button>
            <button class="user-tab-btn"         id="tab-admins"    onclick="switchUserTab('admins', this)">Admins</button>
          </div>

          <div class="users-filter-bar">
            <div class="search-wrapper">
              <img src="../assets/icons/look.svg" alt="" class="search-icon-img" />
              <input type="text" id="searchUsers" class="search-input" placeholder="Search by name or email..." />
            </div>
            <div class="filter-controls-user">
              <select class="filter-select" id="statusFilter"><option value="all">All Status</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
              <select class="filter-select" id="sortFilter"><option value="newest">Newest First</option><option value="oldest">Oldest First</option><option value="name">Name (A–Z)</option></select>
              <button class="btn-export" id="exportBtn">
                <img src="../assets/icons/save.svg" alt="" width="14" height="14" style="filter:brightness(0) invert(1)" /> Export CSV
              </button>
            </div>
          </div>

          <div class="user-tab-content active" id="allTab">
            <div class="users-table-container">
              <div class="loading-state" id="loadingState"><div class="spinner-sm"></div><p>Loading users...</p></div>
              <table class="users-table" id="usersTable" style="display:none;">
                <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Join Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="usersTableBody"></tbody>
              </table>
              <div class="empty-state" id="emptyState" style="display:none;">
                <img src="../assets/icons/users.svg" alt="No users" width="56" height="56" />
                <h3>No Users Found</h3><p>Registered users will appear here once they sign up.</p>
              </div>
            </div>
            <div class="pagination">
              <button class="page-btn" id="prevBtn" disabled>← Previous</button>
              <span class="page-info" id="pageInfo">Page 1 of 1</span>
              <button class="page-btn" id="nextBtn" disabled>Next →</button>
            </div>
          </div>
        </div>
      </div>
      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <!-- Suspend Modal -->
    <div class="modal-overlay" id="suspendModal">
      <div class="modal-box">
        <div class="modal-icon"><img src="../assets/icons/suspend.svg" alt="Suspend" /></div>
        <div class="modal-title">Suspend User</div>
        <div class="modal-subtitle">You are about to suspend <span class="modal-user-name" id="modalUserName"></span></div>
        <label class="modal-label">Reason for suspension <span style="color:#ef4444">*</span></label>
        <textarea class="modal-textarea" id="suspendReason" placeholder="e.g. Violates terms of service, suspicious activity..."></textarea>
        <div class="modal-error" id="reasonError">Please provide a reason before suspending.</div>
        <div class="modal-actions">
          <button class="modal-btn-cancel" id="modalCancelBtn">Cancel</button>
          <button class="modal-btn-confirm" id="modalConfirmBtn">Suspend User</button>
        </div>
      </div>
    </div>

    <script>
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", (e) => { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }

      let allUsers = [], filteredUsers = [], currentTab = "all", currentPage = 1;
      const rowsPerPage = 10;
      let pendingSuspendId = null;

      async function loadUsers() {
        try {
          const res = await fetch("../api/get_users.php");
          const data = await res.json();
          allUsers = data; updateStats(data); applyFilters();
        } catch(e) {
          document.getElementById("loadingState").innerHTML = '<p style="color:#ef4444;">Failed to load users. Please refresh.</p>';
        }
      }

      function animateCount(el, target, duration = 900) {
  const start = parseInt(el.textContent) || 0;
  if (start === target) return;
  const range = target - start;
  const startTime = performance.now();
  function update(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(start + range * eased);
    if (progress < 1) requestAnimationFrame(update);
  }
  requestAnimationFrame(update);
}

function updateStats(users) {
  const customers = users.filter(u => u.role === "customer").length;
  const shops     = users.filter(u => u.role === "repairshop").length;
  animateCount(document.getElementById("statTotal"),     users.length + 1, 900);
  animateCount(document.getElementById("statCustomers"), customers,         900);
  animateCount(document.getElementById("statShops"),     shops,             900);
  document.getElementById("tab-all").textContent       = `All Users (${users.length + 1})`;
  document.getElementById("tab-customers").textContent = `Customers (${customers})`;
  document.getElementById("tab-shops").textContent     = `Shop Owners (${shops})`;
  document.getElementById("tab-admins").textContent    = `Admins (1)`;
}

      function switchUserTab(tabName, btn) {
        document.querySelectorAll(".user-tab-btn").forEach(b => b.classList.remove("active"));
        btn.classList.add("active"); currentTab = tabName; currentPage = 1; applyFilters();
      }

      function applyFilters() {
        const search = document.getElementById("searchUsers").value.toLowerCase();
        const status = document.getElementById("statusFilter").value;
        const sort   = document.getElementById("sortFilter").value;
        const adminEntry = { id:0, name:"Admin User", email:"admin@fixitdavao.com", role:"admin", created_at:"2026-01-01 00:00:00", status:"active", suspend_reason:null, logo_url:null };
        let base = [adminEntry, ...allUsers];
        if (currentTab === "customers")   base = base.filter(u => u.role === "customer");
        else if (currentTab === "shops")  base = base.filter(u => u.role === "repairshop");
        else if (currentTab === "admins") base = base.filter(u => u.role === "admin");
        if (search) base = base.filter(u => u.name.toLowerCase().includes(search) || u.email.toLowerCase().includes(search));
        if (status !== "all") base = base.filter(u => (u.status||"active") === status);
        if (sort === "oldest")    base.sort((a,b) => new Date(a.created_at)-new Date(b.created_at));
        else if (sort === "name") base.sort((a,b) => a.name.localeCompare(b.name));
        else                      base.sort((a,b) => new Date(b.created_at)-new Date(a.created_at));
        filteredUsers = base; renderTable();
      }

      // ── Helper: profile pic (any role) > shop logo > colored initials ──
      function userAvatarUrl(role, name, logoUrl, profilePic) {   // ← gidungag ang 4th param
      if (profilePic) return profilePic;
      if (role === "repairshop" && logoUrl) return logoUrl;
      const color = role === "admin" ? "ef4444" : role === "repairshop" ? "f59e0b" : "3b82f6";
      return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=${color}&color=fff&size=64`;
      }

      // ── Helper: 3 months = inactive threshold ── ← DUNGAG DIRI
      function isInactive(lastLogin) {
      if (!lastLogin) return true;
      const monthsAgo = (Date.now() - new Date(lastLogin)) / (1000 * 60 * 60 * 24 * 30);
      return monthsAgo >= 3;
      }

      function renderTable() {
        const tbody = document.getElementById("usersTableBody");
        const table = document.getElementById("usersTable");
        const empty = document.getElementById("emptyState");
        const loading = document.getElementById("loadingState");
        loading.style.display = "none";
        if (!filteredUsers.length) { table.style.display="none"; empty.style.display="block"; updatePagination(0); return; }
        table.style.display = ""; empty.style.display = "none";
        const start = (currentPage-1)*rowsPerPage;
        const page  = filteredUsers.slice(start, start+rowsPerPage);
        tbody.innerHTML = page.map(u => {
          const avatar = userAvatarUrl(u.role, u.name, u.logo_url, u.profile_picture); // ← added profile_picture
          const joinDate    = new Date(u.created_at).toLocaleDateString("en-PH",{year:"numeric",month:"short",day:"numeric"});
          const roleBadge   = u.role==="admin"?"admin-badge":u.role==="repairshop"?"shop-badge":"customer-badge";
          const roleLabel   = u.role==="repairshop"?"Shop Owner":u.role.charAt(0).toUpperCase()+u.role.slice(1);
          const isSuspended = (u.status||"active")==="suspended";
          const isAdmin     = u.role==="admin";
          const reasonTag   = isSuspended&&u.suspend_reason ? `<div><span class="suspend-reason-tag" title="${escHtml(u.suspend_reason)}">⚠️ ${escHtml(u.suspend_reason)}</span></div>` : "";
          return `<tr>
            <td><div class="user-cell"><img src="${avatar}" alt="${escHtml(u.name)}" class="user-cell-avatar" /><div><div class="user-cell-name">${escHtml(u.name)}</div><div class="user-cell-id">ID #${u.id}</div></div></div></td>
            <td>${escHtml(u.email)}</td>
            <td><span class="role-badge ${roleBadge}">${roleLabel}</span></td>
            <td>${joinDate}</td>
            <td><span class="status-badge ${isSuspended?'suspended-badge':'active-badge'}">${isSuspended?'Suspended':'Active'}</span>${reasonTag}</td>
            <td><div class="action-btns">
              <button class="btn-icon" title="View" onclick="viewUser(${u.id})"><img src="../assets/icons/view.svg" alt="View" /></button>
              ${isSuspended
                ? `<button class="btn-icon success" title="Reactivate" onclick="reactivateUser(${u.id},this)" ${isAdmin?'disabled':''}><img src="../assets/icons/view.svg" alt="Reactivate" /></button>`
                : `<button class="btn-icon danger" title="Suspend" onclick="openSuspendModal(${u.id},'${escHtml(u.name)}')" ${isAdmin?'disabled':''}><img src="../assets/icons/suspend.svg" alt="Suspend" /></button>`
              }
            </div></td>
          </tr>`;
        }).join("");
        updatePagination(filteredUsers.length);
      }

      function updatePagination(total) {
        const totalPages = Math.max(1, Math.ceil(total/rowsPerPage));
        document.getElementById("pageInfo").textContent = `Page ${currentPage} of ${totalPages}`;
        document.getElementById("prevBtn").disabled = currentPage <= 1;
        document.getElementById("nextBtn").disabled = currentPage >= totalPages;
      }
      document.getElementById("prevBtn").addEventListener("click", () => { currentPage--; renderTable(); });
      document.getElementById("nextBtn").addEventListener("click", () => { currentPage++; renderTable(); });
      document.getElementById("searchUsers").addEventListener("input",  () => { currentPage=1; applyFilters(); });
      document.getElementById("statusFilter").addEventListener("change", () => { currentPage=1; applyFilters(); });
      document.getElementById("sortFilter").addEventListener("change",   () => { currentPage=1; applyFilters(); });

      document.getElementById("exportBtn").addEventListener("click", () => {
        if (!filteredUsers.length) { alert("No users to export."); return; }
        const rows = [["ID","Name","Email","Role","Join Date","Status","Suspend Reason"]];
        filteredUsers.forEach(u => rows.push([u.id,u.name,u.email,u.role,new Date(u.created_at).toLocaleDateString("en-PH"),u.status||"active",u.suspend_reason||""]));
        const csv  = rows.map(r => r.map(v => `"${v}"`).join(",")).join("\n");
        const blob = new Blob([csv],{type:"text/csv"});
        const a    = document.createElement("a"); a.href=URL.createObjectURL(blob); a.download="fixitdavao_users.csv"; a.click();
      });

      const suspendModal  = document.getElementById("suspendModal");
      const suspendReason = document.getElementById("suspendReason");
      const reasonError   = document.getElementById("reasonError");

      function openSuspendModal(id, name) {
        pendingSuspendId = id; document.getElementById("modalUserName").textContent = name;
        suspendReason.value=""; suspendReason.classList.remove("error"); reasonError.classList.remove("show");
        suspendModal.classList.add("visible"); setTimeout(()=>suspendReason.focus(),400);
      }
      function closeSuspendModal() { suspendModal.classList.remove("visible"); pendingSuspendId=null; }
      document.getElementById("modalCancelBtn").addEventListener("click", closeSuspendModal);
      suspendModal.addEventListener("click", (e) => { if(e.target===suspendModal) closeSuspendModal(); });
      document.getElementById("modalConfirmBtn").addEventListener("click", async () => {
        const reason = suspendReason.value.trim();
        if (!reason) { suspendReason.classList.add("error"); reasonError.classList.add("show"); suspendReason.focus(); return; }
        const btn = document.getElementById("modalConfirmBtn");
        btn.disabled=true; btn.textContent="Suspending...";
        try {
          const res = await fetch("suspend_user.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:pendingSuspendId,reason,action:"suspend"})});
          const data = await res.json();
          if (data.success) { closeSuspendModal(); loadUsers(); }
          else alert("Error: "+(data.error||"Failed to suspend."));
        } catch(e) { alert("Network error. Please try again."); }
        btn.disabled=false; btn.textContent="Suspend User";
      });

      async function reactivateUser(id, btn) {
        if (!confirm("Reactivate this user? They will regain full access.")) return;
        btn.disabled=true;
        try {
          const res = await fetch("suspend_user.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id,action:"reactivate"})});
          const data = await res.json();
          if (data.success) loadUsers();
          else alert("Error: "+(data.error||"Failed to reactivate."));
        } catch(e) { alert("Network error. Please try again."); }
        btn.disabled=false;
      }

      function viewUser(id) { alert(`View user ID: ${id}`); }
      function editUser(id) { alert(`Edit user ID: ${id}`); }
      function escHtml(str) { return String(str).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;"); }

      loadUsers();
      setInterval(loadUsers, 30000);

      // ── NOTIFICATION BELL ─────────────────────────────────────
      const notifBtn      = document.getElementById("notifBtn");
      const notifDropdown = document.getElementById("notifDropdown");
      const notifBadge    = document.getElementById("notifBadge");
      const notifList     = document.getElementById("notifList");
      let notifOpen = false;
      let seenIds   = JSON.parse(localStorage.getItem("notifSeenIds") || "[]");
      const notifIconCfg = {
        approved: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>` },
        rejected: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>` },
        pending:  { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>` },
        user:     { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><circle cx="12" cy="9" r="3" fill="white"/><path d="M6.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/></svg>` },
      };
      const notifLabels = { approved:"Shop Approved", rejected:"Shop Rejected", pending:"New Shop Registered", user:"New User Registered" };
      function notifTimeAgo(d) {
        if (!d) return ""; const s=Math.floor((Date.now()-new Date(d))/1000);
        if (s<60) return "just now"; if (s<3600) return Math.floor(s/60)+"m ago";
        if (s<86400) return Math.floor(s/3600)+"h ago"; return Math.floor(s/86400)+"d ago";
      }
      function buildNotifId(ev) { return ev.type+"_"+ev.name+"_"+(ev.time||""); }
      async function loadNotifications() {
        try {
          const res=await fetch("../api/get_dashboard_stats.php"); const data=await res.json();
          const events=data.recentActivity||[];
          const unread=events.filter(ev=>!seenIds.includes(buildNotifId(ev))).length;
          if (unread>0){notifBadge.textContent=unread>9?"9+":unread;notifBadge.classList.add("show");}
          else notifBadge.classList.remove("show");
          if (!events.length){notifList.innerHTML=`<div class="notif-empty">No recent activity yet.</div>`;return;}
          notifList.innerHTML=events.map(ev=>{
            const id=buildNotifId(ev);const isUnread=!seenIds.includes(id);
            const cfg=notifIconCfg[ev.type]||notifIconCfg.user;const label=notifLabels[ev.type]||"Activity";
            return `<div class="notif-item ${isUnread?"unread":""}" data-id="${id}">
              <div class="notif-dot-icon">${cfg.svg}</div>
              <div class="notif-content"><div class="notif-title">${label}</div><div class="notif-name">${ev.name}</div><div class="notif-time">${notifTimeAgo(ev.time)}</div></div>
              ${isUnread?'<div class="notif-unread-dot"></div>':""}</div>`;
          }).join("");
        } catch(e){notifList.innerHTML=`<div class="notif-empty" style="color:#ef4444">Failed to load.</div>`;}
      }
      notifBtn.addEventListener("click", e => {
  e.stopPropagation();
  notifOpen = !notifOpen;
  notifDropdown.classList.toggle("open", notifOpen);
  if (notifOpen) {
    loadNotifications();
    setTimeout(() => {
      document.getElementById("markAllRead").click();
    }, 300);
  }
});
      document.addEventListener("click",e=>{if(notifBtn&&!notifBtn.closest(".notif-wrapper").contains(e.target)){notifOpen=false;notifDropdown.classList.remove("open");}});
      document.getElementById("markAllRead").addEventListener("click",()=>{
        notifList.querySelectorAll(".notif-item[data-id]").forEach(item=>{
          if(!seenIds.includes(item.dataset.id))seenIds.push(item.dataset.id);
          item.classList.remove("unread");const dot=item.querySelector(".notif-unread-dot");if(dot)dot.remove();
        });
        localStorage.setItem("notifSeenIds",JSON.stringify(seenIds));notifBadge.classList.remove("show");
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