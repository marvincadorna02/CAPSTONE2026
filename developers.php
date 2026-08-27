<?php
session_start();

// ── Session timeout (30 mins) ──
$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'repairshop') { header("Location: shop-owner/shop-dashboard.php"); }
    else { header("Location: shop-owner/dashboard.php"); }
    exit();
}

$userName  = $_SESSION['name'];
$userId    = $_SESSION['user_id'];
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Developers - Fix It Davao</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/dashboard.css" />
    <link rel="stylesheet" href="assets/css/dashboard-mobile-additions.css" />
    <style>

         /* ── PAGE LOAD ANIMATIONS (matches my-bookings.php / admin-dashboard.php) ── */
      @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
      .top-bar            { animation: fadeInUp 0.4s ease both; }
      .sub-status-banner  { animation: fadeInUp 0.45s ease both; }
      .plans-grid         { animation: fadeInUp 0.5s ease both; }
      .payment-section.show { animation: fadeInUp 0.35s ease; }
      .pending-info-card  { animation: fadeInUp 0.5s ease both; }
      .dash-card          { animation: fadeInUp 0.55s ease both; }
      .top-bar              { animation: fadeInUp 0.4s ease both; }
      .dev-hero             { animation: fadeInUp 0.5s ease both; }
      .developers-grid      { animation: fadeInUp 0.6s ease both; }
      .project-info-section { animation: fadeInUp 0.65s ease both; }
      .tech-stack-section   { animation: fadeInUp 0.7s ease both; }

      .dev-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 60%, #1e1b4b 100%);
        border-radius: 20px; padding: 3rem 2rem; text-align: center;
        color: white; margin-bottom: 2.5rem; position: relative; overflow: hidden;
      }
      .dev-hero::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 30% 50%, rgba(245,158,11,0.12) 0%, transparent 60%),
                    radial-gradient(ellipse at 70% 50%, rgba(99,102,241,0.1) 0%, transparent 60%);
        pointer-events: none;
      }
      .dev-hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: #fbbf24; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 1rem; }
      .dev-hero h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); font-weight: 800; margin: 0 0 1rem; line-height: 1.2; }
      .dev-hero p  { font-size: clamp(0.9rem, 2vw, 1rem); opacity: 0.8; max-width: 600px; margin: 0 auto; line-height: 1.7; }

      .developers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; perspective: 1200px; }

      .developer-card { height: 520px; position: relative; cursor: pointer; }
      .developer-card-inner { position: absolute; width: 100%; height: 100%; transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1); transform-style: preserve-3d; }
      .developer-card.flipped .developer-card-inner { transform: rotateY(180deg); }

      .developer-card-front, .developer-card-back {
        position: absolute; width: 100%; height: 100%;
        backface-visibility: hidden; -webkit-backface-visibility: hidden;
        background: white; border-radius: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;
        padding: 2rem; display: flex; flex-direction: column; align-items: center; overflow: hidden;
      }
      .developer-card-back { transform: rotateY(180deg); align-items: stretch; overflow-y: auto; }

      .dev-photo-wrap { width: 110px; height: 110px; border-radius: 50%; overflow: hidden; border: 4px solid #e2e8f0; box-shadow: 0 0 0 4px rgba(245,158,11,0.2); margin-bottom: 1.25rem; flex-shrink: 0; background: #f1f5f9; }
      .dev-photo-wrap img { width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block; }

      .dev-front-info { text-align: center; width: 100%; }
      .dev-name  { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem; line-height: 1.3; }
      .dev-role  { font-size: 0.85rem; font-weight: 700; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.2rem; }
      .dev-title { font-size: 0.85rem; color: #94a3b8; margin: 0 0 1.25rem; }

      .dev-stats { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
      .dev-stat-row { display: flex; align-items: flex-start; gap: 10px; padding: 0.4rem 0; font-size: 0.85rem; color: #475569; }
      .dev-stat-row + .dev-stat-row { border-top: 1px solid #e2e8f0; }
      .dev-stat-row img { flex-shrink: 0; margin-top: 1px; opacity: 0.6; }

      .btn-flip { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-family: var(--font-primary); margin-top: auto; letter-spacing: 0.3px; }
      .btn-flip:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(245,158,11,0.35); }
      .btn-flip-back { background: #f1f5f9; color: #475569; margin-top: 1rem; }
      .btn-flip-back:hover { background: #e2e8f0; box-shadow: none; transform: translateY(-1px); }

      .back-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem; text-align: center; }
      .dev-bio { font-size: 0.85rem; color: #64748b; line-height: 1.7; text-align: center; margin-bottom: 1.25rem; padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; }
      .back-section { margin-bottom: 1.25rem; }
      .back-section h4 { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem; }
      .skills-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
      .skill-tag { background: #eff6ff; color: #1d4ed8; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600; border: 1px solid #dbeafe; }
      .social-links { display: flex; flex-direction: column; gap: 0.5rem; }
      .social-link { display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #f8fafc; color: #374151; padding: 0.65rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.25s ease; border: 1px solid #e2e8f0; }
      .social-link:hover { background: #0f172a; color: white; border-color: #0f172a; transform: translateX(4px); }
      .social-link:hover img { filter: brightness(0) invert(1); }

      .section-heading { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0 0 1.5rem; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; }
      .section-heading::before, .section-heading::after { content: ''; height: 2px; width: 40px; background: linear-gradient(90deg, transparent, #f59e0b); border-radius: 2px; }
      .section-heading::after { background: linear-gradient(90deg, #f59e0b, transparent); }

      .project-info-section { margin-bottom: 2.5rem; }
      .project-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
      .info-card { background: white; padding: 1.75rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: center; transition: all 0.3s ease; }
      .info-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-color: #f59e0b; }
      .info-card-icon { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, rgba(245,158,11,0.12), rgba(245,158,11,0.06)); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 1px solid rgba(245,158,11,0.2); }
      .info-card h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.6rem; }
      .info-card p  { font-size: 0.85rem; color: #64748b; line-height: 1.6; margin: 0; }

      .tech-stack-section { background: white; padding: 2rem; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1rem; }
      .tech-stack-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
      .tech-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; transition: all 0.25s ease; cursor: default; }
      .tech-item:hover { background: #0f172a; color: white; border-color: #0f172a; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(15,23,42,0.2); }
      .tech-item:hover img { filter: brightness(0) invert(1); }
      .tech-name { font-weight: 600; font-size: 0.88rem; color: inherit; }

      /* ── NOTIFICATION BELL ── */
      .notif-wrapper { position:relative; }
      .notification-btn { position:relative; }
      .notif-badge { position:absolute; top:-3px; right:-3px; min-width:17px; height:17px; padding:0 4px; background:#ef4444; color:white; border-radius:10px; font-size:0.65rem; font-weight:800; display:none; align-items:center; justify-content:center; font-family:var(--font-primary); border:2px solid white; line-height:1; }
      .notif-badge.show { display:flex; }
      .notif-dropdown { position:absolute; top:calc(100% + 10px); right:0; width:320px; background:white; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.18); border:1px solid #e2e8f0; z-index:999; opacity:0; pointer-events:none; transform:translateY(-8px) scale(0.97); transition:opacity 0.22s ease, transform 0.22s ease; overflow:hidden; }
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

      @media (max-width: 768px) {
        .dev-hero { padding: 2rem 1.25rem; margin-bottom: 1.5rem; }
        .developers-grid { grid-template-columns: 1fr; gap: 1.25rem; margin-bottom: 1.75rem; perspective: none; }
        .developer-card { height: auto; min-height: unset; }
        .developer-card-inner { position: relative; transform: none !important; transform-style: flat; transition: none; }
        .developer-card-front, .developer-card-back { position: relative; transform: none !important; backface-visibility: visible; -webkit-backface-visibility: visible; height: auto; border-radius: 16px; }
        .developer-card-back { display: none; margin-top: 0; }
        .developer-card.flipped .developer-card-front { display: none; }
        .developer-card.flipped .developer-card-back  { display: flex; }
        .dev-photo-wrap { width: 90px; height: 90px; }
        .dev-name { font-size: 1.1rem; }
        .project-info-grid { grid-template-columns: 1fr; gap: 1rem; }
        .tech-stack-grid   { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        .tech-stack-section { padding: 1.5rem 1.25rem; }
        .section-heading { font-size: 1.25rem; }
        .section-heading::before, .section-heading::after { width: 24px; }
      }
      @media (max-width: 480px) { .tech-stack-grid { grid-template-columns: 1fr 1fr; } .dev-hero h2 { font-size: 1.3rem; } }

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
  <body class="role-admin">
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo-mini"><img src="assets/images/logo.png" alt="Fix It Davao" /></div>
        <h2 class="brand-name">FIX IT DAVAO</h2>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section" data-role="admin">
          <a href="admin/admin-dashboard.php" class="nav-item"><span class="nav-icon"><img src="assets/icons/dashboard.svg" alt="Dashboard" /></span><span class="nav-text">Dashboard</span></a>
          <a href="admin/admin-approvals.php" class="nav-item"><span class="nav-icon"><img src="assets/icons/approval.svg" alt="Shop Approvals" /></span><span class="nav-text">Shop Approvals</span></a>
          <a href="admin/admin-shops.php" class="nav-item"><span class="nav-icon"><img src="assets/icons/shop.svg" alt="All Repair Shops" /></span><span class="nav-text">All Repair Shops</span></a>
          <a href="admin/admin-users.php" class="nav-item"><span class="nav-icon"><img src="assets/icons/users.svg" alt="Users" /></span><span class="nav-text">Users</span></a>
           <a href="admin/admin-subscriptions.php" class="nav-item">
  <span class="nav-icon"><img src="assets/icons/approve.svg" alt="Subscriptions" /></span>
  <span class="nav-text">Subscriptions</span>
</a>
          <a href="developers.php" class="nav-item active"><span class="nav-icon"><img src="assets/icons/developers.svg" alt="Developers" /></span><span class="nav-text">Developers</span></a>
        </div>
      </nav>
      <div class="sidebar-footer">
        <a href="logout.php" class="nav-item" onclick="return confirmLogout(event)"><span class="nav-icon"><img src="assets/icons/logout.svg" alt="Logout" /></span><span class="nav-text">Logout</span></a>
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
      <button class="modal-btn-confirm" style="background:linear-gradient(135deg,#ef4444,#dc2626);" onclick="window.location.href='logout.php'">Yes, Logout</button>
    </div>
  </div>
</div>

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">Developers Profile</h1></div>
        <div class="top-bar-actions">
          <div class="notif-wrapper">
            <button class="icon-btn notification-btn" id="notifBtn">
              <img src="assets/icons/bell.svg" alt="Notifications" width="20" height="20" />
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
              <div class="notif-footer"><a href="admin/admin-approvals.php">View all activity →</a></div>
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

        <div class="dev-hero">
          <div class="dev-hero-badge">✦ Capstone Project 2026</div>
          <h2>Meet the Team Behind Fix It Davao</h2>
          <p>Passionate developers building innovative solutions that connect Davao residents with trusted repair services.</p>
        </div>

        <div class="developers-grid">
          <!-- Developer 1 — Marvin -->
          <div class="developer-card">
            <div class="developer-card-inner">
              <div class="developer-card-front">
                <div class="dev-photo-wrap">
                  <img src="assets/images/marvin.jpg" alt="Marvin Cadorna" onerror="this.src='https://ui-avatars.com/api/?name=Marvin+Cadorna&background=2563eb&color=fff&size=300&bold=true'" />
                </div>
                <div class="dev-front-info">
                  <h3 class="dev-name">Marvin P. Cadorna Jr.</h3>
                  <p class="dev-role">Lead Developer</p>
                  <p class="dev-title">Programmer & Designer</p>
                  <div class="dev-stats">
                    <div class="dev-stat-row"><img src="assets/icons/cap.svg" width="16" height="16" alt="" /><span>BS Information Communication and Technology</span></div>
                    <div class="dev-stat-row"><img src="assets/icons/map.svg" width="16" height="16" alt="" /><span>Davao City, Philippines</span></div>
                  </div>
                </div>
                <button class="btn-flip" onclick="flipCard(this)">View Profile →</button>
              </div>
              <div class="developer-card-back">
                <h3 class="back-title">About Marvin</h3>
                <p class="dev-bio">Passionate about creating user-friendly applications that solve real-world problems. Specialized in web development, database design, and system architecture.</p>
                <div class="back-section">
                  <h4>Skills & Technologies</h4>
                  <div class="skills-tags">
                    <span class="skill-tag">HTML/CSS</span><span class="skill-tag">JavaScript</span><span class="skill-tag">PHP</span><span class="skill-tag">MySQL</span>
                  </div>
                </div>
                <div class="back-section">
                  <h4>Connect</h4>
                  <div class="social-links">
                    <a href="#" class="social-link"><img src="assets/icons/email.svg" width="16" height="16" alt="" /> Email</a>
                    <a href="#" class="social-link"><img src="assets/icons/github.svg" width="16" height="16" alt="" /> GitHub</a>
                  </div>
                </div>
                <button class="btn-flip btn-flip-back" onclick="flipCard(this)">← Back</button>
              </div>
            </div>
          </div>

          <!-- Developer 2 — Caesar -->
          <div class="developer-card">
            <div class="developer-card-inner">
              <div class="developer-card-front">
                <div class="dev-photo-wrap">
                  <img src="assets/images/adan.jpg" alt="Caesar Ian Adan" onerror="this.src='https://ui-avatars.com/api/?name=Caesar+Adan&background=10b981&color=fff&size=300&bold=true'" />
                </div>
                <div class="dev-front-info">
                  <h3 class="dev-name">Caesar Ian M. Adan</h3>
                  <p class="dev-role">Document Writer</p>
                  <p class="dev-title">Hustler & Designer</p>
                  <div class="dev-stats">
                    <div class="dev-stat-row"><img src="assets/icons/cap.svg" width="16" height="16" alt="" /><span>BS Information Communication and Technology</span></div>
                    <div class="dev-stat-row"><img src="assets/icons/map.svg" width="16" height="16" alt="" /><span>Davao City, Philippines</span></div>
                  </div>
                </div>
                <button class="btn-flip" onclick="flipCard(this)">View Profile →</button>
              </div>
              <div class="developer-card-back">
                <h3 class="back-title">About Caesar</h3>
                <p class="dev-bio">Creative developer with a passion for designing intuitive user interfaces. Focused on creating seamless user experiences and bringing ideas to life through code.</p>
                <div class="back-section">
                  <h4>Skills & Technologies</h4>
                  <div class="skills-tags">
                    <span class="skill-tag">HTML/CSS</span><span class="skill-tag">JavaScript</span><span class="skill-tag">Figma</span>
                  </div>
                </div>
                <div class="back-section">
                  <h4>Connect</h4>
                  <div class="social-links">
                    <a href="#" class="social-link"><img src="assets/icons/email.svg" width="16" height="16" alt="" /> Email</a>
                    <a href="#" class="social-link"><img src="assets/icons/github.svg" width="16" height="16" alt="" /> GitHub</a>
                  </div>
                </div>
                <button class="btn-flip btn-flip-back" onclick="flipCard(this)">← Back</button>
              </div>
            </div>
          </div>
        </div>

        <div class="project-info-section">
          <h2 class="section-heading">About This Project</h2>
          <div class="project-info-grid">
            <div class="info-card"><div class="info-card-icon"><img src="assets/icons/goal.svg" width="28" height="28" alt="" /></div><h3>Project Goal</h3><p>Connect Davao residents with trusted repair shops for phones, laptops, and tablets through an easy-to-use platform.</p></div>
            <div class="info-card"><div class="info-card-icon"><img src="assets/icons/bulb.svg" width="28" height="28" alt="" /></div><h3>Innovation</h3><p>First comprehensive repair shop finder in Davao City with booking system, reviews, and real-time availability.</p></div>
            <div class="info-card"><div class="info-card-icon"><img src="assets/icons/trophy.svg" width="28" height="28" alt="" /></div><h3>Capstone Project</h3><p>Developed as our culminating project for BS ICT, showcasing our technical and problem-solving skills.</p></div>
          </div>
        </div>

        <div class="tech-stack-section">
          <h2 class="section-heading">Technologies Used</h2>
          <div class="tech-stack-grid">
            <div class="tech-item"><img src="assets/icons/html.svg" width="24" height="24" alt="HTML" /><span class="tech-name">HTML5 & CSS3</span></div>
            <div class="tech-item"><img src="assets/icons/js.svg" width="24" height="24" alt="JS" /><span class="tech-name">JavaScript</span></div>
            <div class="tech-item"><img src="assets/icons/php.svg" width="24" height="24" alt="PHP" /><span class="tech-name">PHP</span></div>
            <div class="tech-item"><img src="assets/icons/mysql.svg" width="24" height="24" alt="MySQL" /><span class="tech-name">MySQL</span></div>
            <div class="tech-item"><img src="assets/icons/github.svg" width="24" height="24" alt="Git" /><span class="tech-name">Git & GitHub</span></div>
          </div>
        </div>

      </div>
      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script>
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", function() { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", function(e) { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }

      function flipCard(button) {
        const card = button.closest(".developer-card");
        card.classList.toggle("flipped");
      }

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
          const res=await fetch("api/get_dashboard_stats.php"); const data=await res.json();
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
    window.location.href = "login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
  </body>
</html>