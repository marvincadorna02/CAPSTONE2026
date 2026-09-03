<?php
session_start();
require_once __DIR__ . '/../includes/guard.php';

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
if ($_SESSION['role'] !== 'customer') { header("Location: ../shop-owner/dashboard.php"); exit(); }

$shopId   = (int)($_GET['id'] ?? 0);
if (!$shopId) { header("../shop-owner/dashboard.php"); exit(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { die("DB error"); }

// ── Fetch shop info ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT name, email, shop_location, contact_number, logo_url FROM users WHERE id = ? AND role = 'repairshop' AND status = 'active' AND approval_status = 'approved'");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shop) { header("../shop-owner/dashboard.php"); exit(); }

// ── Fetch services ───────────────────────────────────────────
$services = [];
$sr = $conn->prepare("SELECT id, service_name, service_fee, service_duration FROM services WHERE user_id = ? ORDER BY id ASC");
$sr->bind_param("i", $shopId);
$sr->execute();
$sResult = $sr->get_result();
while ($s = $sResult->fetch_assoc()) $services[] = $s;
$sr->close();

// ── Fetch operating hours ────────────────────────────────────
$hours = [];
$hr = $conn->prepare("SELECT day, open_time, close_time FROM operating_hours WHERE user_id = ? ORDER BY FIELD(day,'monday','tuesday','wednesday','thursday','friday','saturday','sunday')");
$hr->bind_param("i", $shopId);
$hr->execute();
$hResult = $hr->get_result();
while ($h = $hResult->fetch_assoc()) $hours[strtolower($h['day'])] = $h;
$hr->close();
$conn->close();

// Build absolute logo URL
$baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http')
         .'://'.$_SERVER['HTTP_HOST']
         .rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\').'/';
$logoUrl = !empty($shop['logo_url'])
    ? $baseUrl . $shop['logo_url']
    : "https://ui-avatars.com/api/?name=".urlencode($shop['name'])."&background=f59e0b&color=fff&size=128";

// ── Fetch customer's actual profile picture (falls back to initials avatar) ──
$avatarUrl = "https://ui-avatars.com/api/?name=".urlencode($userName)."&background=2563eb&color=fff";
$connAvatar = new mysqli("localhost", "root", "", "fixitdavao");
if (!$connAvatar->connect_error) {
    $avStmt = $connAvatar->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $avStmt->bind_param("i", $userId);
    $avStmt->execute();
    $avRow = $avStmt->get_result()->fetch_assoc();
    $avStmt->close();
    $connAvatar->close();
    if (!empty($avRow['profile_picture'])) {
        $avatarUrl = $avRow['profile_picture'];
    }
}

// Open days for JS
$openDays = array_keys($hours);
$hoursJson = json_encode($hours);
$servicesJson = json_encode($services);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Book <?php echo htmlspecialchars($shop['name']); ?> - Fix It Davao</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
<script src="../assets/js/ai_suggest_widget.js"></script>  <!-- ADD THIS -->
  <style>
    .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:.5px; font-family:"Outfit",sans-serif; font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }

    /* ── BOOKING LAYOUT ── */
    .booking-wrapper {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 1.5rem;
      align-items: start;
    }

    /* ── SHOP SUMMARY CARD ── */
    .shop-summary-card {
      background: white; border-radius: 18px;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      overflow: hidden;
      position: sticky; top: 24px;
      animation: fadeInUp 0.4s ease both;
    }
    .shop-summary-banner {
      height: 72px;
      background: linear-gradient(135deg,#fef9ee,#fef3c7,#fde68a);
      position: relative;
    }
    .shop-summary-banner::before {
      content:''; position:absolute; inset:0;
      background-image: radial-gradient(circle,rgba(245,158,11,.1) 1px,transparent 1px);
      background-size: 18px 18px;
    }
    .shop-summary-logo-wrap {
      position: absolute; bottom:-22px; left:1.1rem;
      width:52px; height:52px; border-radius:14px;
      border:3px solid #fff; box-shadow:0 4px 14px rgba(0,0,0,.13);
      overflow:hidden; background:#fef3c7; z-index:1;
    }
    .shop-summary-logo-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
    .shop-summary-body { padding: 32px 1.1rem 1.1rem; }
    .shop-summary-name { font-size:1.05rem; font-weight:800; color:#0f172a; margin-bottom:4px; }
    .shop-summary-meta { display:flex; flex-direction:column; gap:5px; margin-bottom:1rem; }
    .shop-summary-row  { display:flex; align-items:flex-start; gap:7px; font-size:0.8rem; color:#475569; }
    .shop-summary-row img { margin-top:2px; flex-shrink:0; opacity:.55; }

    /* Hours inside summary */
    .hours-mini-title { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:#94a3b8; margin-bottom:7px; }
    .hours-mini-grid  { display:flex; flex-direction:column; gap:4px; }
    .hours-mini-row   { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:#475569; padding:5px 8px; border-radius:8px; background:#f8fafc; }
    .hours-mini-row.today-row { background:#fffbeb; border:1px solid #fde68a; color:#92400e; font-weight:600; }
    .hours-mini-row.closed-row { opacity:.4; }
    .h-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .h-dot.open   { background:#10b981; }
    .h-dot.closed { background:#cbd5e1; }
    .h-day  { width:80px; font-weight:600; flex-shrink:0; font-size:0.75rem; line-height:1.3; }
    .h-time { font-size:0.75rem; }
    .today-badge { font-size:0.58rem; font-weight:700; background:#f59e0b; color:white; padding:1px 5px; border-radius:4px; margin-left:2px; }

    /* ── BOOKING FORM CARD ── */
    .booking-form-card {
      background: white; border-radius: 18px;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      padding: 1.75rem;
      animation: fadeInUp 0.5s ease both;
    }
    .booking-form-card h2 { font-size:1.15rem; font-weight:800; color:#0f172a; margin-bottom:4px; }
    .booking-form-card p  { font-size:0.85rem; color:#64748b; margin-bottom:1.5rem; }

    .form-section-title {
      font-size:0.68rem; font-weight:800; text-transform:uppercase;
      letter-spacing:.7px; color:#94a3b8; margin-bottom:10px; margin-top:1.25rem;
      display:flex; align-items:center; gap:6px;
    }
    .form-section-title:first-of-type { margin-top:0; }
    .form-section-title img { opacity:.5; }

    .bform-group { margin-bottom:1rem; }
    .bform-group label { display:block; font-size:0.82rem; font-weight:700; color:#374151; margin-bottom:6px; }
    .bform-group input,
    .bform-group select,
    .bform-group textarea {
      width:100%; padding:0.7rem 0.9rem;
      border:2px solid #e2e8f0; border-radius:10px;
      font-size:0.875rem; font-family:"Outfit",sans-serif;
      color:#0f172a; background:#f8fafc;
      transition:border-color .2s, box-shadow .2s;
      box-sizing:border-box;
    }
    .bform-group input:focus,
    .bform-group select:focus,
    .bform-group textarea:focus {
      outline:none; border-color:#f59e0b; background:white;
      box-shadow:0 0 0 3px rgba(245,158,11,.12);
    }
    .bform-group textarea { resize:vertical; min-height:90px; }
    .bform-group input.error,
    .bform-group select.error { border-color:#ef4444; background:#fff5f5; }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }

    /* Service selector cards */
    .service-selector { display:flex; flex-direction:column; gap:8px; }
    .service-option {
      display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:10px;
      border:2px solid #e2e8f0; cursor:pointer;
      transition:all .2s ease; background:#f8fafc;
    }
    .service-option:hover { border-color:#fbbf24; background:#fffbeb; }
    .service-option.selected { border-color:#f59e0b; background:#fffbeb; box-shadow:0 0 0 3px rgba(245,158,11,.12); }
    .service-option input[type="radio"] { display:none; }
    .service-radio-dot {
      width:18px; height:18px; border-radius:50%;
      border:2px solid #cbd5e1; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      transition:all .2s;
    }
    .service-option.selected .service-radio-dot { border-color:#f59e0b; background:#f59e0b; }
    .service-option.selected .service-radio-dot::after { content:''; width:6px; height:6px; background:white; border-radius:50%; display:block; }
    .service-option-name { flex:1; font-size:0.85rem; font-weight:600; color:#374151; }
    .service-option-fee  { font-size:0.82rem; font-weight:700; color:#d97706; background:#fef3c7; padding:2px 8px; border-radius:6px; }
    .service-option-dur  { font-size:0.72rem; color:#94a3b8; margin-top:1px; }
    .no-services-msg { font-size:.85rem; color:#94a3b8; font-style:italic; padding:8px; }

    /* Date picker — disable past dates via JS */
    .date-hint { font-size:0.75rem; color:#94a3b8; margin-top:4px; }
    .date-hint.warn { color:#f59e0b; }
    .date-hint.ok   { color:#10b981; }
    .date-hint.checking { color:#94a3b8; }
    .date-hint.taken { color:#ef4444; font-weight:600; }

    /* Summary box before submit */
    .booking-summary-box {
      background:#f8fafc; border:1.5px solid #e2e8f0;
      border-radius:12px; padding:1rem 1.1rem;
      margin:1.25rem 0 1rem; display:none;
    }
    .booking-summary-box.show { display:block; }
    .bsum-row { display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; padding:4px 0; }
    .bsum-row:not(:last-child) { border-bottom:1px solid #f1f5f9; padding-bottom:6px; margin-bottom:6px; }
    .bsum-label { color:#64748b; }
    .bsum-value { font-weight:700; color:#0f172a; }

    /* Submit button */
    .btn-submit-booking {
      width:100%; padding:.8rem; border:none; border-radius:12px;
      background:linear-gradient(135deg,#f59e0b,#d97706);
      color:white; font-size:.9rem; font-weight:800;
      font-family:"Outfit",sans-serif; cursor:pointer;
      box-shadow:0 4px 14px rgba(245,158,11,.35);
      transition:all .22s ease; margin-top:.5rem;
    }
    .btn-submit-booking:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(245,158,11,.45); }
    .btn-submit-booking:disabled { opacity:.6; cursor:not-allowed; transform:none; }
    .btn-back {
      display:inline-flex; align-items:center; gap:6px;
      font-size:.82rem; font-weight:700; color:#64748b;
      text-decoration:none; margin-bottom:1rem;
      padding:6px 10px; border-radius:8px;
      transition:background .15s;
    }
    .btn-back:hover { background:#f1f5f9; color:#374151; }

    /* Success overlay */
    .success-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(10,15,30,.75); backdrop-filter:blur(4px);
      z-index:2000; align-items:center; justify-content:center; padding:20px;
    }
    .success-overlay.show { display:flex; }
    .success-box {
      background:white; border-radius:22px; padding:2.5rem 2rem;
      max-width:400px; width:100%; text-align:center;
      box-shadow:0 40px 100px rgba(0,0,0,.25);
      animation:popIn .4s cubic-bezier(0.34,1.56,0.64,1) both;
    }
    @keyframes popIn { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
    .success-icon { font-size:3.5rem; margin-bottom:1rem; }
    .success-title { font-size:1.3rem; font-weight:800; color:#0f172a; margin-bottom:.5rem; }
    .success-sub   { font-size:.875rem; color:#64748b; margin-bottom:1.5rem; line-height:1.5; }
    .success-btn   {
      display:inline-block; padding:.75rem 2rem;
      background:linear-gradient(135deg,#f59e0b,#d97706);
      color:white; font-weight:700; border-radius:12px;
      text-decoration:none; font-family:"Outfit",sans-serif;
      box-shadow:0 4px 14px rgba(245,158,11,.35);
      transition:all .2s;
    }
    .success-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(245,158,11,.45); }

    /* LOGOUT MODAL */
    .modal-overlay { position:fixed; inset:0; background:rgba(10,15,30,.72); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:opacity .3s ease; padding:20px; }
    .modal-overlay.visible { opacity:1; pointer-events:all; }
    .modal-box { background:white; border-radius:20px; padding:32px 28px; max-width:420px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,.25); transform:scale(.9) translateY(20px); opacity:0; transition:transform .35s cubic-bezier(0.34,1.56,0.64,1), opacity .3s ease; }
    .modal-overlay.visible .modal-box { transform:scale(1) translateY(0); opacity:1; }
    .modal-title { font-size:18px; font-weight:800; color:#0f172a; margin-bottom:6px; font-family:var(--font-primary); }
    .modal-subtitle { font-size:13px; color:#64748b; }
    .modal-actions { display:flex; gap:10px; margin-top:20px; justify-content:center; }
    .modal-btn-cancel { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:var(--font-primary); cursor:pointer; color:#64748b; transition:all .2s; }
    .modal-btn-cancel:hover { background:#f8fafc; }
    .modal-btn-confirm { flex:1; padding:11px; border:none; border-radius:10px; color:white; font-size:13px; font-weight:700; font-family:var(--font-primary); cursor:pointer; transition:all .2s; }
    .modal-btn-confirm:hover { transform:translateY(-1px); opacity:.9; }

    @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

    @media (max-width: 900px) {
      .booking-wrapper { grid-template-columns: 1fr; }
      .shop-summary-card { position:static; }
    }

    @media (max-width: 768px) {
  .booking-wrapper {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .booking-form-card {
    padding: 1.25rem 1rem;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: .75rem;
  }

  .shop-summary-card {
    position: static;
  }

  .btn-back {
    margin-bottom: .75rem;
  }

  .service-option {
    padding: 9px 10px;
  }

  .service-option-name {
    font-size: .8rem;
  }

  .bform-group input,
  .bform-group select,
  .bform-group textarea {
    font-size: .875rem;
  }

  .btn-submit-booking {
    padding: .75rem;
    font-size: .85rem;
  }

  .booking-summary-box {
    margin: 1rem 0 .75rem;
  }
}

@media (max-width: 480px) {
  .booking-form-card {
    padding: 1rem .875rem;
  }

  .shop-summary-body {
    padding: 28px .875rem .875rem;
  }

  .hours-mini-grid {
    gap: 3px;
  }

  .hours-mini-row {
    padding: 4px 6px;
    font-size: .72rem;
  }
}
/* ── NOTIFICATIONS ── */
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
  border:1.5px solid #e2e8f0; box-shadow:0 10px 40px rgba(0,0,0,.15);
  z-index:500; overflow:hidden; opacity:0; pointer-events:none;
  transform:translateY(-8px) scale(0.97);
  transition:opacity 0.22s ease, transform 0.22s ease;
}
.notif-dropdown.open { opacity:1; pointer-events:all; transform:translateY(0) scale(1); }
.notif-header { display:flex; justify-content:space-between; align-items:center; padding:.75rem 1rem; border-bottom:1px solid #f1f5f9; }
.notif-title { font-size:.85rem; font-weight:800; color:#0f172a; }
.notif-mark-read { font-size:.72rem; font-weight:700; color:#f59e0b; background:none; border:none; cursor:pointer; font-family:"Outfit",sans-serif; padding:3px 8px; border-radius:6px; }
.notif-list { max-height:340px; overflow-y:auto; }
.notif-item { display:flex; align-items:flex-start; gap:.75rem; padding:.85rem 1rem; border-bottom:1px solid #f8fafc; cursor:pointer; transition:background .15s; }
.notif-item:hover { background:#f8fafc; }
.notif-item.unread { background:#eff6ff; }
.notif-logo { width:36px; height:36px; border-radius:10px; object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0; }
.notif-content { flex:1; }
.notif-message { font-size:.8rem; font-weight:600; color:#0f172a; line-height:1.4; }
.notif-message span { font-weight:800; }
.notif-time { font-size:.7rem; color:#94a3b8; margin-top:2px; }
.notif-dot { width:8px; height:8px; border-radius:50%; background:#3b82f6; flex-shrink:0; margin-top:4px; }
.notif-loading { padding:1.5rem; text-align:center; font-size:.83rem; color:#94a3b8; }
.notif-empty { padding:2rem 1rem; text-align:center; font-size:.83rem; color:#94a3b8; }

@media (max-width: 768px) {
  .notif-dropdown {
    position:fixed !important; top:70px !important;
    left:8px !important; right:8px !important;
    width:auto !important; max-width:100% !important;
    max-height:70vh !important; overflow-y:auto !important;
    z-index:600 !important;
  }
}

@media (max-width: 768px) {
  .mobile-menu-toggle {
    display: flex !important;
    z-index: 1200 !important;
  }
  .sidebar {
    z-index: 1100 !important;
  }
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
        <a href="../shop-owner/dashboard.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/find.svg" alt="" /></span><span class="nav-text">Find Repair Shops</span></a>
        <a href="my-bookings.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/book.svg" alt="" /></span><span class="nav-text">My Bookings</span></a>
        <a href="favorites.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/favorite.svg" alt="" /></span><span class="nav-text">Favorites</span></a>
        <a href="history.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/history.svg" alt="" /></span><span class="nav-text">History</span></a>
        <a href="messages.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/talk.svg" alt="" /></span><span class="nav-text">Messages</span></a>
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

  <!-- Success Overlay -->
  <div class="success-overlay" id="successOverlay">
    <div class="success-box">
      <div class="success-icon">🎉</div>
      <div class="success-title">Booking Submitted!</div>
      <div class="success-sub">Your booking request has been sent to <strong><?php echo htmlspecialchars($shop['name']); ?></strong>. They'll confirm it shortly.</div>
      <a href="my-bookings.php" class="success-btn">View My Bookings</a>
    </div>
  </div>

  <main class="main-content">
    <header class="top-bar">
      <div class="page-header">
        <h1 class="current-page-title">Book a Service</h1>
      </div>
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
        <div class="user-profile">
          <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" />
          <div class="user-info">
            <span class="user-name" data-acct-name><?php echo htmlspecialchars($userName); ?></span>
            <span class="user-role">Customer</span>
          </div>
        </div>
      </div>
    </header>

    <div class="dashboard-content">
      <a href="../shop-owner/dashboard.php" class="btn-back">
        <img src="../assets/icons/find.svg" width="14" height="14" alt="" style="opacity:.6;" /> Back to Shops
      </a>

      <div class="booking-wrapper">

        <!-- ── Left: Shop Summary ── -->
        <div class="shop-summary-card">
          <div class="shop-summary-banner">
            <div class="shop-summary-logo-wrap">
              <img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlspecialchars($shop['name']); ?>"
                   onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($shop['name']); ?>&background=f59e0b&color=fff&size=128'" />
            </div>
          </div>
          <div class="shop-summary-body">
            <div class="shop-summary-name"><?php echo htmlspecialchars($shop['name']); ?></div>
            <div class="shop-summary-meta">
              <?php if (!empty($shop['shop_location'])): ?>
              <div class="shop-summary-row">
                <img src="../assets/icons/location.svg" width="13" height="13" alt="" />
                <span><?php echo htmlspecialchars($shop['shop_location']); ?></span>
              </div>
              <?php endif; ?>
              <?php if (!empty($shop['contact_number'])): ?>
              <div class="shop-summary-row">
                <img src="../assets/icons/mobile.svg" width="13" height="13" alt="" />
                <span><?php echo htmlspecialchars($shop['contact_number']); ?></span>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($hours)): ?>
            <div class="hours-mini-title">
              <img src="../assets/icons/history.svg" width="11" height="11" alt="" style="opacity:.5;vertical-align:middle;margin-right:3px;" />
              Operating Hours
            </div>
            <div class="hours-mini-grid">
              <?php
              $allDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
              $todayIdx = date('N') - 1; // 0=Mon ... 6=Sun
              $todayName = $allDays[$todayIdx];
              foreach ($allDays as $day):
                $isToday  = $day === $todayName;
                $isOpen   = isset($hours[$day]);
                $dayLabel = ucfirst($day);
                $rowClass = $isToday ? 'today-row' : ($isOpen ? '' : 'closed-row');
              ?>
              <div class="hours-mini-row <?php echo $rowClass; ?>">
                <div class="h-dot <?php echo $isOpen ? 'open' : 'closed'; ?>"></div>
                <span class="h-day">
                  <?php echo $dayLabel; ?>
                  <?php if($isToday): ?><br><span class="today-badge">TODAY</span><?php endif; ?>
                </span>
                <span class="h-time">
                  <?php if ($isOpen): ?>
                    <?php
                    $o = date('g:i A', strtotime($hours[$day]['open_time']));
                    $c = date('g:i A', strtotime($hours[$day]['close_time']));
                    echo "$o – $c";
                    ?>
                  <?php else: ?>
                    <span style="color:#ef4444;font-size:.72rem;">Closed</span>
                  <?php endif; ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── Right: Booking Form ── -->
        <div class="booking-form-card">
          <h2>Complete Your Booking</h2>
          <p>Fill in the details below and we'll send your request to the shop.</p>

          <form id="bookingForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <input type="hidden" name="shop_id" value="<?php echo $shopId; ?>" />
            <input type="hidden" name="customer_id" value="<?php echo $userId; ?>" />
            <input type="hidden" name="service_id" id="selectedServiceId" value="" />

            <!-- Select Service -->
            <div class="form-section-title">
              <img src="../assets/icons/services.svg" width="12" height="12" alt="" />
              Select a Service
            </div>
            <?php if (!empty($services)): ?>
            <div class="service-selector" id="serviceSelector">
              <?php foreach ($services as $svc): ?>
              <label class="service-option" data-id="<?php echo $svc['id']; ?>" data-name="<?php echo htmlspecialchars($svc['service_name']); ?>" data-fee="<?php echo $svc['service_fee']; ?>">
                <input type="radio" name="service_radio" value="<?php echo $svc['id']; ?>" />
                <div class="service-radio-dot"></div>
                <div style="flex:1;">
                  <div class="service-option-name"><?php echo htmlspecialchars($svc['service_name']); ?></div>
                  <?php if (!empty($svc['service_duration'])): ?>
                  <div class="service-option-dur"><?php echo htmlspecialchars($svc['service_duration']); ?></div>
                  <?php endif; ?>
                </div>
                <span class="service-option-fee">₱<?php echo number_format($svc['service_fee'], 0); ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="no-services-msg">This shop hasn't listed services yet. You can still book and describe your issue below.</p>
            <?php endif; ?>

            <!-- Device & Problem -->
            <div class="form-section-title" style="margin-top:1.25rem;">
              <img src="../assets/icons/shop.svg" width="12" height="12" alt="" />
              Device & Problem
            </div>
            <div class="form-row">
              <div class="bform-group">
                <label for="deviceType">Device Type *</label>
                <select id="deviceType" name="device_type" required>
                  <option value="">Select device...</option>
                  <option>Smartphone</option>
                  <option>Laptop</option>
                  <option>Tablet</option>
                  <option>Desktop PC</option>
                  <option>Smartwatch</option>
                  <option>Other</option>
                </select>
              </div>
              <div class="bform-group">
                <label for="deviceBrand">Brand / Model</label>
                <input type="text" id="deviceBrand" name="device_brand" placeholder="e.g. iPhone 13, ASUS VivoBook" />
              </div>
            </div>
           <div class="bform-group">
  <label for="problemDesc">Describe the Problem *</label>
  <textarea id="problemDesc" name="problem_description" placeholder="e.g. Screen is cracked, battery drains fast, won't turn on..." required></textarea>
  <div id="ai_suggestions_box"></div>  <!-- ADD THIS -->
</div>

            <!-- Schedule -->
            <div class="form-section-title">
              <img src="../assets/icons/history.svg" width="12" height="12" alt="" />
              Preferred Schedule
            </div>
            <div class="form-row">
              <div class="bform-group">
                <label for="bookingDate">Preferred Date * (Pick-up Date)</label>
                <input type="date" id="bookingDate" name="booking_date" required />
                <div class="date-hint" id="dateHint"></div>
              </div>
              <div class="bform-group">
                <label for="bookingTime">Preferred Time * (What time works for you? *)</label>
                <input type="time" id="bookingTime" name="booking_time" required />
                <div class="date-hint">Within shop operating hours only.</div>
              </div>
            </div>

            <!-- Contact -->
            <div class="form-section-title">
              <img src="../assets/icons/mobile.svg" width="12" height="12" alt="" />
              Your Contact
            </div>
            <div class="form-row">
              <div class="bform-group">
                <label for="customerName">Your Full Name *</label>
                <input type="text" id="customerName" name="customer_name" value="<?php echo htmlspecialchars($userName); ?>" required />
              </div>
              <div class="bform-group">
                <label for="customerContact">Your Contact Number *</label>
                <input type="tel" id="customerContact" name="customer_contact" placeholder="0917-123-4567" required />
              </div>
            </div>

            <!-- Booking Summary -->
            <div class="booking-summary-box" id="bookingSummary">
              <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#94a3b8;margin-bottom:8px;">Booking Summary</div>
              <div class="bsum-row"><span class="bsum-label">Shop</span><span class="bsum-value"><?php echo htmlspecialchars($shop['name']); ?></span></div>
              <div class="bsum-row"><span class="bsum-label">Service</span><span class="bsum-value" id="sumService">—</span></div>
              <div class="bsum-row"><span class="bsum-label">Date & Time</span><span class="bsum-value" id="sumDateTime">—</span></div>
              <div class="bsum-row"><span class="bsum-label">Estimated Fee</span><span class="bsum-value" id="sumFee" style="color:#d97706;">—</span></div>
            </div>

            <button type="submit" class="btn-submit-booking" id="submitBtn">
              <img src="../assets/icons/book.svg" width="16" height="16" alt="" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" />
              Confirm Booking Request
            </button>
          </form>
        </div>

      </div>
    </div>

    <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
  </main>

  <script>
    // ── Mobile menu ──────────────────────────────────────────
    const mobileMenuToggle = document.getElementById("mobileMenuToggle");
    const sidebar = document.querySelector(".sidebar");
    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener("click", () => { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
      document.addEventListener("click", (e) => { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
    }
    function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').classList.add('visible'); return false; }
        function confirmLogout(e) {
      e.preventDefault();
      document.getElementById('logoutModal').classList.add('visible');
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-open');
      return false;
    }
    function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('visible'); }

    // ── Data from PHP ────────────────────────────────────────
    const shopHours = <?php echo $hoursJson; ?>;
    const openDays  = <?php echo json_encode($openDays); ?>;

    // ── Service selector ─────────────────────────────────────
    let selectedService = null;
    document.querySelectorAll('.service-option').forEach(opt => {
      opt.addEventListener('click', function() {
        document.querySelectorAll('.service-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
        selectedService = { id: this.dataset.id, name: this.dataset.name, fee: this.dataset.fee };
        document.getElementById('selectedServiceId').value = selectedService.id;
        updateSummary();
      });
    });

    // ── Blur validation for optional fields ──────────────────
document.getElementById('deviceBrand').addEventListener('blur', function() {
  if (!this.value.trim()) this.classList.add('error');
  else this.classList.remove('error');
});
document.getElementById('deviceBrand').addEventListener('input', function() {
  if (this.value.trim()) this.classList.remove('error');
});
    // ── Date picker ──────────────────────────────────────────
    const dateInput = document.getElementById('bookingDate');
    const dateHint  = document.getElementById('dateHint');
    const today     = new Date(); today.setHours(0,0,0,0);
    const tomorrow  = new Date(today); tomorrow.setDate(tomorrow.getDate()+1);
    dateInput.min   = tomorrow.toISOString().split('T')[0];

    const DAY_NAMES = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];

    // ── Constrain time picker to shop operating hours ────────
    const timeInput = document.getElementById('bookingTime');
    function toHM(t){ return t ? String(t).slice(0,5) : ''; }
    function clearTimeWindow(){ timeInput.removeAttribute('min'); timeInput.removeAttribute('max'); }
    function setTimeWindow(dayName){
      const h = shopHours[dayName];
      if (!h) { clearTimeWindow(); return; }
      timeInput.min = toHM(h.open_time || h.open);
      timeInput.max = toHM(h.close_time || h.close);
      validateTimeWindow();
    }
    function validateTimeWindow(){
      const dateVal = dateInput.value;
      if (!dateVal || !timeInput.value) return true;
      const dayName = DAY_NAMES[new Date(dateVal + 'T00:00:00').getDay()];
      const h = shopHours[dayName];
      if (!h) return true;
      const open = toHM(h.open_time || h.open), close = toHM(h.close_time || h.close);
      if (timeInput.value < open || timeInput.value > close) {
        dateHint.textContent = `⚠️ Please pick a time within shop hours (${formatTime(open)} – ${formatTime(close)}).`;
        dateHint.className = 'date-hint warn';
        timeInput.classList.add('error');
        document.getElementById('submitBtn').disabled = true;
        return false;
      }
      timeInput.classList.remove('error');
      return true;
    }

    dateInput.addEventListener('change', function() {
      const chosen  = new Date(this.value + 'T00:00:00');
      const dayName = DAY_NAMES[chosen.getDay()];
      if (!openDays.includes(dayName)) {
        dateHint.textContent = `⚠️ This shop is closed on ${dayName.charAt(0).toUpperCase()+dayName.slice(1)}s. Please pick another day.`;
        dateHint.className = 'date-hint warn';
        this.classList.add('error');
        clearTimeWindow();
      } else {
        const h = shopHours[dayName];
        const o = formatTime(h.open_time || h.open);
        const c = formatTime(h.close_time || h.close);
        dateHint.textContent = `✓ Open ${o} – ${c}`;
        dateHint.className = 'date-hint ok';
        this.classList.remove('error');
        setTimeWindow(dayName);
      }
      updateSummary();
    });

        document.getElementById('bookingTime').addEventListener('change', function() {
      updateSummary();
      if (!validateTimeWindow()) return;
      checkSlotAvailability();
    });
    dateInput.addEventListener('change', checkSlotAvailability);

    // ── Check slot availability (prevents double-booking) ────
    let slotAvailable = true;
    let availCheckToken = 0;

    async function checkSlotAvailability() {
      const dateVal = dateInput.value;
      const timeVal = document.getElementById('bookingTime').value;
      const hint    = dateHint;

      if (!dateVal || !timeVal) return;
      if (!validateTimeWindow()) return;

      const myToken = ++availCheckToken;
      hint.textContent = 'Checking availability...';
      hint.className = 'date-hint checking';
      slotAvailable = false;
      document.getElementById('submitBtn').disabled = true;

      try {
        const res = await fetch(`check-availability.php?shop_id=<?php echo $shopId; ?>&booking_date=${dateVal}&booking_time=${timeVal}`);
        const data = await res.json();

        if (myToken !== availCheckToken) return; // stale response, ignore

        if (data.success && data.available) {
          slotAvailable = true;
          hint.textContent = '✓ This time slot is available.';
          hint.className = 'date-hint ok';
          document.getElementById('submitBtn').disabled = false;
        } else {
          slotAvailable = false;
          hint.textContent = '⚠️ This time slot is already booked. Please choose another time.';
          hint.className = 'date-hint taken';
          document.getElementById('submitBtn').disabled = true;
        }
      } catch (err) {
        if (myToken !== availCheckToken) return;
        // If check fails, allow submit — server-side check will still catch conflicts
        slotAvailable = true;
        document.getElementById('submitBtn').disabled = false;
      }
    }

    // ── Format time helper ───────────────────────────────────
    function formatTime(t) {
      if (!t) return '';
      const parts = t.split(':');
      let h = parseInt(parts[0]), m = parts[1];
      const ampm = h >= 12 ? 'PM' : 'AM';
      h = h % 12 || 12;
      return `${h}:${m} ${ampm}`;
    }

    // ── Update booking summary ───────────────────────────────
    function updateSummary() {
      const dateVal = dateInput.value;
      const timeVal = document.getElementById('bookingTime').value;
      const hasSvc  = selectedService !== null;
      const hasDate = dateVal !== '';
      const hasTime = timeVal !== '';
      if (!hasSvc && !hasDate) return;
      document.getElementById('bookingSummary').classList.add('show');
      document.getElementById('sumService').textContent = hasSvc ? selectedService.name : '—';
      document.getElementById('sumFee').textContent     = hasSvc ? `₱${Number(selectedService.fee).toLocaleString()}` : '—';
      if (hasDate && hasTime) {
        const d = new Date(dateVal + 'T' + timeVal);
        const opts = { weekday:'short', month:'short', day:'numeric', year:'numeric' };
        document.getElementById('sumDateTime').textContent = d.toLocaleDateString('en-PH', opts) + ' at ' + formatTime(timeVal);
      } else if (hasDate) {
        document.getElementById('sumDateTime').textContent = dateVal;
      } else {
        document.getElementById('sumDateTime').textContent = '—';
      }
    }

    // ── Real-time blur validation ────────────────────────────
    document.querySelectorAll('#bookingForm [required]').forEach(field => {
      field.addEventListener('blur', function() {
        if (!this.value.trim()) this.classList.add('error');
        else this.classList.remove('error');
      });
      field.addEventListener('input', function() {
        if (this.value.trim()) this.classList.remove('error');
      });
      field.addEventListener('change', function() {
        if (this.value.trim()) this.classList.remove('error');
      });
    });

    // ── Form submit ──────────────────────────────────────────
    document.getElementById('bookingForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      let hasEmpty = false;
      document.querySelectorAll('#bookingForm [required]').forEach(field => {
        if (!field.value.trim()) { field.classList.add('error'); hasEmpty = true; }
      });
      if (hasEmpty) {
        document.querySelector('#bookingForm .error').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      const dateVal = dateInput.value;
      if (dateVal) {
        const chosen  = new Date(dateVal + 'T00:00:00');
        const dayName = DAY_NAMES[chosen.getDay()];
        if (!openDays.includes(dayName) && openDays.length > 0) {
          dateHint.textContent = `⚠️ This shop is closed on ${dayName.charAt(0).toUpperCase()+dayName.slice(1)}s.`;
          dateHint.className = 'date-hint warn';
          dateInput.classList.add('error');
          dateInput.scrollIntoView({ behavior:'smooth', block:'center' });
          return;
        }
      }

                    if (!slotAvailable) {
        dateHint.textContent = '⚠️ This time slot is already booked. Please choose another time.';
        dateHint.className = 'date-hint taken';
        dateInput.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
      }

      if (!validateTimeWindow()) {
        timeInput.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
      }

      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.innerHTML = '<img src="book.svg" width="16" height="16" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" /> Submitting...';

      try {
        const formData = new FormData(this);
        const res = await fetch('submit-booking.php', { method:'POST', body: formData });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) {
          alert('Server error (not JSON):\n\n' + raw.substring(0, 500));
          btn.disabled = false;
          btn.innerHTML = '<img src="book.svg" width="16" height="16" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" /> Confirm Booking Request';
          return;
        }
        if (data.success) {
          document.getElementById('successOverlay').classList.add('show');
        } else {
          alert('Error: ' + (data.error || 'Something went wrong.'));
          btn.disabled = false;
          btn.innerHTML = '<img src="book.svg" width="16" height="16" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" /> Confirm Booking Request';
        }
      } catch(err) {
        alert('Fetch failed: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<img src="book.svg" width="16" height="16" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" /> Confirm Booking Request';
      }
    });

    // ── AI Suggest Widget ────────────────────────────────────
    initAISuggest({
      descriptionInputId: 'problemDesc',
      deviceTypeInputId:  'deviceType',
      targetContainerId:  'ai_suggestions_box',
      serviceInputId:     'selectedServiceId',
      customerId:         <?= $userId ?>,
      shopId:             <?= $shopId ?>
    });

    let notifOpen = false;
async function loadNotifications() {
  try {
    const res = await fetch('../api/get_notifications.php');
    const data = await res.json();
    if (!data.success) return;
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    if (data.unread_count > 0) {
      badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
    if (!data.notifications?.length) {
      list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      window.__notifs = [];
      return;
    }
    window.__notifs = data.notifications;
    const STATUS_MSG = {
      confirmed: (shop) => `<span>${shop}</span> confirmed your booking! 🎉`,
      completed: (shop) => `Your repair at <span>${shop}</span> is complete! ✅`,
      paid:      (shop) => `Payment confirmed by <span>${shop}</span>. Ready for pickup! 💰`,
      claimed:   (shop) => `You claimed your device from <span>${shop}</span>! 🎉`,
      no_show:   (shop) => `<span>${shop}</span> marked your booking as no-show.`,
      cancelled: (shop) => `<span>${shop}</span> cancelled your booking.`,
      review_reply: (shop, reply) => `<span style="font-weight:800;color:#d97706;">${shop}:</span> ${reply}`,
      message:   (shop) => `<span style="font-weight:800;color:#d97706;">${shop}</span> sent you a message 💬`,
    };
    list.innerHTML = data.notifications.map((n, idx) => {
      const logo = n.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(n.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
      const msg = STATUS_MSG[n.status] ? STATUS_MSG[n.status](n.shop_name||'Shop', n.reply||'') : `<span>${n.shop_name||'Shop'}:</span> ${n.reply||n.status}`;
      const time = n.time ? new Date(n.time).toLocaleDateString('en-PH',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}) : '';
      const dest = n.link ? ('../' + n.link) : n.status === 'message' ? ('messages.php' + (n.other_id ? '?open=' + n.other_id : '')) : 'my-bookings.php';
      return `<div class="notif-item ${n.is_read?'':'unread'}" onclick="handleNotifClick(${idx}, '${dest}')">
        <img src="${logo}" class="notif-logo" alt="" onerror="this.src='https://ui-avatars.com/api/?name=Shop&background=f59e0b&color=fff&size=80'" />
        <div class="notif-content">
          <div class="notif-message">${msg}</div>
          <div class="notif-time">${time}</div>
        </div>
        ${!n.is_read ? '<div class="notif-dot"></div>' : ''}
      </div>`;
    }).join('');
  } catch(e) { console.error('Notif error:', e); }
}
async function handleNotifClick(idx, dest) {
  const n = (window.__notifs || [])[idx];
  if (n && !n.is_read) {
    let payload = null;
    if (n.status === 'review_reply' && n.review_id) {
      payload = { mark_one: true, type: 'review', review_id: n.review_id };
    } else if (n.status === 'system' && n.notif_id) {
      payload = { mark_one: true, type: 'system', notif_id: n.notif_id };
    } else if (n.status === 'message') {
      payload = null;
    } else if (n.booking_id && n.status) {
      payload = { mark_one: true, type: 'booking', booking_id: n.booking_id, status: n.status };
    }
    if (payload) {
      try {
        await fetch('../api/get_notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
      } catch (e) {}
    }
    const badge = document.getElementById('notifBadge');
    const current = parseInt(badge.textContent, 10) || 0;
    const next = current - 1;
    if (next > 0) { badge.textContent = next; } else { badge.style.display = 'none'; }
  }
  window.location.href = dest;
}
function toggleNotifDropdown() {
  const dropdown = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  dropdown.classList.toggle('open', notifOpen);
  if (notifOpen) { loadNotifications(); }
}
async function markAllRead() {
  await fetch('../api/get_notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({mark_read:true}) });
  document.getElementById('notifBadge').style.display = 'none';
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  document.querySelectorAll('.notif-dot').forEach(el => el.remove());
}
document.addEventListener('click', (e) => {
  const wrapper = document.querySelector('.notif-wrapper');
  if (wrapper && !wrapper.contains(e.target)) {
    document.getElementById('notifDropdown')?.classList.remove('open');
    notifOpen = false;
  }
});
loadNotifications();
setInterval(loadNotifications, 15000); // poll every 15s so the bell stays live without a reload
</script>
<script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>

<?php $chatbotApiPath = '../api/chatbot.php'; include __DIR__ . '/../includes/chatbot-widget.php'; ?>
  <script src="../assets/js/ui-modals.js"></script>
</body>
</html>