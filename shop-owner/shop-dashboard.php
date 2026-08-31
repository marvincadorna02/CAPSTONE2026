<?php
session_start();
require_once __DIR__ . '/../includes/guard.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ── Session timeout (30 mins) ──
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId   = (int) $_SESSION['user_id'];
$userName = $_SESSION['name'];

// ── Load shop name + logo for avatar ──────────────────────────
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) die("DB error: " . $conn->connect_error);
$stmt = $conn->prepare("SELECT name, email, contact_number, shop_name, logo_url FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
if (!empty($row['shop_name'])) $userName = $row['shop_name'];

$acctName    = $row['name'] ?? ($_SESSION['name'] ?? '');
$acctEmail   = $row['email'] ?? ($_SESSION['email'] ?? '');
$acctContact = $row['contact_number'] ?? '';

$savedLogoUrl = $row['logo_url'] ?? '';
if ($savedLogoUrl) {
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $baseUrl = ($isHttps ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    $savedLogoUrl = $baseUrl . $savedLogoUrl;
}
$avatarUrl = $savedLogoUrl ?: "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=f59e0b&color=fff";
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Dashboard - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@600;700&family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <meta name="theme-color" content="#f59e0b" />
    <style>
      /* ── ANIMATIONS ── */
      @keyframes fadeInUp { from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)} }
      @keyframes shimmer  { 0%{background-position:200% 0}100%{background-position:-200% 0} }
      @keyframes spin     { to { transform:rotate(360deg); } }

      /* ── PERIOD FILTER ── */
      .dash-filter { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1.25rem; animation:fadeInUp 0.4s ease both; }
      .filter-chip { padding:7px 16px; border:2px solid #e2e8f0; background:#fff; border-radius:20px; font-size:.78rem; font-weight:700; color:#64748b; cursor:pointer; font-family:'Outfit',sans-serif; transition:all .2s; }
      .filter-chip:hover { border-color:#f59e0b; color:#f59e0b; }
      .filter-chip.active { background:#f59e0b; border-color:#f59e0b; color:#fff; }

      /* ── STATS GRID ── */
      .stats-grid-admin {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem; margin-bottom: 1.75rem; animation: fadeInUp 0.4s ease both;
      }
      .stat-card {
        background: white; border-radius: 16px; padding: 1.4rem 1.5rem;
        display: flex; align-items: center; gap: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.07); border: 1px solid #f1f5f9;
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
      .stat-info::after    { background:#8b5cf6; }
      .stat-teal::after    { background:#14b8a6; }

      .stat-icon { width:54px; height:54px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
      .stat-primary .stat-icon { background:#dbeafe; }
      .stat-warning .stat-icon { background:#fef3c7; }
      .stat-success .stat-icon { background:#d1fae5; }
      .stat-info    .stat-icon { background:#ede9fe; }
      .stat-teal    .stat-icon { background:#ccfbf1; }

      .stat-value { font-size:2rem; font-weight:800; color:#0f172a; margin:0 0 2px; line-height:1; font-family:'Rajdhani',sans-serif !important; }
      .stat-label { color:#64748b; font-size:0.82rem; margin:0; font-weight:500; }
      .stat-sub   { font-size:0.7rem; color:#94a3b8; margin:3px 0 0; font-weight:500; }
      .stat-badge { position:absolute; top:12px; right:14px; font-size:0.68rem; font-weight:700; padding:3px 8px; border-radius:20px; }
      .stat-badge.up   { background:#d1fae5; color:#065f46; }
      .stat-badge.warn { background:#fef3c7; color:#92400e; }
      .stat-badge.red  { background:#fee2e2; color:#991b1b; }

      /* ── TWO-COLUMN LAYOUT ── */
      .dashboard-grid { display:grid; grid-template-columns:1fr 340px; gap:1.25rem; margin-bottom:1.75rem; animation:fadeInUp 0.4s ease both; align-items:start; }

      /* ── CARDS ── */
      .dash-card { background:white; border-radius:16px; padding:1.4rem 1.5rem; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:1px solid #f1f5f9; position:relative; overflow:hidden; }
      .dash-card::before { content:""; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#0f172a,#f59e0b 60%,#fbbf24); opacity:0.85; }
      .dash-card-title { font-size:0.95rem; font-weight:700; color:#0f172a; margin:0 0 1.1rem; display:flex; align-items:center; gap:8px; }

      /* ── CHART ── */
      .chart-wrap { height:200px; position:relative; }
      .chart-empty-overlay {
        position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;
        background:rgba(248,250,252,0.85); border-radius:10px; color:#94a3b8; font-size:0.78rem; font-weight:600;
        font-family:'Outfit',sans-serif; text-align:center; padding:10px; opacity:0; pointer-events:none; transition:opacity 0.2s ease;
      }
      .chart-empty-overlay.show { opacity:1; pointer-events:all; }
      .chart-empty-overlay .empty-icon { font-size:1.4rem; opacity:0.5; }

      /* ── ACTIVITY FEED ── */
      .activity-list { display:flex; flex-direction:column; gap:0; }
      .activity-item { display:flex; align-items:flex-start; gap:0.9rem; padding:0.8rem 0; border-bottom:1px solid #f8fafc; cursor:pointer; }
      .activity-item:last-child { border-bottom:none; padding-bottom:0; }
      .activity-item:hover { background:#fafafa; }
      .activity-dot { width:34px; height:34px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; margin-top:1px; }
      .activity-content { flex:1; min-width:0; }
      .activity-label { font-size:0.82rem; font-weight:700; color:#0f172a; margin:0 0 2px; }
      .activity-name  { font-size:0.78rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .activity-time  { font-size:0.72rem; color:#94a3b8; margin-top:2px; }
      .skeleton { background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:8px; }
      .empty-activity { text-align:center; padding:30px 20px; color:#94a3b8; }
      .empty-activity p { font-size:0.85rem; margin:8px 0 0; }

      /* ── NOTIFICATION BELL ── */
      .notif-wrapper { position:relative; }
      .notification-btn { position:relative; }
      .notif-badge { position:absolute; top:-3px; right:-3px; min-width:17px; height:17px; padding:0 4px; background:#ef4444; color:white; border-radius:10px; font-size:0.65rem; font-weight:800; display:none; align-items:center; justify-content:center; font-family:'Outfit',sans-serif; border:2px solid white; line-height:1; }
      .notif-badge.show { display:flex; }
      .notif-dropdown { position:absolute; top:calc(100% + 10px); right:0; width:320px; background:white; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.18); border:1px solid #e2e8f0; z-index:999; opacity:0; pointer-events:none; transform:translateY(-8px) scale(0.97); transition:opacity 0.22s ease, transform 0.22s ease; overflow:hidden; }
      .notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }
      @media (max-width: 768px) { .notif-dropdown { position:fixed !important; top:70px !important; left:8px !important; right:8px !important; width:auto !important; max-width:100% !important; max-height:70vh; overflow-y:auto; z-index:1200; } }
      .notif-header { padding:14px 16px 10px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
      .notif-header-title { font-size:0.88rem; font-weight:800; color:#0f172a; font-family:'Outfit',sans-serif; }
      .notif-mark-read { font-size:.72rem; font-weight:700; color:#f59e0b; background:none; border:none; cursor:pointer; font-family:"Outfit",sans-serif; padding:3px 8px; border-radius:6px; transition:background 0.2s ease, color 0.2s ease; }
      .notif-mark-read:hover { background:#fff7e6; color:#d97706; }
      .notif-list { max-height:340px; overflow-y:auto; scrollbar-width:thin; scrollbar-color:#e2e8f0 transparent; }
      .notif-list::-webkit-scrollbar { width:4px; }
      .notif-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:4px; }
      .notif-item { display:flex; align-items:flex-start; gap:10px; padding:11px 16px; border-bottom:1px solid #f8fafc; transition:background 0.15s ease; cursor:pointer; }
      .notif-item:last-child { border-bottom:none; }
      .notif-item:hover { background:#fafafa; }
      .notif-item.unread { background:#fffbeb; }
      .notif-dot-icon { width:34px; height:34px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; margin-top:1px; overflow:hidden; }
      .notif-dot-icon img { width:34px; height:34px; border-radius:50%; object-fit:cover; }
      .notif-content { flex:1; min-width:0; }
      .notif-title { font-size:0.8rem; font-weight:700; color:#0f172a; margin:0 0 2px; font-family:'Outfit',sans-serif; }
      .notif-name  { font-size:0.75rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .notif-time  { font-size:0.7rem; color:#94a3b8; margin-top:3px; }
      .notif-unread-dot { width:7px; height:7px; background:#f59e0b; border-radius:50%; flex-shrink:0; margin-top:6px; }
      .notif-empty { text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.82rem; font-family:'Outfit',sans-serif; }
      .notif-loading { text-align:center; padding:24px 20px; }
      .notif-spinner { width:22px; height:22px; border:2.5px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto; }

      /* ── SIDEBAR BACKDROP ── */
      .sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:900; }
      body.sidebar-open .sidebar-backdrop { display:block; }
      .sidebar { z-index:950; }

      /* ── LOGOUT / ACCOUNT MODAL ── */
      .acct-tab { flex:1; padding:9px; background:#f1f5f9; border:none; border-radius:10px 10px 0 0; font-size:.8rem; font-weight:700; color:#64748b; cursor:pointer; font-family:'Outfit',sans-serif; }
      .acct-tab-active { background:#fff; color:#0f172a; box-shadow:inset 0 -2px 0 #f59e0b; }
      .acct-field { margin-bottom:14px; }
      .acct-field label { display:block; font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
      .acct-field input { width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:.88rem; font-family:'Outfit',sans-serif; box-sizing:border-box; }
      .acct-field input:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.15); }
      .acct-msg { display:none; padding:9px 12px; border-radius:8px; font-size:.78rem; font-weight:600; margin-bottom:12px; }
      .acct-submit { width:100%; padding:11px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; border-radius:10px; font-size:.85rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; }
      .acct-submit:disabled { opacity:.6; cursor:not-allowed; }

      /* ── RESPONSIVE ── */
      .chart-wrap canvas { max-width:100% !important; }
      @media(max-width:1024px){ .dashboard-grid { grid-template-columns:1fr; } }
      @media(max-width:768px){
        .row-2col { display:flex !important; flex-direction:column !important; gap:1.25rem !important; }
        .chart-wrap { height:200px !important; }
        .dash-card { padding:1rem !important; }
        .dash-card-title { flex-wrap:wrap; }
        .stats-grid-admin { gap:0.85rem; }
        .dashboard-content { overflow-x:hidden; }
      }
      @media(max-width:640px){
        .stats-grid-admin { grid-template-columns:1fr 1fr; }
        .stat-value { font-size:1.6rem; }
        .stat-card { padding:1rem; gap:0.75rem; }
        .stat-icon { width:44px; height:44px; border-radius:12px; }
        .stat-icon img { width:22px; height:22px; }
      }
      @media(max-width:400px){
        .stats-grid-admin { grid-template-columns:1fr; }
        .stat-card { padding:0.9rem 1rem; }
      }
      @media(max-width:480px){
        .dash-filter { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding-bottom:2px; }
        .dash-filter::-webkit-scrollbar { display:none; }
        .filter-chip { flex:0 0 auto; padding:7px 12px; font-size:.72rem; white-space:nowrap; }
      }
    </style>
  </head>
  <body class="role-repairshop">
    <?php include __DIR__ . '/../includes/page-loader.php'; ?>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
        <h2 class="brand-name">FIX IT DAVAO</h2>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section" data-role="repairshop">
          <a href="shop-dashboard.php" class="nav-item active">
            <span class="nav-icon"><img src="../assets/icons/dashboard.svg" alt="Dashboard" onerror="this.style.display='none'" /></span>
            <span class="nav-text">Dashboard</span>
          </a>
          <a href="shop-information.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/shop.svg" alt="My Shop" /></span>
            <span class="nav-text">My Shop</span>
          </a>
          <a href="shop-bookings.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/booking.svg" alt="Bookings" /></span>
            <span class="nav-text">Bookings</span>
          </a>
          <a href="shop-services.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/services.svg" alt="Services" /></span>
            <span class="nav-text">Services &amp; Fees</span>
          </a>
          <a href="shop-reviews.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/reviews.svg" alt="Reviews" /></span>
            <span class="nav-text">Reviews</span>
          </a>
          <a href="shop-messages.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/talk.svg" alt="Messages" /></span>
            <span class="nav-text">Messages</span>
          </a>
          <a href="shop-subscription.php" class="nav-item">
            <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscription" /></span>
            <span class="nav-text">Subscription</span>
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

    <!-- Account Settings Modal -->
    <div class="modal-overlay" id="accountModal">
      <div class="modal-box" style="max-width:420px;padding:0;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #e2e8f0;">
          <span style="font-size:1.05rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif;">Account Settings</span>
          <button onclick="closeAccountModal()" style="background:#f1f5f9;border:none;width:28px;height:28px;border-radius:8px;cursor:pointer;font-size:14px;color:#64748b;">✕</button>
        </div>
        <div style="display:flex;gap:6px;padding:12px 22px 0;">
          <button id="acctTabProfile" class="acct-tab acct-tab-active" onclick="acctSwitch('profile')">Edit Profile</button>
          <button id="acctTabPass" class="acct-tab" onclick="acctSwitch('password')">Change Password</button>
        </div>
        <form id="acctProfileForm" onsubmit="return saveProfile(event)" style="padding:18px 22px 22px;">
          <div class="acct-field"><label>Full Name</label><input type="text" id="acctName" required /></div>
          <div class="acct-field"><label>Email</label><input type="email" id="acctEmail" required /></div>
          <div class="acct-field"><label>Contact Number</label><input type="text" id="acctContact" /></div>
          <div id="acctProfileMsg" class="acct-msg"></div>
          <button type="submit" class="acct-submit">Save Changes</button>
        </form>
        <form id="acctPassForm" onsubmit="return savePassword(event)" style="padding:18px 22px 22px;display:none;">
          <div class="acct-field"><label>Current Password</label><input type="password" id="acctCurrent" required /></div>
          <div class="acct-field"><label>New Password</label><input type="password" id="acctNew" required /></div>
          <div class="acct-field"><label>Confirm New Password</label><input type="password" id="acctConfirm" required /></div>
          <div id="acctPassMsg" class="acct-msg"></div>
          <button type="submit" class="acct-submit">Update Password</button>
        </form>
      </div>
    </div>

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">Dashboard</h1></div>
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
              <div class="notif-footer" style="padding:10px 16px;border-top:1px solid #f1f5f9;text-align:center;"><a href="shop-bookings.php" style="font-size:0.78rem;font-weight:700;color:#f59e0b;text-decoration:none;font-family:'Outfit',sans-serif;">View all bookings →</a></div>
            </div>
          </div>
          <div class="user-profile" onclick="openAccountModal()" style="cursor:pointer;" title="Account settings">
            <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" />
            <div class="user-info">
              <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
              <span class="user-role">Repair Shop</span>
            </div>
          </div>
        </div>
      </header>

      <div class="dashboard-content">

        <!-- ── PERIOD FILTER ── -->
        <div class="dash-filter" id="dashFilter">
          <button class="filter-chip active" data-range="all">All Time</button>
          <button class="filter-chip" data-range="year">This Year</button>
          <button class="filter-chip" data-range="3m">Last 3 Months</button>
          <button class="filter-chip" data-range="month">This Month</button>
        </div>

        <!-- ── STAT CARDS ── -->
        <div class="stats-grid-admin">
          <div class="stat-card stat-primary">
            <div class="stat-icon"><img src="../assets/icons/buk.svg" alt="Bookings" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statTotal">—</h3>
              <p class="stat-label">Total Bookings</p>
              <p class="stat-sub" id="statTotalSub"></p>
            </div>
            <span class="stat-badge warn" id="badgePending" style="display:none"></span>
          </div>
          <div class="stat-card stat-success">
            <div class="stat-icon"><img src="../assets/icons/corek.svg" alt="Completed" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statCompleted">—</h3>
              <p class="stat-label">Completed</p>
              <p class="stat-sub">jobs finished</p>
            </div>
          </div>
          <div class="stat-card stat-warning">
            <div class="stat-icon"><img src="../assets/icons/credit.svg" alt="Revenue" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statRevenue">—</h3>
              <p class="stat-label">Total Revenue</p>
              <p class="stat-sub">from finished jobs</p>
            </div>
          </div>
          <div class="stat-card stat-info">
            <div class="stat-icon"><img src="../assets/icons/star.svg" alt="Rating" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statRating">—</h3>
              <p class="stat-label">Avg Rating</p>
              <p class="stat-sub" id="statReviews"></p>
            </div>
          </div>
          <div class="stat-card stat-teal">
            <div class="stat-icon"><img src="../assets/icons/approve.svg" alt="Subscription" width="26" height="26" /></div>
            <div class="stat-info">
              <h3 class="stat-value" id="statSub" style="font-size:1.4rem;">—</h3>
              <p class="stat-label">Subscription</p>
              <p class="stat-sub" id="statSubEnd"></p>
            </div>
          </div>
        </div>

        <!-- ── OVERVIEW + ACTIVITY ── -->
        <div class="dashboard-grid">

          <!-- Business Overview -->
          <div class="dash-card">
            <div class="dash-card-title">
              <img src="../assets/icons/chart.svg" width="18" height="18" alt="" style="opacity:0.7" />
              Business Overview
              <span style="margin-left:auto;font-size:0.75rem;color:#94a3b8;font-weight:500" id="chartDateLabel"></span>
            </div>

            <!-- Row 1: Bar + Donut -->
            <div class="row-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
              <div>
                <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;" id="bookingsTrendTitle">Bookings — Last 6 Months</div>
                <div class="chart-wrap"><canvas id="bookingsChart"></canvas><div class="chart-empty-overlay" id="bookingsEmpty"><span class="empty-icon">📊</span>No bookings yet</div></div>
              </div>
              <div>
                <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;">Booking Status Breakdown</div>
                <div class="chart-wrap"><canvas id="statusChart"></canvas><div class="chart-empty-overlay" id="statusEmpty"><span class="empty-icon">🧾</span>No bookings yet</div></div>
              </div>
            </div>

            <!-- Row 2: Revenue line -->
            <div style="margin-top:1.25rem;">
              <div style="font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:8px;" id="revenueTrendTitle">Revenue — Last 6 Months (₱)</div>
              <div style="height:180px;position:relative;" class="chart-wrap">
                <canvas id="revenueChart"></canvas>
                <div class="chart-empty-overlay" id="revenueEmpty"><span class="empty-icon">💰</span>No revenue yet</div>
              </div>
            </div>

            <!-- Summary row -->
            <div style="display:flex;gap:1.5rem;margin-top:1.1rem;padding-top:1rem;border-top:1px solid #f1f5f9;flex-wrap:wrap;">
              <div><div style="font-size:1.35rem;font-weight:800;color:#0f172a" id="sumBookings">—</div><div style="font-size:0.75rem;color:#64748b">Bookings</div></div>
              <div><div style="font-size:1.35rem;font-weight:800;color:#10b981" id="sumRevenue">—</div><div style="font-size:0.75rem;color:#64748b">Revenue</div></div>
              <div><div style="font-size:1.35rem;font-weight:800;color:#3b82f6" id="sumRating">—</div><div style="font-size:0.75rem;color:#64748b">Avg Rating</div></div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="dash-card">
            <div class="dash-card-title"><img src="../assets/icons/clock.svg" width="18" height="18" alt="" style="opacity:0.7" /> Recent Activity</div>
            <div class="activity-list" id="activityList">
              <div style="display:flex;flex-direction:column;gap:10px">
                <div class="skeleton" style="height:40px"></div>
                <div class="skeleton" style="height:40px"></div>
                <div class="skeleton" style="height:40px"></div>
              </div>
            </div>
          </div>

        </div>

      </div>
      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script src="../assets/js/chart.umd.min.js"></script>
    <script>
      // ── Sidebar toggle ──
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      const backdrop = document.getElementById("sidebarBackdrop");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        if (backdrop) backdrop.addEventListener("click", () => { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); });
      }
      function confirmLogout(e){ e.preventDefault(); sidebar.classList.remove('active'); document.body.classList.remove('sidebar-open'); document.getElementById('logoutModal').classList.add('visible'); return false; }
      function closeLogoutModal(){ document.getElementById('logoutModal').classList.remove('visible'); }

      // ── Shared helpers ──
      const peso = n => '₱' + Number(n||0).toLocaleString('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0});
      const SUB_LABELS = { active:'Active', pending:'Pending', expired:'Expired', rejected:'Rejected', cancelled:'Cancelled', refunded:'Refunded', none:'None' };
      const brandTooltip = {
        enabled:true, backgroundColor:"#020617", titleColor:"#fbbf24", bodyColor:"#f8fafc",
        borderColor:"rgba(245,158,11,0.35)", borderWidth:1, cornerRadius:8, padding:10,
        titleFont:{family:"Outfit",weight:"700",size:12}, bodyFont:{family:"Outfit",size:12}, displayColors:true, boxPadding:4,
      };
      function animateCount(el, target) {
        let start = 0; const duration = 700;
        const step = (ts) => { if (!start) start = ts; const p = Math.min((ts-start)/duration, 1); el.textContent = Math.floor(p*target); if (p<1) requestAnimationFrame(step); else el.textContent = target; };
        requestAnimationFrame(step);
      }
      function animateMoney(el, target) {
        let start = 0; const duration = 700;
        const step = (ts) => { if (!start) start = ts; const p = Math.min((ts-start)/duration, 1); el.textContent = peso(Math.floor(p*target)); if (p<1) requestAnimationFrame(step); else el.textContent = peso(target); };
        requestAnimationFrame(step);
      }
      function timeAgo(dateStr) {
        if (!dateStr) return "";
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60) return "just now";
        if (diff < 3600) return Math.floor(diff/60) + "m ago";
        if (diff < 86400) return Math.floor(diff/3600) + "h ago";
        return Math.floor(diff/86400) + "d ago";
      }

      // ── Charts ──
      let bookingsChartObj, statusChartObj, revenueChartObj;
      let currentRange = 'all';

      async function loadStats(range = currentRange) {
        currentRange = range;
        let d;
        try {
          const res = await fetch('../api/get_shop_stats.php?range=' + encodeURIComponent(range));
          d = await res.json();
          if (d.error) throw new Error(d.error);
        } catch(e) { console.error('stats load failed', e); return; }

        // Stat cards
        animateCount(document.getElementById('statTotal'), d.totalBookings);
        animateCount(document.getElementById('statCompleted'), d.completed);
        document.getElementById('statTotalSub').textContent = d.pending + ' pending · ' + d.confirmed + ' confirmed';
        document.getElementById('statRevenue').textContent  = peso(d.totalRevenue);
        document.getElementById('statRating').textContent   = d.reviewCount ? (d.avgRating + ' ★') : '—';
        document.getElementById('statReviews').textContent  = d.reviewCount + ' review' + (d.reviewCount === 1 ? '' : 's');
        document.getElementById('statSub').textContent      = SUB_LABELS[d.subStatus] || d.subStatus;
        document.getElementById('statSubEnd').textContent   = (d.subStatus === 'active' && d.subEndDate) ? ('until ' + d.subEndDate) : '';

        const badge = document.getElementById('badgePending');
        if (d.pending > 0) { badge.style.display = ''; badge.textContent = d.pending + ' pending'; }
        else badge.style.display = 'none';

        // Summary row
        document.getElementById('sumBookings').textContent = d.totalBookings;
        document.getElementById('sumRevenue').textContent  = peso(d.totalRevenue);
        document.getElementById('sumRating').textContent   = d.reviewCount ? (d.avgRating + ' ★') : '—';

        // Date label
        document.getElementById('chartDateLabel').textContent = new Date().toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });

        // Bookings trend (bar)
        document.getElementById('bookingsEmpty').classList.toggle('show', !(d.totalBookings > 0));
        if (bookingsChartObj) bookingsChartObj.destroy();
        bookingsChartObj = new Chart(document.getElementById('bookingsChart'), {
          type: 'bar',
          data: { labels: d.trendLabels, datasets: [{ label:'Bookings', data:d.trendBookings, backgroundColor:'rgba(245,158,11,0.85)', borderColor:'#f59e0b', borderWidth:2, borderRadius:6, borderSkipped:false, maxBarThickness:38 }] },
          options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:brandTooltip }, scales:{ x:{ grid:{display:false}, ticks:{ font:{size:11,family:'Outfit'}, color:'#94a3b8' } }, y:{ grid:{color:'#f1f5f9'}, beginAtZero:true, ticks:{ precision:0, font:{size:11,family:'Outfit'}, color:'#94a3b8' } } } }
        });

        // Status breakdown (doughnut)
        const sb = d.statusBreakdown;
        const sbTotal = sb.pending + sb.confirmed + sb.completed + sb.paid + sb.claimed + sb.cancelled + sb.no_show;
        document.getElementById('statusEmpty').classList.toggle('show', !sbTotal);
        if (statusChartObj) statusChartObj.destroy();
        statusChartObj = new Chart(document.getElementById('statusChart'), {
          type: 'doughnut',
          data: {
            labels: ['Pending','Confirmed','Completed','Paid','Claimed','Cancelled','No-show'],
            datasets: [{ data:[sb.pending, sb.confirmed, sb.completed, sb.paid, sb.claimed, sb.cancelled, sb.no_show],
              backgroundColor:['#f59e0b','#10b981','#3b82f6','#14b8a6','#6366f1','#ef4444','#a855f7'], borderWidth:2, borderColor:'#fff', hoverOffset:6 }]
          },
          options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:10, font:{size:11,family:'Outfit'}, color:'#64748b' } }, tooltip:brandTooltip } }
        });

        // Revenue trend (line)
        document.getElementById('revenueEmpty').classList.toggle('show', !d.trendRevenue.some(v => v > 0));
        if (revenueChartObj) revenueChartObj.destroy();
        revenueChartObj = new Chart(document.getElementById('revenueChart'), {
          type: 'line',
          data: { labels: d.trendLabels, datasets: [{ label:'Revenue', data:d.trendRevenue, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.12)', borderWidth:2.5, fill:true, tension:0.4, pointRadius:(c)=>c.raw>0?4:0, pointHoverRadius:6, pointBackgroundColor:'#10b981' }] },
          options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{ ...brandTooltip, callbacks:{ label:c => peso(c.parsed.y) } } }, scales:{ x:{ grid:{display:false}, ticks:{ font:{size:11,family:'Outfit'}, color:'#94a3b8' } }, y:{ beginAtZero:true, grid:{color:'#f1f5f9'}, ticks:{ font:{size:11,family:'Outfit'}, color:'#94a3b8', callback:v => peso(v) } } } }
        });
      }
      loadStats();
      setInterval(() => loadStats(currentRange), 30000); // auto-refresh like admin-dashboard (keeps active filter)

      // ── Period filter chips ──
      document.querySelectorAll('#dashFilter .filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
          if (chip.classList.contains('active')) return;
          document.querySelectorAll('#dashFilter .filter-chip').forEach(c => c.classList.remove('active'));
          chip.classList.add('active');
          loadStats(chip.dataset.range);
        });
      });

      // ── Activity + Notification icons ──
      const ICON = {
        green:  `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
        red:    `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>`,
        amber:  `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><path d="M12 7v5l3 2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
        blue:   `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#3b82f6"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
        violet: `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><path d="M12 7l1.5 3.2 3.5.4-2.6 2.4.7 3.5L12 15.3 8.9 16.9l.7-3.5L7 11l3.5-.4z" fill="white"/></svg>`,
        teal:   `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#14b8a6"/><rect x="7" y="9" width="10" height="7" rx="1.5" fill="white"/><rect x="7" y="10.5" width="10" height="1.6" fill="#14b8a6"/></svg>`,
        chat:   `<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#6366f1"/><path d="M7 9.5h10v5H12l-3 2.5v-2.5H7z" fill="white"/></svg>`,
      };
      function mapActivity(n) {
        if (n.type === 'message')      return { label:'New Message', icon:ICON.chat, dest:'shop-messages.php' + (n.other_id ? '?open=' + n.other_id : ''), name:n.customer_name || 'Customer' };
        if (n.type === 'review')       return { label:'New Review', icon:ICON.violet, dest:'shop-reviews.php', name:n.customer_name || 'Customer' };
        if (n.type === 'subscription') return { label:'Subscription ' + (n.status==='active'?'Approved':'Update'), icon:ICON.teal, dest:'shop-subscription.php', name:n.plan_name || 'Subscription' };
        if (n.type === 'system')       return { label:n.sys_title || 'System Notice', icon:ICON.amber, dest:(n.link ? '../' + n.link : 'shop-dashboard.php'), name:n.sys_body || '' };
        if (n.type === 'reschedule')   return { label:'Booking Rescheduled', icon:ICON.amber, dest:'shop-bookings.php', name:n.customer_name || 'Customer' };
        const m = {
          pending:   { label:'New Booking',        icon:ICON.amber },
          confirmed: { label:'Booking Confirmed',  icon:ICON.green },
          completed: { label:'Booking Completed',  icon:ICON.blue  },
          cancelled: { label:'Booking Cancelled',  icon:ICON.red   },
        }[n.status] || { label:'Booking Update', icon:ICON.blue };
        return { ...m, dest:'shop-bookings.php', name:n.customer_name || 'Customer' };
      }

      // ── Notifications + Activity (shared fetch) ──
      const notifBtn = document.getElementById('notifBtn');
      const notifDropdown = document.getElementById('notifDropdown');
      const notifBadge = document.getElementById('notifBadge');
      const notifList = document.getElementById('notifList');
      let notifOpen = false;

      function renderActivity(items) {
        const list = document.getElementById('activityList');
        if (!items || !items.length) {
          list.innerHTML = `<div class="empty-activity"><img src="../assets/icons/bell.svg" width="40" style="opacity:0.25"><p>No recent activity yet.</p></div>`;
          return;
        }
        list.innerHTML = items.slice(0, 8).map(n => {
          const a = mapActivity(n);
          return `<div class="activity-item" onclick="window.location.href='${a.dest}'">
            <div class="activity-dot">${a.icon}</div>
            <div class="activity-content">
              <div class="activity-label">${a.label}</div>
              <div class="activity-name">${a.name}</div>
              <div class="activity-time">${timeAgo(n.time)}</div>
            </div>
          </div>`;
        }).join('');
      }

      function renderBell(items) {
        if (!items || !items.length) {
          notifList.innerHTML = `<div class="notif-empty">🎉 No notifications yet.</div>`;
          return;
        }
        notifList.innerHTML = items.map(n => {
          const a = mapActivity(n);
          const unread = !n.is_read;
          return `<div class="notif-item ${unread ? 'unread' : ''}" onclick="window.location.href='${a.dest}'">
            <div class="notif-dot-icon">${a.icon}</div>
            <div class="notif-content">
              <div class="notif-title">${a.label}</div>
              <div class="notif-name">${a.name}</div>
              <div class="notif-time">${timeAgo(n.time)}</div>
            </div>
            ${unread ? '<div class="notif-unread-dot"></div>' : ''}
          </div>`;
        }).join('');
      }

      async function loadNotifs() {
        try {
          const res = await fetch('../api/get_shop_notifications.php');
          const data = JSON.parse(await res.text());
          if (!data.success) throw new Error('fail');
          const items = data.notifications || [];
          if (data.unread_count > 0) { notifBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count; notifBadge.classList.add('show'); }
          else notifBadge.classList.remove('show');
          renderBell(items);
          renderActivity(items);
        } catch(e) {
          notifList.innerHTML = `<div class="notif-empty">No notifications yet.</div>`;
          renderActivity([]);
        }
      }
      async function markAllRead() {
        await fetch('../api/get_shop_notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ mark_read:true }) });
        notifBadge.classList.remove('show');
        notifList.querySelectorAll('.notif-item.unread').forEach(i => { i.classList.remove('unread'); const d=i.querySelector('.notif-unread-dot'); if (d) d.remove(); });
      }
      notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifOpen = !notifOpen;
        notifDropdown.classList.toggle('open', notifOpen);
        if (notifOpen) { loadNotifs(); }
      });
      document.getElementById('markAllRead').addEventListener('click', markAllRead);
      document.addEventListener('click', (e) => {
        if (!notifBtn.closest('.notif-wrapper').contains(e.target)) { notifOpen = false; notifDropdown.classList.remove('open'); }
      });
      loadNotifs();
      setInterval(loadNotifs, 60000);
    </script>
    <script>
    (function(){
      const CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
      const INIT = { name: <?php echo json_encode($acctName); ?>, email: <?php echo json_encode($acctEmail); ?>, contact: <?php echo json_encode($acctContact); ?> };
      function acctMsg(id, text, ok){ const el=document.getElementById(id); if(!text){el.style.display='none';return;} el.style.display='block'; el.textContent=text; el.style.background=ok?'#d1fae5':'#fee2e2'; el.style.color=ok?'#065f46':'#991b1b'; }
      window.acctSwitch = function(which){ const p=which==='profile'; document.getElementById('acctProfileForm').style.display=p?'block':'none'; document.getElementById('acctPassForm').style.display=p?'none':'block'; document.getElementById('acctTabProfile').classList.toggle('acct-tab-active',p); document.getElementById('acctTabPass').classList.toggle('acct-tab-active',!p); };
      window.openAccountModal = function(){ document.getElementById('acctName').value=INIT.name||''; document.getElementById('acctEmail').value=INIT.email||''; document.getElementById('acctContact').value=INIT.contact||''; document.getElementById('acctCurrent').value=''; document.getElementById('acctNew').value=''; document.getElementById('acctConfirm').value=''; acctMsg('acctProfileMsg',''); acctMsg('acctPassMsg',''); acctSwitch('profile'); document.getElementById('accountModal').classList.add('visible'); };
      window.closeAccountModal = function(){ document.getElementById('accountModal').classList.remove('visible'); };
      async function post(payload){ const res=await fetch('../api/update_account.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF},payload))}); return res.json(); }
      window.saveProfile = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'update_profile',name:document.getElementById('acctName').value.trim(),email:document.getElementById('acctEmail').value.trim(),contact_number:document.getElementById('acctContact').value.trim()}); acctMsg('acctProfileMsg',d.message||d.error,!!d.success); if(d.success){INIT.name=d.name;INIT.email=d.email;document.querySelectorAll('[data-acct-name]').forEach(el=>el.textContent=d.name);} }catch(err){ acctMsg('acctProfileMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
      window.savePassword = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'change_password',current_password:document.getElementById('acctCurrent').value,new_password:document.getElementById('acctNew').value,confirm_password:document.getElementById('acctConfirm').value}); acctMsg('acctPassMsg',d.message||d.error,!!d.success); if(d.success){document.getElementById('acctCurrent').value='';document.getElementById('acctNew').value='';document.getElementById('acctConfirm').value='';} }catch(err){ acctMsg('acctPassMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
      const ov=document.getElementById('accountModal'); if(ov) ov.addEventListener('click',function(e){ if(e.target===this) closeAccountModal(); });
    })();
    </script>
    <script>
      setTimeout(() => { window.location.href = "../login.php?timeout=1"; }, 1800000);
    </script>
  </body>
</html>