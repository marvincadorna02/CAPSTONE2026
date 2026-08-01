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
if ($_SESSION['role'] !== 'admin') { header("../shop-owner/dashboard.php"); exit(); }

$userName  = $_SESSION['name'];
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@600;700&family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
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

      /* ── ANIMATIONS ── */
      @keyframes fadeInUp { from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)} }
      @keyframes countUp  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
      @keyframes pulse    { 0%,100%{opacity:1}50%{opacity:0.5} }

      /* ── STATS GRID ── */
      .stats-grid-admin {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.75rem;
        animation: fadeInUp 0.4s ease both;
      }
      .stat-card {
        background: white; border-radius: 16px; padding: 1.4rem 1.5rem;
        display: flex; align-items: center; gap: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        border: 1px solid #f1f5f9;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        position: relative; overflow: hidden;
      }
      .stat-card::after {
        content:""; position:absolute; top:0; left:0; width:4px; height:100%;
        border-radius:4px 0 0 4px;
      }
      .stat-card:hover { transform:translateY(-4px); box-shadow:0 10px 28px rgba(0,0,0,0.11); }
      .stat-primary::after { background:#3b82f6; }
      .stat-warning::after { background:#f59e0b; }
      .stat-success::after { background:#10b981; }
      .stat-danger::after  { background:#ef4444; }
      .stat-info::after    { background:#8b5cf6; }

      .stat-icon {
        width:54px; height:54px; border-radius:14px;
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
      }
      .stat-primary .stat-icon { background:#dbeafe; }
      .stat-warning .stat-icon { background:#fef3c7; }
      .stat-success .stat-icon { background:#d1fae5; }
      .stat-danger  .stat-icon { background:#fee2e2; }
      .stat-info    .stat-icon { background:#ede9fe; }

      .stat-value {
    font-size: 2rem; font-weight: 800; color: #0f172a;
    margin: 0 0 2px; line-height: 1;
    font-family: 'Rajdhani', sans-serif !important;
}
      .stat-label { color:#64748b; font-size:0.82rem; margin:0; font-weight:500; }
      .stat-badge {
        position:absolute; top:12px; right:14px;
        font-size:0.68rem; font-weight:700; padding:3px 8px;
        border-radius:20px;
      }
      .stat-badge.up   { background:#d1fae5; color:#065f46; }
      .stat-badge.warn { background:#fef3c7; color:#92400e; }
      .stat-badge.red  { background:#fee2e2; color:#991b1b; }

      /* ── TWO-COLUMN LAYOUT ── */
     .dashboard-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  margin-bottom: 1.75rem;
  animation: fadeInUp 0.4s ease both;
  align-items: start; /* ← add this */
}

      /* ── CARDS ── */
      .dash-card {
        background: white; border-radius: 16px; padding: 1.4rem 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.07); border:1px solid #f1f5f9;
        
      }
      .dash-card-title {
        font-size:0.95rem; font-weight:700; color:#0f172a;
        margin:0 0 1.1rem; display:flex; align-items:center; gap:8px;
      }
      .dash-card-title span { font-size:1rem; }

      /* ── CHART ── */
      .chart-wrap { height: 200px; position: relative; }
      canvas#trendChart { width:100%!important; height:100%!important; }

      /* ── QUICK ACTIONS ── */
      .quick-actions-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
        animation: fadeInUp 0.5s ease 0.15s both;
      }
      .quick-action-card {
        background: white; border-radius: 14px; padding: 1.1rem;
        text-align: center; text-decoration: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07); border:1px solid #f1f5f9;
        transition: all 0.25s ease; display:block;
      }
      .quick-action-card:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.1); border-color:#e2e8f0; }
      .action-icon { margin-bottom:0.65rem; }
      .quick-action-card h3 { font-size:0.88rem; font-weight:700; color:#0f172a; margin:0 0 4px; }
      .quick-action-card p  { color:#64748b; font-size:0.75rem; margin:0; }

      /* ── ACTIVITY FEED ── */
      .activity-list { display:flex; flex-direction:column; gap:0; }
      .activity-item {
        display:flex; align-items:flex-start; gap:0.9rem;
        padding:0.8rem 0; border-bottom:1px solid #f8fafc;
      }
      .activity-item:last-child { border-bottom:none; padding-bottom:0; }
      .activity-dot {
        width:34px; height:34px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-size:0.85rem; margin-top:1px;
      }
      .dot-approved { background:transparent; }
      .dot-rejected { background:transparent; }
      .dot-pending  { background:transparent; }
      .dot-user     { background:transparent; }

      .activity-content { flex:1; min-width:0; }
      .activity-label { font-size:0.82rem; font-weight:700; color:#0f172a; margin:0 0 2px; }
      .activity-name  { font-size:0.78rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .activity-time  { font-size:0.72rem; color:#94a3b8; margin-top:2px; }

      /* ── BOTTOM ROW ── */
      .bottom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.75rem;
        animation: fadeInUp 0.5s ease 0.2s both;
      }
      .summary-stat {
        background: white; border-radius: 16px; padding:1.3rem 1.5rem;
        box-shadow:0 2px 10px rgba(0,0,0,0.07); border:1px solid #f1f5f9;
        text-align:center;
      }
      .summary-stat .big { font-size:2.2rem; font-weight:800; color:#0f172a; margin:0 0 4px; font-family:var(--font-primary); }
      .summary-stat .lbl { font-size:0.82rem; color:#64748b; font-weight:500; }
      .summary-stat .bar-bg { height:6px; background:#f1f5f9; border-radius:4px; margin-top:10px; overflow:hidden; }
      .summary-stat .bar-fg { height:100%; border-radius:4px; transition:width 1s ease; }

      /* ── LOADING SKELETON ── */
      .skeleton { background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:8px; }
      @keyframes shimmer { 0%{background-position:200% 0}100%{background-position:-200% 0} }

      /* ── EMPTY STATE ── */
      .empty-activity { text-align:center; padding:30px 20px; color:#94a3b8; }
      .empty-activity p { font-size:0.85rem; margin:8px 0 0; }

      /* ── NOTIFICATION BELL ── */
      .notif-wrapper { position:relative; }

      .notification-btn { position:relative; }
      .notif-badge {
        position:absolute; top:-3px; right:-3px;
        min-width:17px; height:17px; padding:0 4px;
        background:#ef4444; color:white; border-radius:10px;
        font-size:0.65rem; font-weight:800; display:none;
        align-items:center; justify-content:center;
        font-family:var(--font-primary); border:2px solid white;
        line-height:1;
      }
      .notif-badge.show { display:flex; }

      .notif-dropdown {
        position:absolute; top:calc(100% + 10px); right:0;
        width:320px; background:white; border-radius:16px;
        box-shadow:0 20px 60px rgba(0,0,0,0.18); border:1px solid #e2e8f0;
        z-index:999; opacity:0; pointer-events:none;
        transform:translateY(-8px) scale(0.97);
        transition:opacity 0.22s ease, transform 0.22s ease;
        overflow:hidden;
      }
      .notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }
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
        padding:14px 16px 10px; border-bottom:1px solid #f1f5f9;
        display:flex; align-items:center; justify-content:space-between;
      }
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

      .notif-item {
        display:flex; align-items:flex-start; gap:10px;
        padding:11px 16px; border-bottom:1px solid #f8fafc;
        transition:background 0.15s ease; cursor:default;
      }
      .notif-item:last-child { border-bottom:none; }
      .notif-item:hover { background:#fafafa; }
      .notif-item.unread { background:#fffbeb; }
      .notif-item.unread:hover { background:#fef9e7; }

      .notif-dot-icon {
        width:34px; height:34px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center; margin-top:1px;
      }
      .notif-content { flex:1; min-width:0; }
      .notif-title { font-size:0.8rem; font-weight:700; color:#0f172a; margin:0 0 2px; font-family:var(--font-primary); }
      .notif-name  { font-size:0.75rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .notif-time  { font-size:0.7rem; color:#94a3b8; margin-top:3px; }
      .notif-unread-dot {
        width:7px; height:7px; background:#f59e0b; border-radius:50%;
        flex-shrink:0; margin-top:6px;
      }

      .notif-footer {
        padding:10px 16px; border-top:1px solid #f1f5f9; text-align:center;
      }
      .notif-footer a {
        font-size:0.78rem; font-weight:700; color:#f59e0b;
        text-decoration:none; font-family:var(--font-primary);
      }
      .notif-footer a:hover { text-decoration:underline; }

      .notif-empty { text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.82rem; font-family:var(--font-primary); }
      .notif-loading { text-align:center; padding:24px 20px; }
      .notif-spinner { width:22px; height:22px; border:2.5px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto; }
      @keyframes spin { to { transform:rotate(360deg); } }

      @media(max-width:1024px) {
        .dashboard-grid { grid-template-columns: 1fr; }
      }
      @media(max-width:640px) {
        .quick-actions-grid { grid-template-columns: 1fr 1fr; }
        .stats-grid-admin   { grid-template-columns: 1fr 1fr; }
        .stat-value         { font-size:1.6rem; }
      }
      @media(max-width:400px) {
        .stats-grid-admin   { grid-template-columns: 1fr; }
      }

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
.modal-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 6px; font-family: var(--font-primary); }
.modal-subtitle { font-size: 13px; color: #64748b; }
.modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: center; }
.modal-btn-cancel {
  flex: 1; padding: 11px; border: 2px solid #e2e8f0; border-radius: 10px;
  background: white; font-size: 13px; font-weight: 700;
  font-family: var(--font-primary); cursor: pointer; color: #64748b; transition: all 0.2s;
}
.modal-btn-cancel:hover { background: #f8fafc; }
.modal-btn-confirm {
  flex: 1; padding: 11px; border: none; border-radius: 10px; color: white;
  font-size: 13px; font-weight: 700; font-family: var(--font-primary);
  cursor: pointer; transition: all 0.2s;
}
.modal-btn-confirm:hover { transform: translateY(-1px); opacity: 0.9; }

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

@media (max-width: 768px) {
  /* Stack the two charts vertically */
  .dash-card > div[style*="grid-template-columns:1fr 1fr"] {
    display: flex !important;
    flex-direction: column !important;
    gap: 1.25rem !important;
  }

  /* Fix chart heights on mobile */
  .chart-wrap {
    height: 160px !important;
  }

  /* Timeline chart */
  .dash-card > div[style*="height:180px"] {
    height: 150px !important;
  }

  /* Dashboard grid stack */
  .dashboard-grid {
    grid-template-columns: 1fr !important;
  }

  /* Stats grid 2 columns on mobile */
  .stats-grid-admin {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Quick actions 2 columns */
  .quick-actions-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Bottom summary grid */
  .bottom-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Prevent horizontal scroll on charts */
  canvas {
    max-width: 100% !important;
  }

  /* Dash card padding smaller */
  .dash-card {
    padding: 1rem !important;
  }

  /* Chart summary row wrap */
  .dash-card > div[style*="display:flex;gap:1.5rem"] {
    gap: 1rem !important;
    flex-wrap: wrap !important;
  }
}

@media (max-width: 480px) {
  .stats-grid-admin {
    grid-template-columns: repeat(2, 1fr) !important;
  }
  .bottom-grid {
    grid-template-columns: 1fr !important;
  }
  .stat-value {
    font-size: 1.4rem !important;
  }
}

@media (max-width: 480px) {
  .stats-grid-admin {
    grid-template-columns: repeat(2, 1fr) !important;
  }
  .bottom-grid {
    grid-template-columns: 1fr !important;
  }
  .stat-value {
    font-size: 1.4rem !important;
  }
}

/* ── REGISTRATIONS SECTION ── */
.reg-summary-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.1rem; margin-bottom: 1.5rem; animation: fadeInUp 0.5s ease both;
}
.reg-summary-card {
  background: white; border-radius: 16px; padding: 1.2rem 1.4rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.07); border: 1px solid #f1f5f9;
  display: flex; align-items: center; gap: 0.9rem;
}
.reg-summary-icon {
  width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
}
.reg-icon-customer { background: #dbeafe; }
.reg-icon-shop      { background: #fef3c7; }
.reg-icon-growth-c   { background: #ede9fe; }
.reg-icon-growth-s   { background: #dcfce7; }
.reg-summary-val   { font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1; font-family: 'Rajdhani', sans-serif; }
.reg-summary-lbl   { font-size: 0.76rem; color: #64748b; font-weight: 500; margin-top: 2px; }

.reg-controls {
  display: flex; gap: 10px; margin-bottom: 1rem; flex-wrap: wrap;
  align-items: center; justify-content: space-between;
}
.reg-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.reg-tab-btn {
  padding: 6px 14px; border-radius: 20px; border: 2px solid #e2e8f0;
  background: white; font-size: 0.78rem; font-weight: 700; color: #64748b;
  cursor: pointer; transition: all 0.2s; font-family: 'Outfit', sans-serif;
}
.reg-tab-btn:hover { border-color: #f59e0b; color: #f59e0b; }
.reg-tab-btn.active { background: #f59e0b; border-color: #f59e0b; color: white; }
.reg-search {
  padding: 8px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
  font-size: 0.82rem; font-family: 'Outfit', sans-serif; outline: none;
  min-width: 220px; transition: border-color 0.2s;
}
.reg-search:focus { border-color: #f59e0b; }

.reg-table-wrap { background: white; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; animation: fadeInUp 0.55s ease both; }
.reg-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.reg-table th { padding: 10px 16px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #f1f5f9; background: #f8fafc; }
.reg-table td { padding: 11px 16px; border-bottom: 1px solid #f8fafc; color: #374151; }
.reg-table tr:last-child td { border-bottom: none; }
.reg-role-pill { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
.reg-role-customer  { background: #dbeafe; color: #1e40af; }
.reg-role-repairshop{ background: #fef3c7; color: #92400e; }
.reg-status-pill { padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
.reg-status-active   { background: #d1fae5; color: #065f46; }
.reg-status-suspended{ background: #fee2e2; color: #991b1b; }
.reg-status-pending  { background: #fef3c7; color: #92400e; }
.reg-status-approved { background: #d1fae5; color: #065f46; }
.reg-status-rejected { background: #f1f5f9; color: #64748b; }
.reg-empty { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 0.85rem; }

@media (max-width: 768px) {
  .reg-table-wrap { overflow-x: auto; }
  .reg-table { min-width: 620px; }
  .reg-controls { flex-direction: column; align-items: stretch; }
  .reg-search { width: 100%; }
}
    </style>
    </style>
    <link rel="manifest" href="../manifest.json" />
<meta name="theme-color" content="#f59e0b">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('../service-worker.js');
  }
</script>
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
          <a href="admin-dashboard.php" class="nav-item active">
            <span class="nav-icon"><img src="../assets/icons/dashboard.svg" alt="Dashboard" /></span>
            <span class="nav-text">Dashboard</span>
          </a>
          <a href="admin-approvals.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/approval.svg" alt="Shop Approvals" /></span>
            <span class="nav-text">Shop Approvals</span>
          </a>
          <a href="admin-shops.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/shop.svg" alt="All Repair Shops" /></span>
            <span class="nav-text">All Repair Shops</span>
          </a>
          <a href="admin-users.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/users.svg" alt="Users" /></span>
            <span class="nav-text">Users</span>
          </a>
          <a href="admin-subscriptions.php" class="nav-item">
  <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscriptions" /></span>
  <span class="nav-text">Subscriptions</span>
</a>
<a href="../developers.php" class="nav-item">
  <span class="nav-icon"><img src="../assets/icons/developers.svg" alt="Developers" /></span>
  <span class="nav-text">Developers</span>
</a>
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
        <div class="page-header">
          <h1 class="current-page-title">Admin Dashboard</h1>
        </div>
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

        <!-- ── STAT CARDS ── -->
        <div class="stats-grid-admin">
          <div class="stat-card stat-primary">
            <div class="stat-icon"><img src="../assets/icons/store.svg" alt="Shops" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statTotalShops">—</h3>
              <p class="stat-label">Total Shops</p>
            </div>
          </div>
          <div class="stat-card stat-warning">
            <div class="stat-icon"><img src="../assets/icons/glass.svg" alt="Pending" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statPending">—</h3>
              <p class="stat-label">Pending Approval</p>
            </div>
            <span class="stat-badge warn" id="badgePending" style="display:none">Needs review</span>
          </div>
          <div class="stat-card stat-success">
            <div class="stat-icon"><img src="../assets/icons/approve.svg" alt="Approved" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statApproved">—</h3>
              <p class="stat-label">Approved Shops</p>
            </div>
          </div>
          <div class="stat-card stat-danger">
            <div class="stat-icon"><img src="../assets/icons/suspend.svg" alt="Rejected" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statRejected">—</h3>
              <p class="stat-label">Rejected Shops</p>
            </div>
          </div>
          <div class="stat-card stat-info">
            <div class="stat-icon"><img src="../assets/icons/user.svg" alt="Users" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statUsers">—</h3>
              <p class="stat-label">Total Customers</p>
            </div>
          </div>
        </div>

        <!-- ── CHART + ACTIVITY ── -->
        <div class="dashboard-grid">

          <!-- Chart -->
<div class="dash-card">
  <div class="dash-card-title">
    <img src="../assets/icons/chart.svg" width="18" height="18" alt="" style="opacity:0.7">
    Shop Overview
    <span style="margin-left:auto;font-size:0.75rem;color:#94a3b8;font-weight:500" id="chartDateLabel"></span>
  </div>

  <!-- Row 1: Bar + Donut -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div>
      <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;">Registration Trend — Last 6 months</div>
      <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
    </div>
    <div>
      <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;">Shop Status Breakdown</div>
      <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
    </div>
  </div>

  <!-- Row 2: Line graph -->
  <div style="margin-top:1.25rem;">
    <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;">Registration Timeline — Last 30 days</div>
    <div style="height:180px;position:relative;">
      <canvas id="timelineChart"></canvas>
    </div>
  </div>

  <!-- Summary row -->
  <div style="display:flex;gap:1.5rem;margin-top:1.1rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
    <div><div style="font-size:1.35rem;font-weight:800;color:#0f172a" id="chartThisMonth">—</div><div style="font-size:0.75rem;color:#64748b">This Month</div></div>
    <div><div style="font-size:1.35rem;font-weight:800;color:#10b981" id="chartApprovalRate">—%</div><div style="font-size:0.75rem;color:#64748b">Approval Rate</div></div>
    <div><div style="font-size:1.35rem;font-weight:800;color:#3b82f6" id="chartTotal">—</div><div style="font-size:0.75rem;color:#64748b">Total Shops</div></div>
  </div>
</div>

          <!-- Recent Activity -->
          <div class="dash-card">
            <div class="dash-card-title"><img src="../assets/icons/clock.svg" width="18" height="18" alt="" style="opacity:0.7"> Recent Activity</div>
            <div class="activity-list" id="activityList">
              <!-- skeleton -->
              <div style="display:flex;flex-direction:column;gap:10px">
                <div class="skeleton" style="height:40px"></div>
                <div class="skeleton" style="height:40px"></div>
                <div class="skeleton" style="height:40px"></div>
              </div>
            </div>
          </div>

        </div>

        <!-- ── QUICK ACTIONS ── -->
        <h2 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 0.85rem">Quick Actions</h2>
        <div class="quick-actions-grid" style="margin-bottom:1.75rem">
          <a href="admin-approvals.php" class="quick-action-card">
            <div class="action-icon"><img src="../assets/icons/glass.svg" alt="Pending" width="34" height="34" /></div>
            <h3>Review Pending</h3>
            <p id="qaSubPending">Loading...</p>
          </a>
          <a href="admin-shops.php" class="quick-action-card">
            <div class="action-icon"><img src="../assets/icons/store.svg" alt="Shop" width="34" height="34" /></div>
            <h3>Manage Shops</h3>
            <p>View all registered shops</p>
          </a>
          <a href="admin-users.php" class="quick-action-card">
            <div class="action-icon"><img src="../assets/icons/user.svg" alt="Users" width="34" height="34" /></div>
            <h3>Manage Users</h3>
            <p>View and manage all users</p>
          </a>
          <a href="admin-approvals.php" class="quick-action-card">
            <div class="action-icon"><img src="../assets/icons/bar.svg" alt="Analytics" width="34" height="34" /></div>
            <h3>View Reports</h3>
            <p>Shop approval analytics</p>
          </a>
        </div>

       <!-- ── BOTTOM SUMMARY ── -->
        <div class="bottom-grid">
          <div class="summary-stat">
            <div class="big" id="sumApprovalRate">—%</div>
            <div class="lbl">Overall Approval Rate</div>
            <div class="bar-bg"><div class="bar-fg" id="barApproval" style="background:#10b981;width:0%"></div></div>
          </div>
          <div class="summary-stat">
            <div class="big" id="sumPendingRate">—%</div>
            <div class="lbl">Pending Rate</div>
            <div class="bar-bg"><div class="bar-fg" id="barPending" style="background:#f59e0b;width:0%"></div></div>
          </div>
          <div class="summary-stat">
            <div class="big" id="sumRejectedRate">—%</div>
            <div class="lbl">Rejection Rate</div>
            <div class="bar-bg"><div class="bar-fg" id="barRejected" style="background:#ef4444;width:0%"></div></div>
          </div>
        </div>

        <!-- ── ALL REGISTRATIONS ── -->
        <h2 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 0.85rem">All Registrations</h2>

        <div class="reg-summary-grid">
          <div class="reg-summary-card">
  <div class="reg-summary-icon reg-icon-customer"><img src="../assets/icons/user.svg" alt="Customers" width="22" height="22" /></div>
  <div>
    <div class="reg-summary-val" id="regTotalCustomers">—</div>
    <div class="reg-summary-lbl">Total Customers</div>
  </div>
</div>
<div class="reg-summary-card">
  <div class="reg-summary-icon reg-icon-shop"><img src="../assets/icons/store.svg" alt="Shop Owners" width="22" height="22" /></div>
  <div>
    <div class="reg-summary-val" id="regTotalShops">—</div>
    <div class="reg-summary-lbl">Total Shop Owners</div>
  </div>
</div>
<div class="reg-summary-card">
  <div class="reg-summary-icon reg-icon-growth-c"><img src="../assets/icons/bar.svg" alt="Growth" width="22" height="22" /></div>
  <div>
    <div class="reg-summary-val" id="regMonthCustomers">—</div>
    <div class="reg-summary-lbl">New Customers (This Month)</div>
  </div>
</div>
<div class="reg-summary-card">
  <div class="reg-summary-icon reg-icon-growth-s"><img src="../assets/icons/bar.svg" alt="Growth" width="22" height="22" /></div>
  <div>
    <div class="reg-summary-val" id="regMonthShops">—</div>
    <div class="reg-summary-lbl">New Shop Owners (This Month)</div>
  </div>
</div>
</div>
        <div class="reg-controls">
          <div class="reg-tabs">
            <button class="reg-tab-btn active" data-role="all">All</button>
            <button class="reg-tab-btn" data-role="customer">Customers</button>
            <button class="reg-tab-btn" data-role="repairshop">Shop Owners</button>
          </div>
          <input type="text" class="reg-search" id="regSearch" placeholder="Search by name or email…" />
        </div>

        <div class="reg-table-wrap">
          <table class="reg-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
              </tr>
            </thead>
            <tbody id="regTableBody">
              <tr><td colspan="5" class="reg-empty">Loading…</td></tr>
            </tbody>
          </table>
        </div>

      </div>

      </div>
      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <!-- Chart.js CDN -->
    <script src="../assets/js/chart.umd.min.js"></script>

    <script>
      // ── Sidebar toggle ────────────────────────────────────────
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", e => { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }

      // ── Animated counter ──────────────────────────────────────
      function animateCount(el, target) {
        let start = 0;
        const duration = 700;
        const step = (timestamp) => {
          if (!start) start = timestamp;
          const progress = Math.min((timestamp - start) / duration, 1);
          el.textContent = Math.floor(progress * target);
          if (progress < 1) requestAnimationFrame(step);
          else el.textContent = target;
        };
        requestAnimationFrame(step);
      }

      // ── Time ago helper ───────────────────────────────────────
      function timeAgo(dateStr) {
        if (!dateStr) return "";
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)   return "just now";
        if (diff < 3600) return Math.floor(diff/60) + "m ago";
        if (diff < 86400)return Math.floor(diff/3600) + "h ago";
        return Math.floor(diff/86400) + "d ago";
      }

      // ── Activity icons ────────────────────────────────────────
      const activityCfg = {
        approved: {
          cls:  "dot-approved",
          icon: `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
          label:"Shop Approved"
        },
        rejected: {
          cls:  "dot-rejected",
          icon: `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>`,
          label:"Shop Rejected"
        },
        pending: {
          cls:  "dot-pending",
          icon: `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>`,
          label:"New Shop Registered"
        },
        user: {
          cls:  "dot-user",
          icon: `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><circle cx="12" cy="9" r="3" fill="white"/><path d="M6.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/></svg>`,
          label:"New User Registered"
        },
      };

      // ── Chart instance ────────────────────────────────────────
      let trendChart = null;

      function renderChart(trend) {
        const labels   = trend.map(t => t.month);
        const totals   = trend.map(t => t.total);
        const approved = trend.map(t => t.approved);
        const ctx = document.getElementById("trendChart").getContext("2d");

        if (trendChart) trendChart.destroy();

        trendChart = new Chart(ctx, {
          type: "bar",
          data: {
            labels,
            datasets: [
              {
                label: "Registered",
                data: totals,
                backgroundColor: "rgba(59,130,246,0.18)",
                borderColor: "#3b82f6",
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
              },
              {
                label: "Approved",
                data: approved,
                backgroundColor: "rgba(16,185,129,0.18)",
                borderColor: "#10b981",
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: "top",
                labels: { font:{ size:11, family:"Outfit" }, color:"#64748b", boxWidth:12, padding:14 }
              },
              tooltip: { bodyFont:{ family:"Outfit" }, titleFont:{ family:"Outfit" } }
            },
            scales: {
              x: { grid:{ display:false }, ticks:{ font:{ size:11, family:"Outfit" }, color:"#94a3b8" } },
              y: { grid:{ color:"#f1f5f9" }, ticks:{ font:{ size:11, family:"Outfit" }, color:"#94a3b8", stepSize:1 }, beginAtZero:true }
            }
          }
        });
      }

      // ── Main data loader ──────────────────────────────────────
      async function loadDashboard() {
        try {
          const res  = await fetch("../api/get_dashboard_stats.php");
          const data = await res.json();
          if (data.error) throw new Error(data.error);

          // Stat cards
          animateCount(document.getElementById("statTotalShops"), data.totalShops);
          animateCount(document.getElementById("statPending"),    data.pendingShops);
          animateCount(document.getElementById("statApproved"),   data.approvedShops);
          animateCount(document.getElementById("statRejected"),   data.rejectedShops);
          animateCount(document.getElementById("statUsers"),      data.totalUsers);

          // Pending badge
          if (data.pendingShops > 0) {
            const badge = document.getElementById("badgePending");
            badge.style.display = "";
            badge.textContent   = data.pendingShops + " pending";
          }

          // Chart summary
          document.getElementById("chartThisMonth").textContent   = "+" + data.thisMonth;
          document.getElementById("chartApprovalRate").textContent = data.approvalRate + "%";
          document.getElementById("chartTotal").textContent        = data.totalShops;

          // Quick action subtitle
          document.getElementById("qaSubPending").textContent =
            data.pendingShops > 0
              ? data.pendingShops + " shop" + (data.pendingShops > 1 ? "s" : "") + " waiting"
              : "No shops waiting";

          // Bottom summary bars
          const total = data.totalShops || 1;
          const approvalRate  = Math.round((data.approvedShops / total) * 100);
          const pendingRate   = Math.round((data.pendingShops  / total) * 100);
          const rejectedRate  = Math.round((data.rejectedShops / total) * 100);

          document.getElementById("sumApprovalRate").textContent  = approvalRate  + "%";
          document.getElementById("sumPendingRate").textContent   = pendingRate   + "%";
          document.getElementById("sumRejectedRate").textContent  = rejectedRate  + "%";

          setTimeout(() => {
            document.getElementById("barApproval").style.width = approvalRate  + "%";
            document.getElementById("barPending").style.width  = pendingRate   + "%";
            document.getElementById("barRejected").style.width = rejectedRate  + "%";
          }, 300);


          // Charts
setTimeout(() => renderChart(data.trend || []), 50);

// Set today's date label
const today = new Date().toLocaleDateString("en-PH", { year:"numeric", month:"long", day:"numeric" });
document.getElementById("chartDateLabel").textContent = today;

// Donut chart
setTimeout(() => renderStatusChart(data.approvedShops, data.pendingShops, data.rejectedShops), 50);

setTimeout(() => renderTimelineChart(data.dailyRegistrations || []), 50);
          // Activity feed
          const list = document.getElementById("activityList");
          if (!data.recentActivity || !data.recentActivity.length) {
            list.innerHTML = `<div class="empty-activity"><img src="../assets/icons/glass.svg" width="40" style="opacity:0.25"><p>No recent activity yet.</p></div>`;
          } else {
            list.innerHTML = data.recentActivity.map(ev => {
              const cfg = activityCfg[ev.type] || activityCfg.user;
              return `
                <div class="activity-item">
                  <div class="activity-dot ${cfg.cls}">${cfg.icon}</div>
                  <div class="activity-content">
                    <div class="activity-label">${cfg.label}</div>
                    <div class="activity-name">${ev.name}</div>
                    <div class="activity-time">${timeAgo(ev.time)}</div>
                  </div>
                </div>`;
            }).join("");
          }

        } catch(e) {
          console.error("Dashboard load error:", e);
          document.getElementById("activityList").innerHTML =
            `<div class="empty-activity"><p style="color:#ef4444">Failed to load data. Please refresh.</p></div>`;
        }
      }

      // ── NOTIFICATION BELL ─────────────────────────────────────
      const notifBtn      = document.getElementById("notifBtn");
      const notifDropdown = document.getElementById("notifDropdown");
      const notifBadge    = document.getElementById("notifBadge");
      const notifList     = document.getElementById("notifList");
      let notifOpen       = false;
      let seenIds         = JSON.parse(localStorage.getItem("notifSeenIds") || "[]");

      // icon config reused from activityCfg
      const notifIconCfg = {
        approved: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>` },
        rejected: { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>` },
        pending:  { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>` },
        user:     { svg:`<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><circle cx="12" cy="9" r="3" fill="white"/><path d="M6.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/></svg>` },
      };

      const notifLabels = {
        approved: "Shop Approved",
        rejected: "Shop Rejected",
        pending:  "New Shop Registered",
        user:     "New User Registered",
      };

      function timeAgoShort(dateStr) {
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
          const res  = await fetch("../api/get_dashboard_stats.php");
          const data = await res.json();
          const events = data.recentActivity || [];

          // Count unread
          const unread = events.filter(ev => !seenIds.includes(buildNotifId(ev))).length;
          if (unread > 0) {
            notifBadge.textContent = unread > 9 ? "9+" : unread;
            notifBadge.classList.add("show");
          } else {
            notifBadge.classList.remove("show");
          }

          // Render list
          if (!events.length) {
            notifList.innerHTML = `<div class="notif-empty">🎉 No recent activity yet.</div>`;
            return;
          }

          notifList.innerHTML = events.map(ev => {
            const id      = buildNotifId(ev);
            const isUnread = !seenIds.includes(id);
            const cfg     = notifIconCfg[ev.type] || notifIconCfg.user;
            const label   = notifLabels[ev.type]  || "Activity";
            return `
              <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${id}">
                <div class="notif-dot-icon">${cfg.svg}</div>
                <div class="notif-content">
                  <div class="notif-title">${label}</div>
                  <div class="notif-name">${ev.name}</div>
                  <div class="notif-time">${timeAgoShort(ev.time)}</div>
                </div>
                ${isUnread ? '<div class="notif-unread-dot"></div>' : ''}
              </div>`;
          }).join("");

        } catch(e) {
          notifList.innerHTML = `<div class="notif-empty" style="color:#ef4444">Failed to load.</div>`;
        }
      }

      // Toggle dropdown
    notifBtn.addEventListener("click", (e) => {
  e.stopPropagation();
  notifOpen = !notifOpen;
  notifDropdown.classList.toggle("open", notifOpen);
  if (notifOpen) {
    loadNotifications();
    // Auto mark all read pag open
    setTimeout(() => {
      document.getElementById("markAllRead").click();
    }, 300); // slight delay para ma-render muna ang list
  }
});

      // Close on outside click
      document.addEventListener("click", (e) => {
        if (!notifBtn.closest(".notif-wrapper").contains(e.target)) {
          notifOpen = false;
          notifDropdown.classList.remove("open");
        }
      });

      // Mark all read
      document.getElementById("markAllRead").addEventListener("click", () => {
        const items = notifList.querySelectorAll(".notif-item[data-id]");
        items.forEach(item => {
          const id = item.dataset.id;
          if (!seenIds.includes(id)) seenIds.push(id);
          item.classList.remove("unread");
          const dot = item.querySelector(".notif-unread-dot");
          if (dot) dot.remove();
        });
        localStorage.setItem("notifSeenIds", JSON.stringify(seenIds));
        notifBadge.classList.remove("show");
      });

      // Load badge count on page load
      loadNotifications();

      loadDashboard();
      setInterval(loadDashboard, 30000); // auto-refresh every 30s

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

// ── ALL REGISTRATIONS ─────────────────────────────────────
let allRegistrations = [];
let regRoleFilter     = 'all';
let regSearchQuery    = '';

function fmtRegDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

function renderRegistrations() {
  const tbody = document.getElementById('regTableBody');
  let filtered = allRegistrations;

  if (regRoleFilter !== 'all') {
    filtered = filtered.filter(u => u.role === regRoleFilter);
  }
  if (regSearchQuery) {
    filtered = filtered.filter(u =>
      u.name.toLowerCase().includes(regSearchQuery) ||
      u.email.toLowerCase().includes(regSearchQuery)
    );
  }

  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="reg-empty">No registrations found.</td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(u => {
    const roleLabel  = u.role === 'repairshop' ? 'Repair Shop' : 'Customer';
    const statusText = u.role === 'repairshop' ? (u.approval_status || 'approved') : (u.status || 'active');
    const statusCls  = `reg-status-${statusText}`;
    return `
      <tr>
        <td><strong>${escapeHtml(u.name)}</strong></td>
        <td>${escapeHtml(u.email)}</td>
        <td><span class="reg-role-pill reg-role-${u.role}">${roleLabel}</span></td>
        <td><span class="reg-status-pill ${statusCls}">${statusText.charAt(0).toUpperCase() + statusText.slice(1)}</span></td>
        <td>${fmtRegDate(u.created_at)}</td>
      </tr>`;
  }).join('');
}

function escapeHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadRegistrations() {
  try {
    const res  = await fetch('../api/get_all_registrations.php');
    const data = await res.json();
    if (!data.success) throw new Error('Failed');

    allRegistrations = data.users || [];

    document.getElementById('regTotalCustomers').textContent = data.totalCustomers;
    document.getElementById('regTotalShops').textContent     = data.totalShops;
    document.getElementById('regMonthCustomers').textContent = '+' + data.thisMonthCustomers;
    document.getElementById('regMonthShops').textContent     = '+' + data.thisMonthShops;

    renderRegistrations();
  } catch(e) {
    document.getElementById('regTableBody').innerHTML =
      `<tr><td colspan="5" class="reg-empty" style="color:#ef4444">Failed to load registrations.</td></tr>`;
  }
}

document.querySelectorAll('.reg-tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.reg-tab-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    regRoleFilter = this.dataset.role;
    renderRegistrations();
  });
});

document.getElementById('regSearch').addEventListener('input', function() {
  regSearchQuery = this.value.toLowerCase().trim();
  renderRegistrations();
});

loadRegistrations();

// ── Donut chart instance ───────────────────────────────
let statusChart = null;

function renderStatusChart(approved, pending, rejected) {
  const ctx = document.getElementById("statusChart").getContext("2d");
  if (statusChart) statusChart.destroy();
  statusChart = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: ["Approved", "Pending", "Rejected"],
      datasets: [{
        data: [approved, pending, rejected],
        backgroundColor: ["rgba(16,185,129,0.85)", "rgba(245,158,11,0.85)", "rgba(239,68,68,0.85)"],
        borderColor: ["#10b981", "#f59e0b", "#ef4444"],
        borderWidth: 2,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "65%",
      plugins: {
        legend: {
          position: "bottom",
          labels: { font:{ size:11, family:"Outfit" }, color:"#64748b", boxWidth:12, padding:10 }
        },
        tooltip: { bodyFont:{ family:"Outfit" }, titleFont:{ family:"Outfit" } }
      }
    }
  });
}

// ── Timeline line chart ───────────────────────────────
let timelineChart = null;

function renderTimelineChart(dailyData) {
  const labels = [];
  const customerMap = {};
  const shopMap = {};

  for (let i = 29; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().split("T")[0];
    labels.push(d.toLocaleDateString("en-PH", { month:"short", day:"numeric" }));
    customerMap[key] = 0;
    shopMap[key] = 0;
  }

  (dailyData || []).forEach(row => {
    if (row.role === "customer")   customerMap[row.reg_date] = parseInt(row.count);
    if (row.role === "repairshop") shopMap[row.reg_date]     = parseInt(row.count);
  });

  const ctx = document.getElementById("timelineChart").getContext("2d");
  if (timelineChart) timelineChart.destroy();

  timelineChart = new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          label: "Customers",
          data: Object.values(customerMap),
          borderColor: "#8b5cf6",
          backgroundColor: "rgba(139,92,246,0.08)",
          borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5,
          fill: true, tension: 0.4,
        },
        {
          label: "Repair Shops",
          data: Object.values(shopMap),
          borderColor: "#f59e0b",
          backgroundColor: "rgba(245,158,11,0.08)",
          borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5,
          fill: true, tension: 0.4,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position:"top", labels:{ font:{size:11,family:"Outfit"}, color:"#64748b", boxWidth:12, padding:14 } },
        tooltip: { bodyFont:{family:"Outfit"}, titleFont:{family:"Outfit"} }
      },
      scales: {
        x: { grid:{display:false}, ticks:{ font:{size:10,family:"Outfit"}, color:"#94a3b8", maxTicksLimit:10 } },
        y: { grid:{color:"#f1f5f9"}, ticks:{ font:{size:11,family:"Outfit"}, color:"#94a3b8", stepSize:1 }, beginAtZero:true }
      }
    }
  });
}
    </script>
     <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
  </body>
</html>