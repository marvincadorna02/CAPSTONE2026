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
if ($_SESSION['role'] !== 'repairshop') {
    header("Location: " . ($_SESSION['role'] === 'customer' ? 'dashboard.php' : '../admin/admin-dashboard.php'));
    exit();
}

$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { die("DB error"); }

// ── Load shop logo ───────────────────────────────────────────
$r = $conn->query("SHOW COLUMNS FROM `users` LIKE 'logo_url'");
if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `users` ADD COLUMN `logo_url` VARCHAR(255) DEFAULT NULL");
$stmt = $conn->prepare("SELECT logo_url FROM users WHERE id = ?");
$stmt->bind_param("i", $userId); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
$savedLogoUrl = $row['logo_url'] ?? '';
if ($savedLogoUrl) {
    $baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http')
             .'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\').'/';
    $savedLogoUrl = $baseUrl . $savedLogoUrl;
}
$avatarUrl = $savedLogoUrl ?: "https://ui-avatars.com/api/?name=".urlencode($userName)."&background=f59e0b&color=fff";

// ── Auto-create bookings table ───────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    shop_id         INT NOT NULL,
    customer_id     INT NOT NULL,
    service_id      INT DEFAULT NULL,
    service_name    VARCHAR(255) DEFAULT '',
    customer_name   VARCHAR(255) NOT NULL,
    customer_contact VARCHAR(50) NOT NULL,
    device_type     VARCHAR(100) DEFAULT '',
    device_brand    VARCHAR(150) DEFAULT '',
    problem_description TEXT,
    booking_date    DATE NOT NULL,
    booking_time    TIME NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── Fetch bookings for this shop ─────────────────────────────
$bookings = [];
$bResult = $conn->prepare("
    SELECT b.*, u.name AS customer_user_name, u.email AS customer_email, u.profile_picture AS customer_picture
    FROM bookings b
    LEFT JOIN users u ON u.id = b.customer_id
    WHERE b.shop_id = ?
    ORDER BY
      FIELD(b.status,'pending','confirmed','completed','cancelled'),
      b.booking_date ASC, b.booking_time ASC
");
$bResult->bind_param("i", $userId);
$bResult->execute();
$result = $bResult->get_result();
while ($b = $result->fetch_assoc()) $bookings[] = $b;
$bResult->close();

// ── Count per status ─────────────────────────────────────────
$counts = ['all'=>0,'pending'=>0,'confirmed'=>0,'completed'=>0,'cancelled'=>0];
foreach ($bookings as $b) {
    $counts['all']++;
    $counts[$b['status']] = ($counts[$b['status']] ?? 0) + 1;
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bookings - Fix It Davao</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
  <style>
    .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:.5px; font-family:"Outfit",sans-serif; font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }
    .top-bar       { animation: fadeInUp .4s ease both; }
    .approval-tabs { animation: fadeInUp .5s ease both; }
    .bookings-list { animation: fadeInUp .6s ease both; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* ── TABS ── */
    .approval-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1.25rem; }
    .tab-btn {
      padding:7px 16px; border-radius:20px; border:2px solid #e2e8f0;
      background:white; font-size:.8rem; font-weight:700;
      font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b;
      transition:all .2s ease;
    }
    .tab-btn:hover { border-color:#fbbf24; color:#d97706; }
    .tab-btn.active { background:linear-gradient(135deg,#f59e0b,#d97706); color:white; border-color:transparent; box-shadow:0 3px 10px rgba(245,158,11,.3); }

    /* ── BOOKINGS LIST ── */
    .bookings-list { display:flex; flex-direction:column; gap:.85rem; }

    .booking-card {
      background:white; border-radius:16px; border:1.5px solid #e2e8f0;
      box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;
      animation:fadeInUp .4s ease both;
      transition:box-shadow .2s, border-color .2s;
    }
    .booking-card:hover { box-shadow:0 6px 22px rgba(0,0,0,.1); border-color:#fbbf24; }

    .booking-card-header {
      display:flex; align-items:center; justify-content:space-between;
      padding:.9rem 1.1rem; border-bottom:1px solid #f1f5f9;
      gap:1rem; flex-wrap:wrap;
    }
    .booking-customer { display:flex; align-items:center; gap:.75rem; }
    .customer-avatar  { width:40px; height:40px; border-radius:10px; object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0; }
    .customer-name    { font-size:.9rem; font-weight:800; color:#0f172a; }
    .customer-contact { font-size:.75rem; color:#64748b; margin-top:1px; display:flex; align-items:center; gap:4px; }
    .customer-contact img { opacity:.5; }

    .booking-status-area { display:flex; align-items:center; gap:.5rem; }
    .status-badge {
      font-size:.72rem; font-weight:700; padding:4px 10px; border-radius:20px;
      font-family:"Outfit",sans-serif;
    }
    .status-pending   { background:#fef3c7; color:#92400e; }
    .status-confirmed { background:#d1fae5; color:#065f46; }
    .status-completed { background:#dbeafe; color:#1e40af; }
    .status-cancelled { background:#fee2e2; color:#991b1b; }
    .status-no_show { background: #f3e8ff; color: #6b21a8; }
.btn-noshow { background: linear-gradient(135deg,#8b5cf6,#7c3aed); color: white; }
.btn-noshow:hover { background: linear-gradient(135deg,#7c3aed,#6d28d9); color: white; }

    .booking-card-body {
      display:grid; grid-template-columns:1fr 1fr 1fr;
      gap:.75rem; padding:.9rem 1.1rem;
    }
    .booking-detail-block { display:flex; flex-direction:column; gap:3px; }
    .detail-label { font-size:.67rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; }
    .detail-value { font-size:.83rem; font-weight:600; color:#374151; }
    .detail-value.service-val { color:#d97706; }

    .booking-problem {
      padding:.65rem 1.1rem .9rem;
      font-size:.82rem; color:#475569; line-height:1.5;
      border-top:1px solid #f8fafc;
    }
    .booking-problem strong { color:#374151; font-weight:700; }

    /* ── ACTION BUTTONS ── */
    .booking-card-footer {
      padding:.75rem 1.1rem; border-top:1px solid #f1f5f9;
      display:flex; gap:7px; flex-wrap:wrap;
    }
    .action-btn {
      padding:7px 14px; border-radius:9px; font-size:.78rem;
      font-weight:700; font-family:"Outfit",sans-serif;
      cursor:pointer; border:none; transition:all .2s ease;
      display:flex; align-items:center; gap:5px;
    }
    .action-btn img { width:13px; height:13px; }
    .btn-confirm  { background:linear-gradient(135deg,#10b981,#059669); color:white; }
.btn-confirm:hover  { background:linear-gradient(135deg,#059669,#047857); color:white; }
    .btn-complete { background:#dbeafe; color:#1e40af; }
    .btn-complete:hover { background:#bfdbfe; }
    .btn-cancel   { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; }
.btn-cancel:hover   { background:linear-gradient(135deg,#dc2626,#b91c1c); color:white; }
    .btn-view-detail    { background:#f1f5f9; color:#475569; }
    .btn-view-detail:hover { background:#e2e8f0; }
    .action-btn:disabled { opacity:.4; cursor:not-allowed; }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align:center; padding:4rem 2rem; max-width:460px; margin:0 auto;
    }
    .empty-icon { font-size:3.5rem; margin-bottom:1rem; opacity:.7; }
    .empty-state h3 { font-size:1.15rem; font-weight:700; color:#475569; margin-bottom:6px; }
    .empty-state p  { font-size:.875rem; color:#94a3b8; line-height:1.6; }
    .empty-tip { background:#fef3c7; border:1px solid #fde68a; border-radius:12px; padding:14px 18px; margin-top:1.5rem; text-align:left; font-size:.83rem; line-height:1.5; color:#92400e; }

    /* ── BOOKING DETAIL MODAL ── */
    .modal-overlay { position:fixed; inset:0; background:rgba(10,15,30,.72); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:opacity .3s ease; padding:20px; }
    .modal-overlay.visible { opacity:1; pointer-events:all; }
    .modal-box { background:white; border-radius:20px; padding:28px; max-width:460px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,.25); transform:scale(.9) translateY(20px); opacity:0; transition:transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease; }
    .modal-overlay.visible .modal-box { transform:scale(1) translateY(0); opacity:1; }
    .modal-title { font-size:17px; font-weight:800; color:#0f172a; margin-bottom:1rem; font-family:"Outfit",sans-serif; }
    .modal-close { float:right; background:none; border:none; font-size:1.2rem; color:#94a3b8; cursor:pointer; padding:0; margin-top:-2px; }
    .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:1rem; }
        /* ── BOOKING STATUS STEPPER ── */
    .booking-stepper { display:flex; align-items:flex-start; margin-bottom:1.1rem; }
    .stepper-step { display:flex; flex-direction:column; align-items:center; flex:1; position:relative; }
    .stepper-circle {
      width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center;
      font-size:.75rem; font-weight:800; border:2px solid #e2e8f0; background:white; color:#94a3b8;
      z-index:2; font-family:"Outfit",sans-serif; transition:all .2s ease;
    }
    .stepper-circle.done    { background:#10b981; border-color:#10b981; color:white; }
    .stepper-circle.current { background:#f59e0b; border-color:#f59e0b; color:white; box-shadow:0 0 0 4px rgba(245,158,11,.18); }
    .stepper-circle.cancel  { background:#ef4444; border-color:#ef4444; color:white; }
    .stepper-label { font-size:.66rem; font-weight:700; color:#64748b; margin-top:5px; text-align:center; }
    .stepper-line {
      position:absolute; top:14px; left:50%; width:100%; height:2px; background:#e2e8f0; z-index:1;
    }
    .stepper-line.done { background:#10b981; }
    .stepper-step:last-child .stepper-line { display:none; }
    .detail-item { background:#f8fafc; border-radius:10px; padding:10px 12px; }
    .detail-item-label { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:3px; }
    .detail-item-value { font-size:.85rem; font-weight:600; color:#0f172a; }
    .problem-block { background:#fff7ed; border:1px solid #fde68a; border-radius:10px; padding:12px; margin-bottom:1rem; }
    .modal-actions-row { display:flex; gap:8px; }
    .modal-btn-cancel { flex:1; padding:10px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s; }
    .modal-btn-cancel:hover { background:#f8fafc; }
    .modal-btn-confirm { flex:1; padding:10px; border:none; border-radius:10px; color:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; transition:all .2s; }
    .modal-btn-confirm:hover { transform:translateY(-1px); opacity:.9; }

    @media (max-width:768px) {
      .booking-card-body { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:480px) {
      .booking-card-body { grid-template-columns:1fr; }
    }
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

/* ── Mobile fix ── */
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
    z-index: 600;
  }
}

@media (max-width: 768px) {
  .approval-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 4px;
  }
  .tab-btn { flex-shrink: 0; }
}
@media (max-width: 768px) {
  .bc-footer .action-btn { flex: 1; justify-content: center; }
}
@media (max-width: 768px) {
  .booking-card { margin: 0 2px; }
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
<body class="role-repairshop">
  <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
      <h2 class="brand-name">FIX IT DAVAO</h2>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section" data-role="repairshop">
        <a href="shop-information.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/shop.svg" alt="" /></span><span class="nav-text">My Shop</span></a>
        <a href="shop-bookings.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/booking.svg" alt="" /></span><span class="nav-text">Bookings</span></a>
        <a href="shop-services.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/services.svg" alt="" /></span><span class="nav-text">Services &amp; Fees</span></a>
        <a href="shop-reviews.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/reviews.svg" alt="" /></span><span class="nav-text">Reviews</span></a>
        <a href="shop-subscription.php" class="nav-item">
  <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscription" /></span>
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

   <!-- Booking Detail Modal -->
  <div class="modal-overlay" id="detailModal">
    <div class="modal-box">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div class="modal-title" style="margin:0;">Booking Details</div>
        <button class="modal-close" onclick="closeDetailModal()">✕</button>
      </div>
      <div id="detailStepper"></div>
      <div class="detail-grid" id="detailGrid"></div>
      <div class="problem-block" id="detailProblem"></div>
      <div class="modal-actions-row" id="detailActions"></div>
    </div>
  </div>

  <main class="main-content">
    <header class="top-bar">
      <div class="page-header"><h1 class="current-page-title">Bookings</h1></div>
      <div class="top-bar-actions">
       <div class="notif-wrapper" style="position:relative;">
  <button class="icon-btn notification-btn" id="notifBtn" onclick="toggleNotifDropdown()">
    <img src="../assets/icons/bell.svg" alt="" width="20" height="20" />
  </button>
  <span class="notif-badge" id="notifBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:.6rem;font-weight:700;min-width:18px;height:18px;border-radius:20px;display:none;align-items:center;justify-content:center;padding:0 4px;font-family:'Outfit',sans-serif;border:2px solid white;"></span>
  <div class="notif-dropdown" id="notifDropdown">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;">
      <span style="font-size:.85rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif;">Notifications</span>
    <button onclick="markAllRead()" style="font-size:.72rem;font-weight:700;color:#f59e0b;background:none;border:none;cursor:pointer;font-family:'Outfit',sans-serif;padding:3px 8px;border-radius:6px;transition:background 0.2s ease,color 0.2s ease;" onmouseover="this.style.background='#fff7e6';this.style.color='#d97706';" onmouseout="this.style.background='none';this.style.color='#f59e0b';">Mark all read</button>
    </div>
    <div id="notifList" style="max-height:320px;overflow-y:auto;">
      <div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;">Loading...</div>
    </div>
  </div>
</div>
        <div class="user-profile">
          <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" />
          <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            <span class="user-role">Repair Shop</span>
          </div>
        </div>
      </div>
    </header>

    <div class="dashboard-content">

      <!-- Tabs -->
      <div class="approval-tabs">
        <button class="tab-btn active" data-status="all">All (<?php echo $counts['all']; ?>)</button>
        <button class="tab-btn" data-status="pending">Pending (<?php echo $counts['pending']; ?>)</button>
        <button class="tab-btn" data-status="confirmed">Confirmed (<?php echo $counts['confirmed']; ?>)</button>
        <button class="tab-btn" data-status="completed">Completed (<?php echo $counts['completed']; ?>)</button>
        <button class="tab-btn" data-status="cancelled">Cancelled (<?php echo $counts['cancelled']; ?>)</button>
      </div>

      <!-- Bookings -->
      <div class="bookings-list" id="bookingsList">

        <?php if (empty($bookings)): ?>
        <div class="empty-state">
          <div class="empty-icon"><img src="../assets/icons/list.svg" width="64" height="64" alt="" style="opacity:.4;" /></div>
          <h3>No Bookings Yet</h3>
          <p>Bookings from customers will appear here once they start booking your services.</p>
          <div class="empty-tip">
            <strong><img src="../assets/icons/bulb.svg" width="18" height="18" style="vertical-align:middle;" alt="" /> Tip:</strong>
            Make sure your profile, services, and operating hours are complete to attract more customers!
          </div>
        </div>

        <?php else: ?>

        <?php foreach ($bookings as $b):
          $statusClass = 'status-' . $b['status'];
          $statusLabel = ucfirst($b['status']);
          $custAvatar  = !empty($b['customer_picture'])
    ? $b['customer_picture']
    : "https://ui-avatars.com/api/?name=".urlencode($b['customer_name'])."&background=2563eb&color=fff&size=80";
          $dateFormatted = date('M d, Y', strtotime($b['booking_date']));
          $timeFormatted = date('g:i A', strtotime($b['booking_time']));
          $createdFormatted = date('M d, Y g:i A', strtotime($b['created_at']));
        ?>
        <div class="booking-card" data-status="<?php echo $b['status']; ?>" data-id="<?php echo $b['id']; ?>">

          <!-- Header -->
          <div class="booking-card-header">
            <div class="booking-customer">
              <img src="<?php echo $custAvatar; ?>" alt="" class="customer-avatar" />
              <div>
                <div class="customer-name"><?php echo htmlspecialchars($b['customer_name']); ?></div>
                <div class="customer-contact">
                  <img src="../assets/icons/mobile.svg" width="11" height="11" alt="" />
                  <?php echo htmlspecialchars($b['customer_contact']); ?>
                </div>
              </div>
            </div>
            <div class="booking-status-area">
              <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
              <span style="font-size:.72rem;color:#94a3b8;">Booked <?php echo $createdFormatted; ?></span>
            </div>
          </div>

          <!-- Body -->
          <div class="booking-card-body">
            <div class="booking-detail-block">
              <span class="detail-label">Service</span>
              <span class="detail-value service-val"><?php echo $b['service_name'] ? htmlspecialchars($b['service_name']) : '—'; ?></span>
            </div>
            <div class="booking-detail-block">
              <span class="detail-label">Device</span>
              <span class="detail-value"><?php echo htmlspecialchars($b['device_type']); ?><?php echo $b['device_brand'] ? ' · ' . htmlspecialchars($b['device_brand']) : ''; ?></span>
            </div>
            <div class="booking-detail-block">
              <span class="detail-label">Schedule</span>
              <span class="detail-value"><?php echo $dateFormatted; ?> at <?php echo $timeFormatted; ?></span>
            </div>
          </div>

          <!-- Problem -->
          <?php if (!empty($b['problem_description'])): ?>
          <div class="booking-problem">
            <strong>Problem:</strong> <?php echo htmlspecialchars($b['problem_description']); ?>
          </div>
          <?php endif; ?>

          <!-- Footer actions -->
          <div class="booking-card-footer">
            <?php if ($b['status'] === 'pending'): ?>
              <button class="action-btn btn-confirm"  onclick="updateStatus(<?php echo $b['id']; ?>,'confirmed',this)">
                <img src="../assets/icons/tama.svg" width="13" height="13" alt="" /> Confirm
              </button>
              <button class="action-btn btn-cancel"   onclick="updateStatus(<?php echo $b['id']; ?>,'cancelled',this)">
                <img src="../assets/icons/xmark.svg" width="13" height="13" alt="" /> Decline
              </button>
            <?php elseif ($b['status'] === 'confirmed'): ?>
              <button class="action-btn btn-complete" onclick="updateStatus(<?php echo $b['id']; ?>,'completed',this)">
                <img src="../assets/icons/nice.svg" width="13" height="13" alt="" /> Mark Complete
              </button>
              <button class="action-btn btn-cancel"   onclick="updateStatus(<?php echo $b['id']; ?>,'cancelled',this)">
                <img src="../assets/icons/xmark.svg" width="13" height="13" alt="" /> Cancel
              </button>
                <button class="action-btn btn-noshow" onclick="updateStatus(<?php echo $b['id']; ?>,'no_show',this)">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width:1em;height:1em;vertical-align:middle;"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path fill="currentColor" d="M41-24.9c-9.4-9.4-24.6-9.4-33.9 0S-2.3-.3 7 9.1l528 528c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9L311.5 245.7c55-10.9 96.5-59.5 96.5-117.7 0-66.3-53.7-120-120-120-58.2 0-106.8 41.5-117.7 96.5L41-24.9zM235.6 305.4C147.9 316.6 80 391.5 80 482.3 80 498.7 93.3 512 109.7 512l332.5 0-206.6-206.6z"/></svg>
  No Show
</button>
            <?php endif; ?>
            <button class="action-btn btn-view-detail" onclick='viewDetail(<?php echo json_encode($b); ?>)'>
              <img src="../assets/icons/view.svg" width="13" height="13" alt="" /> View Details
            </button>
          </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
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
    function confirmLogout(e) {
  e.preventDefault();
  sidebar.classList.remove('active');
  document.body.classList.remove('sidebar-open');
  document.getElementById('logoutModal').classList.add('visible');
  return false;
}
    function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('visible'); }

    // ── Tab filter ───────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const status = this.dataset.status;
        document.querySelectorAll('.booking-card').forEach(card => {
          card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
        });
      });
    });

    // ── Update booking status via AJAX ───────────────────────
    async function updateStatus(bookingId, newStatus, btn) {
      const labels = { confirmed:'Confirm this booking?', cancelled:'Decline/cancel this booking?', completed:'Mark as completed?', no_show:'Mark customer as no-show?' };
      if (!confirm(labels[newStatus] || 'Are you sure?')) return;

      btn.disabled = true;
      try {
        const fd = new FormData();
        fd.append('booking_id', bookingId);
        fd.append('status', newStatus);
        const res  = await fetch('update-booking-status.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
          // Reload page to reflect new status
          window.location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to update.'));
          btn.disabled = false;
        }
      } catch(e) {
        alert('Network error. Please try again.');
        btn.disabled = false;
      }
    }

    function renderStepper(status) {
      if (status === 'cancelled' || status === 'no_show') {
        const label = status === 'cancelled' ? 'Cancelled' : 'No Show';
        return `
          <div class="booking-stepper">
            <div class="stepper-step">
              <div class="stepper-circle done">✓</div>
              <div class="stepper-line done"></div>
              <div class="stepper-label">Pending</div>
            </div>
            <div class="stepper-step">
              <div class="stepper-circle cancel">✕</div>
              <div class="stepper-label" style="color:#ef4444;">${label}</div>
            </div>
          </div>`;
      }

      const order = ['pending', 'confirmed', 'completed'];
      const labels = { pending: 'Pending', confirmed: 'Confirmed', completed: 'Completed' };
      const currentIdx = order.indexOf(status);

      return `<div class="booking-stepper">` + order.map((key, idx) => {
        let circleClass = '', content = idx + 1;
        if (idx < currentIdx)      { circleClass = 'done';    content = '✓'; }
        else if (idx === currentIdx) { circleClass = 'current'; }
        const lineClass = idx < currentIdx ? 'done' : '';
        return `
          <div class="stepper-step">
            <div class="stepper-circle ${circleClass}">${content}</div>
            <div class="stepper-line ${lineClass}"></div>
            <div class="stepper-label">${labels[key]}</div>
          </div>`;
      }).join('') + `</div>`;
    }

    // ── View detail modal ────────────────────────────────────
      function viewDetail(b) {
      const statusColors = { pending:'#92400e', confirmed:'#065f46', completed:'#1e40af', cancelled:'#991b1b' };
      const statusBg     = { pending:'#fef3c7', confirmed:'#d1fae5', completed:'#dbeafe', cancelled:'#fee2e2' };

      document.getElementById('detailStepper').innerHTML = renderStepper(b.status);

      document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item"><div class="detail-item-label">Customer</div><div class="detail-item-value">${esc(b.customer_name)}</div></div>
        <div class="detail-item"><div class="detail-item-label">Contact</div><div class="detail-item-value">${esc(b.customer_contact)}</div></div>
        <div class="detail-item"><div class="detail-item-label">Service</div><div class="detail-item-value" style="color:#d97706">${esc(b.service_name||'—')}</div></div>
        <div class="detail-item"><div class="detail-item-label">Device</div><div class="detail-item-value">${esc(b.device_type)}${b.device_brand?' · '+esc(b.device_brand):''}</div></div>
        <div class="detail-item"><div class="detail-item-label">Date</div><div class="detail-item-value">${esc(b.booking_date)}</div></div>
        <div class="detail-item"><div class="detail-item-label">Time</div><div class="detail-item-value">${esc(b.booking_time)}</div></div>
        <div class="detail-item" style="grid-column:span 2"><div class="detail-item-label">Status</div>
          <div class="detail-item-value"><span style="background:${statusBg[b.status]};color:${statusColors[b.status]};padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span></div>
        </div>`;

      document.getElementById('detailProblem').innerHTML =
        `<div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:5px;">Problem Description</div>
         <div style="font-size:.85rem;color:#374151;line-height:1.5;">${esc(b.problem_description||'No description provided.')}</div>`;

      // Actions
      let actionsHtml = `<button class="modal-btn-cancel" onclick="closeDetailModal()">Close</button>`;
      if (b.status === 'pending') {
        actionsHtml += `<button class="modal-btn-confirm" style="background:linear-gradient(135deg,#10b981,#059669);" onclick="closeDetailModal();updateStatus(${b.id},'confirmed',document.createElement('button'))">Confirm Booking</button>`;
      } else if (b.status === 'confirmed') {
        actionsHtml += `<button class="modal-btn-confirm" style="background:linear-gradient(135deg,#3b82f6,#2563eb);" onclick="closeDetailModal();updateStatus(${b.id},'completed',document.createElement('button'))">Mark Complete</button>`;
      }
      document.getElementById('detailActions').innerHTML = actionsHtml;

      document.getElementById('detailModal').classList.add('visible');
    }

    function closeDetailModal() { document.getElementById('detailModal').classList.remove('visible'); }
    document.getElementById('detailModal').addEventListener('click', function(e){ if(e.target===this) closeDetailModal(); });

    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── Shop Notifications ───────────────────────────────────────
let notifOpen = false;

async function loadNotifications() {
  const badge = document.getElementById('notifBadge');
  const list  = document.getElementById('notifList');
  try {
    const res  = await fetch('../api/get_shop_notifications.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    if (!text.trim()) throw new Error('Empty response');
    const data = JSON.parse(text);
    if (!data.success) throw new Error('Not success');

    // Badge
    if (data.unread_count > 0) {
      badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }

    if (!data.notifications || !data.notifications.length) {
      list.innerHTML = `
        <div style="padding:2rem 1rem;text-align:center;">
          <img src="../assets/icons/bell.svg" width="32" height="32" style="opacity:.3;display:block;margin:0 auto 8px;" />
          <div style="font-size:.83rem;color:#94a3b8;font-family:'Outfit',sans-serif;">No notifications yet.</div>
        </div>`;
      return;
    }

   const ICON = {
  pending:   `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>`,
  confirmed: `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
  completed: `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#3b82f6"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
  cancelled: `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>`,
  review:    `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><text x="12" y="16" text-anchor="middle" font-size="11" fill="white">★</text></svg>`,
};

    const AVATAR_BG = { pending: 'f59e0b', cancelled: 'ef4444', review: '8b5cf6', active: '10b981', rejected: 'ef4444' };

    list.innerHTML = data.notifications.map(n => {
      const time = n.time
        ? new Date(n.time).toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' })
        : '';
      const bg      = n.is_read ? '' : 'background:#fffbeb;';
      const dest    = n.type === 'review' ? 'shop-reviews.php' : n.type === 'subscription' ? 'shop-subscription.php' : 'shop-bookings.php';
      const avatarBg = AVATAR_BG[n.status] || '94a3b8';
      const displayName = n.type === 'subscription' ? 'Subscription' : (n.customer_name || 'Customer');
      const avatarUrl = n.customer_picture 
  ? n.customer_picture 
  : `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=${avatarBg}&color=fff&size=80`;


const msgText = n.type === 'subscription'
  ? (n.status === 'active'
      ? `Your ${n.plan_name || ''} subscription was approved ✅`
      : `Your ${n.plan_name || ''} subscription was declined`)
  : n.type === 'reschedule'
  ? `${n.customer_name} has rescheduled their booking 📅`
  : n.type === 'review'
    ? 'Left you a review'
    : n.status === 'pending'
      ? 'Booked your shop'
      : n.status === 'confirmed'
        ? 'Booking confirmed ✅'
        : n.status === 'completed'
          ? 'Booking completed 🎉'
          : 'Cancelled their booking';

      const starsHtml = n.type === 'review'
        ? `<div style="display:flex;gap:1px;margin-top:2px;">
            ${'<span style="font-size:.7rem;color:#f59e0b;">★</span>'.repeat(n.rating)}
            ${'<span style="font-size:.7rem;color:#d1d5db;">★</span>'.repeat(5 - n.rating)}
           </div>`
        : '';

      return `
        <div onclick="window.location.href='${dest}'"
          style="display:flex;align-items:flex-start;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid #f8fafc;cursor:pointer;transition:background .15s;${bg}">
          <img src="${avatarUrl}"
            style="width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid #e2e8f0;"
            onerror="this.src='https://ui-avatars.com/api/?name=Customer&background=94a3b8&color=fff&size=80'" />
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:5px;">
              <span style="font-size:.82rem;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${displayName}</span>
              ${ICON[n.status] || ''}
            </div>
            <div style="font-size:.75rem;color:#64748b;margin-top:1px;">${msgText}</div>
            ${starsHtml}
            ${n.service_name ? `<div style="font-size:.72rem;color:#d97706;margin-top:2px;">🔧 ${n.service_name}</div>` : ''}
            <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">${time}</div>
          </div>
          ${!n.is_read ? '<div style="width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0;margin-top:4px;"></div>' : ''}
        </div>`;
    }).join('');

  } catch(e) {
    document.getElementById('notifList').innerHTML = `
      <div style="padding:2rem 1rem;text-align:center;">
        <img src="../assets/icons/bell.svg" width="32" height="32" style="opacity:.3;display:block;margin:0 auto 8px;" />
        <div style="font-size:.83rem;color:#94a3b8;font-family:'Outfit',sans-serif;">No notifications yet.</div>
      </div>`;
  }
}

// REPLACE WITH
function toggleNotifDropdown() {
  const dropdown = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  dropdown.classList.toggle('open', notifOpen);
  if (notifOpen) {
    loadNotifications();
    markAllRead();
  }
}

async function markAllRead() {
  await fetch('../api/get_shop_notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mark_read: true })
  });
  document.getElementById('notifBadge').style.display = 'none';
  loadNotifications();
}

// REPLACE WITH
document.addEventListener('click', (e) => {
  const btn = document.getElementById('notifBtn');
  const dropdown = document.getElementById('notifDropdown');
  if (btn && dropdown && !btn.closest('div').contains(e.target)) {
    dropdown.classList.remove('open');
    notifOpen = false;
  }
});

loadNotifications();
  </script>
   <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
</body>
</html>