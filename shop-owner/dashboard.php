<?php
session_start();

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
if ($_SESSION['role'] === 'admin') { header("Location: ../admin/admin-dashboard.php"); exit(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userRole  = $_SESSION['role'];
$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

$roleLabel = $userRole === 'repairshop' ? 'Repair Shop' : 'Customer';
$pageTitle = $userRole === 'repairshop' ? 'My Repair Shop' : 'Find Repair Shops';
$avatarBg  = $userRole === 'repairshop' ? 'f59e0b' : '2563eb';
$avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background={$avatarBg}&color=fff";
$bodyClass = "role-{$userRole}";
?>
<!doctype html>
<html lang="en">
<head>
    <script>
// Break out of the login/signup iframe modal once we've landed on a real page
if (window.top !== window.self) {
  window.top.location.href = window.location.href;
}
</script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="format-detection" content="address=no, telephone=no" />
  <title>Dashboard - Fix It Davao</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="manifest" href="../manifest.json" />
  <meta name="theme-color" content="#f59e0b" />
  <style>
    /* ── NOTIFICATIONS (matched exactly to admin-dashboard.php) ── */
    .notif-wrapper { position:relative; }
    .notification-btn { position:relative; }

    .notif-badge {
      position:absolute; top:-3px; right:-3px;
      min-width:17px; height:17px; padding:0 4px;
      background:#ef4444; color:white; border-radius:10px;
      font-size:0.65rem; font-weight:800;
      align-items:center; justify-content:center;
      font-family:"Outfit",sans-serif; border:2px solid white;
      line-height:1;
    }

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
    .notif-header-title { font-size:0.88rem; font-weight:800; color:#0f172a; font-family:"Outfit",sans-serif; }
    .notif-mark-read {
      font-size:.72rem; font-weight:700; color:#f59e0b;
      background:none; border:none; cursor:pointer;
      font-family:"Outfit",sans-serif;
      padding:3px 8px; border-radius:6px;
      transition:background 0.2s ease, color 0.2s ease;
    }
    .notif-mark-read:hover { background:#fff7e6; color:#d97706; }

    .notif-list { max-height:340px; overflow-y:auto; scrollbar-width:thin; scrollbar-color:#e2e8f0 transparent; }
    .notif-list::-webkit-scrollbar { width:4px; }
    .notif-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:4px; }

    .notif-item {
      display:flex; align-items:flex-start; gap:10px;
      padding:11px 16px; border-bottom:1px solid #f8fafc;
      transition:background 0.15s ease; cursor:pointer;
    }
    .notif-item:last-child { border-bottom:none; }
    .notif-item:hover { background:#fafafa; }
    .notif-item.unread { background:#fffbeb; }
    .notif-item.unread:hover { background:#fef9e7; }

    .notif-logo { width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0; margin-top:1px; }
    .notif-content { flex:1; min-width:0; }
    .notif-message { font-size:0.8rem; font-weight:600; color:#0f172a; line-height:1.4; font-family:"Outfit",sans-serif; }
    .notif-message span { font-weight:800; }
    .notif-time { font-size:0.7rem; color:#94a3b8; margin-top:3px; }
    .notif-dot { width:7px; height:7px; background:#f59e0b; border-radius:50%; flex-shrink:0; margin-top:6px; }

    .notif-empty { text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.82rem; font-family:"Outfit",sans-serif; }
    .notif-loading { text-align:center; padding:24px 20px; font-size:0.82rem; color:#94a3b8; }

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
<body class="<?php echo $bodyClass; ?>">

  <!-- Mobile toggle -->
  <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

  <!-- ════════════════ SIDEBAR ════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
      <h2 class="brand-name">Fix It Davao</h2>
    </div>
    <nav class="sidebar-nav">
      <?php if ($userRole === 'customer'): ?>
      <div class="nav-section" data-role="customer">
        <a href="dashboard.php" class="nav-item active">
          <span class="nav-icon"><img src="../assets/icons/find.svg" alt="" /></span>
          <span class="nav-text">Find Repair Shops</span>
        </a>
        <a href="../customer/my-bookings.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/book.svg" alt="" /></span>
          <span class="nav-text">My Bookings</span>
        </a>
        <a href="../customer/favorites.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/favorite.svg" alt="" /></span>
          <span class="nav-text">Favorites</span>
        </a>
        <a href="../customer/history.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/history.svg" alt="" /></span>
          <span class="nav-text">History</span>
        </a>
      </div>
      <?php endif; ?>
      <?php if ($userRole === 'repairshop'): ?>
      <div class="nav-section" data-role="repairshop">
        <a href="dashboard.php" class="nav-item active">
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
      </div>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <a href="../logout.php" class="nav-item" onclick="return confirmLogout(event)">
        <span class="nav-icon"><img src="../assets/icons/logout.svg" alt="" /></span>
        <span class="nav-text">Logout</span>
      </a>
    </div>
  </aside>

  <!-- ════════════════ LOGOUT MODAL ════════════════ -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal-box" style="max-width:360px;text-align:center;">
      <div style="width:52px;height:52px;background:var(--brand-faint);border:1px solid #fde68a;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <div style="font-size:48px; margin-bottom:12px;">👋</div>
      </div>
      <div class="modal-title">Logging Out?</div>
      <div class="modal-subtitle" style="margin-bottom:4px;">Are you sure you want to logout of Fix It Davao?</div>
      <div class="modal-actions">
        <button class="modal-btn-cancel" onclick="closeLogoutModal()">Cancel</button>
        <button class="modal-btn-confirm" style="background:linear-gradient(135deg,#ef4444,#dc2626);" onclick="window.location.href='../logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>

<!-- ════════════════ PROFILE MODAL ════════════════ -->
<div class="modal-overlay" id="profileModal">
  <div class="modal-box" style="max-width:380px;padding:0;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#1e293b,#0f172a);padding:32px 24px 24px;text-align:center;position:relative;">
      <button onclick="closeProfileModal()" style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,.1);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:14px;">✕</button>
      <div style="position:relative;display:inline-block;margin:0 auto 12px;">
        <div id="profileInitials" style="width:72px;height:72px;border-radius:16px;background:linear-gradient(135deg,#ff6b35,#ef4444);color:white;font-size:1.6rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:var(--font);overflow:hidden;cursor:<?php echo $userRole==='customer'?'pointer':'default';?>;"
          <?php if($userRole==='customer'): ?>onclick="document.getElementById('picInput').click()"<?php endif; ?>>
        </div>
        <?php if($userRole === 'customer'): ?>
        <div onclick="document.getElementById('picInput').click()" style="position:absolute;bottom:-4px;right:-4px;width:22px;height:22px;background:#f59e0b;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #0f172a;" title="Change photo">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="white"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
        </div>
        <input type="file" id="picInput" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handlePicUpload(event)" />
        <?php endif; ?>
      </div>
      <div style="font-size:1.1rem;font-weight:800;color:white;margin-bottom:4px;" id="profileName"></div>
      <div style="font-size:.75rem;color:#f59e0b;font-weight:700;text-transform:uppercase;letter-spacing:1px;" id="profileRole"></div>
    </div>
    <div style="padding:20px 24px;">
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--canvas);border-radius:10px;border:1px solid var(--border);">
          <img src="../assets/icons/email.svg" width="16" height="16" alt="" style="opacity:.5;flex-shrink:0;" />
          <div>
            <div style="font-size:.65rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Email</div>
            <div style="font-size:.82rem;font-weight:600;color:var(--text-primary);" id="profileEmail"></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--canvas);border-radius:10px;border:1px solid var(--border);">
          <img src="../assets/icons/users.svg" width="16" height="16" alt="" style="opacity:.5;flex-shrink:0;" />
          <div>
            <div style="font-size:.65rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Account Type</div>
            <div style="font-size:.82rem;font-weight:600;color:var(--text-primary);" id="profileType"></div>
          </div>
        </div>
      </div>
      <div id="picStatus" style="display:none;margin-top:10px;padding:8px 12px;border-radius:8px;font-size:.78rem;font-weight:600;text-align:center;"></div>
      <button onclick="confirmLogout(event)" style="width:100%;margin-top:16px;padding:11px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:10px;font-size:.85rem;font-weight:700;font-family:var(--font);cursor:pointer;">
        Logout
      </button>
    </div>
  </div>
</div>

  <!-- ════════════════ VIEW DETAILS MODAL ════════════════ -->
  <div class="modal-overlay" id="shopDetailsModal">
    <div class="modal-box">
      <div class="sdm-header">
        <img id="sdmLogo" src="" alt="" class="sdm-logo" />
        <div class="sdm-header-info">
          <div id="sdmName" class="sdm-shop-name"></div>
          <div class="sdm-location">
            <img src="../assets/icons/location.svg" width="12" height="12" alt="" style="opacity:.55;flex-shrink:0;" />
            <span id="sdmLocationText"></span>
          </div>
          <div class="sdm-contact">
            <img src="../assets/icons/mobile.svg" width="12" height="12" alt="" style="opacity:.55;flex-shrink:0;" />
            <span id="sdmContactText"></span>
          </div>
          <div class="sdm-rating-badge" id="sdmRatingBadge"></div>
        </div>
        <button class="sdm-close" onclick="closeDetailsModal()">✕</button>
      </div>
      <div class="sdm-tabs">
        <button class="sdm-tab active" data-panel="hours">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
          Hours
        </button>
        <button class="sdm-tab" data-panel="services">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
          Services
        </button>
        <button class="sdm-tab" data-panel="reviews">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          Reviews
        </button>
        <button class="sdm-tab" data-panel="location">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
          Location
        </button>
      </div>
      <div class="sdm-body">
        <div class="sdm-tab-panel active" id="panel-hours">
          <div class="sdm-section-title">Operating Hours</div>
          <div class="hours-grid" id="sdmHoursGrid"></div>
        </div>
        <div class="sdm-tab-panel" id="panel-services">
          <div class="sdm-section-title">Services &amp; Fees</div>
          <div class="sdm-services-list" id="sdmServicesList"></div>
        </div>
        <div class="sdm-tab-panel" id="panel-reviews">
          <div class="sdm-section-title">Customer Reviews</div>
          <div id="sdmReviewsContent"></div>
        </div>
        <div class="sdm-tab-panel" id="panel-location">
  <div class="sdm-section-title">Shop Location</div>
  <div id="sdmMapAddress" style="-webkit-touch-callout:none;user-select:none;pointer-events:none;">
    <img src="../assets/icons/location.svg" width="12" height="12" style="opacity:.55;flex-shrink:0;" alt="" />
    <span></span>
  </div>
  <div id="sdmMap" style="height:240px;width:100%;border-radius:12px;margin-top:10px;"></div>
  <!-- ↓ NEW: travel info panel below map, no overlap -->
  <div id="sdmTravelPanel" style="margin-top:10px;">
    <div style="padding:10px 0;font-size:12px;color:var(--text-muted);text-align:center;">
      📍 Enable location to see travel times
    </div>
  </div>
</div>
      </div>
      <div class="sdm-footer">
        <a id="sdmBookBtn" href="#" class="sdm-book-btn">
          <img src="../assets/icons/book.svg" width="14" height="14" alt="" style="vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);" />
          Book This Shop
        </a>
      </div>
    </div>
  </div>

  <!-- ════════════════ MAP MODAL ════════════════ -->
  <div class="modal-overlay" id="mapModal">
    <div class="modal-box">
      <div class="map-modal-header">
        <span class="map-modal-title" id="mapModalTitle"></span>
        <button class="map-modal-close" onclick="closeMapModal()">✕</button>
      </div>
      <div id="leafletMap"></div>
      <div class="map-modal-footer" id="mapModalAddress"></div>
    </div>
  </div>

  <!-- ════════════════ MAIN CONTENT ════════════════ -->
  <main class="main-content">
    <header class="top-bar">
      <div class="page-header">
        <h1 class="current-page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
      </div>
      <div class="top-bar-actions">
        <div class="notif-wrapper">
          <button class="icon-btn notification-btn" id="notifBtn" onclick="toggleNotifDropdown()">
            <img src="../assets/icons/bell.svg" alt="Notifications" width="18" height="18" />
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
        <div class="user-profile" onclick="openProfileModal()">
          <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" />
          <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            <span class="user-role"><?php echo $roleLabel; ?></span>
          </div>
        </div>
      </div>
    </header>

    <div class="dashboard-content">

      <?php if ($userRole === 'customer'): ?>
      <div class="content-section" data-role="customer">
        <div class="search-filter-section">
          <div class="search-box-large">
            <span class="search-icon"><img src="../assets/icons/look.svg" alt="" width="18" height="18" /></span>
            <input type="text" id="locationSearch" placeholder="Search by shop name or location…" />
            <button class="search-btn" id="searchBtn">Search</button>
          </div>
          <div class="filter-section">
            <button class="filter-btn active" data-filter="all"><span>All Shops</span></button>
            <button class="filter-btn" data-filter="phones">
              <span style="display:flex;align-items:center;gap:5px"><img src="../assets/icons/mobile.svg" width="16" height="16" alt="" /> Phones</span>
            </button>
            <button class="filter-btn" data-filter="laptops">
              <span style="display:flex;align-items:center;gap:5px"><img src="../assets/icons/laptop.svg" width="16" height="16" alt="" /> Laptops</span>
            </button>
            <button class="filter-btn" data-filter="tablets">
              <span style="display:flex;align-items:center;gap:5px"><img src="../assets/icons/tablet.svg" width="16" height="16" alt="" /> Tablets</span>
            </button>
            <button class="filter-btn" id="nearMeBtn">
  <span style="display:flex;align-items:center;gap:5px">
    <img src="../assets/icons/location.svg" width="16" height="16" alt="" /> Near Me
  </span>
</button>
          </div>
        </div>
        <div class="shops-loading" id="shopsLoading">
          <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="skeleton-card">
            <div style="display:flex;gap:12px;margin-bottom:14px;">
              <div class="skeleton-line" style="width:52px;height:52px;border-radius:12px;flex-shrink:0;margin:0;"></div>
              <div style="flex:1;">
                <div class="skeleton-line" style="height:13px;width:65%;"></div>
                <div class="skeleton-line" style="height:10px;width:40%;"></div>
              </div>
            </div>
            <div class="skeleton-line" style="height:10px;width:88%;"></div>
            <div class="skeleton-line" style="height:10px;width:58%;"></div>
            <div style="display:flex;gap:6px;margin-top:12px;">
              <div class="skeleton-line" style="height:22px;width:75px;border-radius:20px;margin:0;"></div>
              <div class="skeleton-line" style="height:22px;width:75px;border-radius:20px;margin:0;"></div>
            </div>
          </div>
          <?php endfor; ?>
        </div>
        <div class="shops-grid" id="shopsGrid" style="display:none;"></div>
        <div class="empty-state" id="emptyState">
          <img src="../assets/icons/look.svg" alt="No shops found" />
          <h3>No Repair Shops Found</h3>
          <p>Try a different search or filter — or check back later.</p>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($userRole === 'repairshop'): ?>
      <div class="content-section" data-role="repairshop">
        <div class="form-container">
          <div class="form-header">
            <h2>My Repair Shop Information</h2>
            <p>Update your shop details, services, and availability</p>
          </div>
          <form id="shopForm" class="shop-form" method="POST" action="save-shop.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <div class="form-section">
              <h3 class="section-title">Shop Logo</h3>
              <div class="logo-upload-area">
                <div class="logo-preview" id="logoPreview">
                  <img src="https://ui-avatars.com/api/?name=Shop&background=f59e0b&color=fff&size=150" alt="Shop Logo" id="logoImage" />
                </div>
                <div class="upload-controls">
                  <input type="file" id="logoInput" name="shop_logo" accept="image/*" style="display:none" />
                  <button type="button" class="btn-upload" onclick="document.getElementById('logoInput').click()">
                    <img src="../assets/icons/find.svg" width="14" height="14" alt="" style="filter:brightness(0) invert(1);" />
                    Upload Logo
                  </button>
                  <p class="upload-hint">Recommended: 500×500px, Max 5MB</p>
                </div>
              </div>
            </div>
            <div class="form-section">
              <h3 class="section-title">Basic Information</h3>
              <div class="form-grid">
                <div class="form-group full-width">
                  <label for="shopName">Shop Name *</label>
                  <input type="text" id="shopName" name="shop_name" placeholder="Enter your repair shop name" required />
                </div>
                <div class="form-group full-width">
                  <label for="shopLocation">Location *</label>
                  <input type="text" id="shopLocation" name="shop_location" placeholder="Complete address (e.g., 123 Main St, Davao City)" required />
                </div>
                <div class="form-group">
                  <label for="contactNumber">Contact Number *</label>
                  <input type="tel" id="contactNumber" name="contact_number" placeholder="0917-123-4567" required />
                </div>
                <div class="form-group">
                  <label for="email">Email Address *</label>
                  <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" placeholder="shop@example.com" required />
                </div>
              </div>
            </div>
            <div class="form-section">
              <h3 class="section-title">Services &amp; Fees</h3>
              <div id="servicesContainer">
                <div class="service-item">
                  <div class="form-grid">
                    <div class="form-group">
                      <label>Service Name</label>
                      <input type="text" name="service_name[]" placeholder="e.g., Screen Replacement" class="service-name" />
                    </div>
                    <div class="form-group">
                      <label>Fee (₱)</label>
                      <input type="number" name="service_fee[]" placeholder="1500" class="service-fee" />
                    </div>
                    <div class="form-group">
                      <label>Duration</label>
                      <input type="text" name="service_duration[]" placeholder="1–2 hours" class="service-duration" />
                    </div>
                  </div>
                </div>
              </div>
              <button type="button" class="btn-add-service" onclick="addServiceField()">
                <img src="../assets/icons/find.svg" width="13" height="13" alt="" />
                Add Another Service
              </button>
            </div>
            <div class="form-section">
              <h3 class="section-title">Operating Hours</h3>
              <div class="schedule-grid">
                <?php
                $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                $defaultOpen  = ['monday','tuesday','wednesday','thursday','friday'];
                $defaultStart = ['saturday' => '10:00','sunday' => '10:00'];
                $defaultEnd   = ['saturday' => '15:00','sunday' => '15:00'];
                foreach ($days as $day):
                  $checked = in_array($day, $defaultOpen) ? 'checked' : '';
                  $start   = $defaultStart[$day] ?? '09:00';
                  $end     = $defaultEnd[$day]   ?? '18:00';
                ?>
                <div class="day-schedule">
                  <input type="checkbox" id="<?php echo $day; ?>" name="days[]" value="<?php echo $day; ?>" <?php echo $checked; ?> />
                  <label for="<?php echo $day; ?>"><?php echo ucfirst($day); ?></label>
                  <input type="time" name="open_<?php echo $day; ?>" value="<?php echo $start; ?>" class="time-input" />
                  <span>to</span>
                  <input type="time" name="close_<?php echo $day; ?>" value="<?php echo $end; ?>" class="time-input" />
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
            <div class="form-actions">
              <button type="submit" class="btn-submit-form">
                <img src="../assets/icons/book.svg" width="16" height="16" alt="" style="filter:brightness(0) invert(1);" />
                Save Shop Information
              </button>
              <p class="form-note">Your shop will be reviewed by admin before going live</p>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <footer class="dashboard-footer">© 2026 All Rights Reserved — Fix It Davao</footer>
  </main>

  <!-- ════════════════ SCRIPTS ════════════════ -->
  <!-- NOTE: leaflet-routing-machine script is REMOVED intentionally -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  window.addEventListener('load', function() {
    document.querySelectorAll('.leaflet-routing-container, .leaflet-routing-alternatives-container, .leaflet-routing-error').forEach(el => el.remove());
    // Also override L.Routing if LRM loads late
    const observer = new MutationObserver(() => {
      document.querySelectorAll('.leaflet-routing-container').forEach(el => el.remove());
    });
    observer.observe(document.body, { childList: true, subtree: true });
  });
</script>
  <script>
    

  // ── Shared helper: build custom popup HTML ───────────────────
  function buildPopupHtml(s) {
    const services   = (s.services || []).slice(0, 3);
    const extraCount = (s.services || []).length - 3;
    const serviceHtml = services.length
      ? services.map(sv =>
          `<div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;border-bottom:1px solid rgba(255,255,255,0.06);">
             <span style="font-size:11px;color:#cbd5e1;">${sv.service_name}</span>
             <span style="font-size:11px;font-weight:700;color:#f59e0b;">₱${Number(sv.service_fee).toLocaleString()}</span>
           </div>`
        ).join('') + (extraCount > 0 ? `<div style="font-size:10px;color:#64748b;margin-top:3px;">+${extraCount} more</div>` : '')
      : `<div style="font-size:11px;color:#64748b;font-style:italic;">No services listed yet</div>`;

    const avg   = parseFloat(s.avg_rating) || 0;
    const cnt   = parseInt(s.review_count) || 0;
    const stars = Array.from({length:5}, (_, i) =>
      `<span style="font-size:11px;color:${i < Math.round(avg) ? '#f59e0b' : '#334155'};">★</span>`
    ).join('');
    const ratingHtml = cnt > 0
      ? `<div style="display:flex;align-items:center;gap:4px;margin-bottom:6px;">${stars}
           <span style="font-size:11px;font-weight:700;color:#f59e0b;">${avg.toFixed(1)}</span>
           <span style="font-size:10px;color:#64748b;">(${cnt})</span>
         </div>`
      : `<div style="font-size:10px;color:#64748b;margin-bottom:6px;">No reviews yet</div>`;

    const logo = s.logo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(s.name)}&background=f59e0b&color=fff&size=80`;

    return `
      <div style="background:#0d1117;border-radius:12px;overflow:hidden;min-width:170px;max-width:220px;font-family:'Outfit',sans-serif;border:1px solid rgba(245,158,11,0.25);">
        <div style="background:linear-gradient(135deg,#1a2540,#141c2e);padding:12px 14px 10px;border-bottom:1px solid rgba(245,158,11,0.15);position:relative;">
          <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#f59e0b,transparent);"></div>
          <div style="display:flex;align-items:center;gap:8px;">
            <img src="${logo}" style="width:34px;height:34px;border-radius:7px;border:2px solid rgba(245,158,11,0.4);object-fit:cover;flex-shrink:0;"
              onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.name)}&background=f59e0b&color=fff&size=80'" />
            <div>
              <div style="font-size:12.5px;font-weight:800;color:#f1f5f9;line-height:1.2;">${s.name}</div>
              <div style="font-size:10px;color:#64748b;margin-top:1px;">📍 ${s.location || 'Location not set'}</div>
            </div>
          </div>
          <div style="margin-top:7px;">${ratingHtml}</div>
        </div>
        <div style="padding:10px 14px;">
          <div style="font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#475569;margin-bottom:6px;">Services</div>
          ${serviceHtml}
        </div>
        <div style="padding:4px 14px 8px;display:flex;align-items:center;gap:5px;">
          <span style="font-size:11px;color:#64748b;">📞</span>
          <span style="font-size:11px;color:#94a3b8;">${s.contact || 'No contact info'}</span>
        </div>
      </div>`;
  }

  // ── Shared helper: OSRM route (zero panel) ───────────────────
 function drawOsrmRoute(map, cLat, cLng, shopLat, shopLng) {
  // Haversine formula — exact distance, zero external API
  const R    = 6371000;
  const dLat = (shopLat - cLat) * Math.PI / 180;
  const dLng = (shopLng - cLng) * Math.PI / 180;
  const a    = Math.sin(dLat/2) * Math.sin(dLat/2) +
               Math.cos(cLat * Math.PI/180) * Math.cos(shopLat * Math.PI/180) *
               Math.sin(dLng/2) * Math.sin(dLng/2);
  const dist  = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  const km    = dist >= 1000
    ? (dist / 1000).toFixed(1) + ' km'
    : Math.round(dist) + ' m';
  const driveMin  = Math.round(dist / 500);  // ~30 km/h average city speed
  const walkMin   = Math.round(dist / 80);   // ~80 m/min walking
  const driveStr  = driveMin >= 60
    ? Math.floor(driveMin/60) + 'h ' + (driveMin%60) + 'min'
    : driveMin + ' min';

  // Draw straight dashed line — no external API needed
  L.polyline([[cLat, cLng],[shopLat, shopLng]], {
    color: '#f59e0b', weight: 3, opacity: 0.7, dashArray: '8,8'
  }).addTo(map);

  // Custom info panel
  const footer = document.getElementById('mapModalAddress');
  if (footer) {
    footer.innerHTML = `
      <div style="width:100%;font-family:'Outfit',sans-serif;">
        <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;
             background:linear-gradient(135deg,#1a2540,#141c2e);flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:6px;
               background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);
               border-radius:8px;padding:6px 12px;">
            <span style="font-size:15px;">📍</span>
            <div>
              <div style="font-size:13px;font-weight:800;color:#f59e0b;">${km}</div>
              <div style="font-size:10px;color:#64748b;">distance</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:6px;
               background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.3);
               border-radius:8px;padding:6px 12px;">
            <span style="font-size:15px;">🚗</span>
            <div>
              <div style="font-size:13px;font-weight:800;color:#60a5fa;">${driveStr}</div>
              <div style="font-size:10px;color:#64748b;">by car</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:6px;
               background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);
               border-radius:8px;padding:6px 12px;">
            <span style="font-size:15px;">🚶</span>
            <div>
              <div style="font-size:13px;font-weight:800;color:#34d399;">${walkMin} min</div>
              <div style="font-size:10px;color:#64748b;">walking</div>
            </div>
          </div>
        </div>
      </div>`;
  }
}
  // ── Shared helper: shop + user markers ───────────────────────
  function makeShopIcon(size = 42) {
    return L.divIcon({
      className: '',
      html: `<div style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;font-size:${size*0.43}px;width:${size}px;height:${size}px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(245,158,11,.55);border:3px solid white;">
        <span style="transform:rotate(45deg);">🔧</span>
      </div>`,
      iconSize:    [size, size * 1.24],
      iconAnchor:  [size/2, size * 1.24],
      popupAnchor: [0, -(size * 1.24) - 4]
    });
  }

  function makeUserIcon(size = 32) {
    return L.divIcon({
      className: '',
      html: `<div style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:white;font-size:13px;width:${size}px;height:${size}px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(37,99,235,.5);border:3px solid white;">📍</div>`,
      iconSize:   [size, size],
      iconAnchor: [size/2, size/2]
    });
  }

  // ── Mobile menu ──────────────────────────────────────────────
  const mobileMenuToggle = document.getElementById('mobileMenuToggle');
  const sidebar = document.querySelector('.sidebar');
if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      document.body.classList.toggle('sidebar-open');
    });

    // Close sidebar when tapping outside — but NOT when tapping a nav link
    document.addEventListener('click', (e) => {
      if (e.target.closest('.nav-item')) return; // let nav links navigate freely
      if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
        sidebar.classList.remove('active');
        document.body.classList.remove('sidebar-open');
      }
    });

    // Close sidebar on nav link tap, then navigate immediately
document.querySelectorAll('.sidebar .nav-item').forEach(link => {
  link.addEventListener('click', function(e) {
    if (this.tagName === 'A' && !this.getAttribute('onclick')) {
      // Disable transition temporarily so close is instant
      sidebar.style.transition = 'none';
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-open');
      // Re-enable transition after navigation (for next open)
      requestAnimationFrame(() => {
        sidebar.style.transition = '';
      });
      // Navigate immediately — no delay
    }
  });
});
  }

  function confirmLogout(e) {
    e.preventDefault();
    sidebar.classList.remove('active');
    document.body.classList.remove('sidebar-open');
    document.getElementById('logoutModal').classList.add('visible');
    return false;
  }
  function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('visible'); }

  // ── Repairshop: logo preview ─────────────────────────────────
  const logoInput = document.getElementById('logoInput');
  if (logoInput) {
    logoInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const r = new FileReader();
        r.onload = (ev) => { document.getElementById('logoImage').src = ev.target.result; };
        r.readAsDataURL(file);
      }
    });
  }

  function addServiceField() {
    const container = document.getElementById('servicesContainer');
    const el = document.createElement('div');
    el.className = 'service-item';
    el.innerHTML = `
      <div class="form-grid">
        <div class="form-group"><label>Service Name</label><input type="text" name="service_name[]" placeholder="e.g., Battery Replacement" class="service-name"></div>
        <div class="form-group"><label>Fee (₱)</label><input type="number" name="service_fee[]" placeholder="800" class="service-fee"></div>
        <div class="form-group"><label>Duration</label><input type="text" name="service_duration[]" placeholder="30 minutes" class="service-duration"></div>
      </div>
      <button type="button" class="btn-remove-service" onclick="this.parentElement.remove()">Remove</button>`;
    container.appendChild(el);
  }

  <?php if ($userRole === 'customer'): ?>
  // ════════════════════════════════════════════════════════════
  // CUSTOMER — SHOP LISTINGS
  // ════════════════════════════════════════════════════════════
  let allShops     = [];
  let activeFilter = 'all';
  let searchQuery  = '';

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function shopAvatarUrl(name, logoUrl) {
    return logoUrl || `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=f59e0b&color=fff&size=128`;
  }
  function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
  }
  function starsHtml(rating, cls = 'card-star', size = '.75rem') {
    const r = parseFloat(rating) || 0;
    return Array.from({length: 5}, (_, i) =>
      `<span class="${cls} ${i < Math.round(r) ? 'filled' : ''}" style="font-size:${size};">★</span>`
    ).join('');
  }

  const FILTER_KEYWORDS = {
    phones:  ['phone','screen','iphone','android','samsung','xiaomi','mobile','battery','charging','reformat','deepcleaning'],
    laptops: ['laptop','macbook','notebook','charger','keyboard','hinge','trackpad','pc','computer','desktop','imac','windows','processor','ram','motherboard','gpu','cpu','reformat','deepcleaning'],
    tablets: ['tablet','ipad','tab','reformat','deepcleaning'],
  };

  function matchesFilter(shop, filter) {
    if (filter === 'all' || filter === 'rating') return true;
    const kw = FILTER_KEYWORDS[filter] || [];
    return (shop.services || []).some(sv => kw.some(k => sv.service_name.toLowerCase().includes(k)));
  }

  function renderShops() {
    const grid    = document.getElementById('shopsGrid');
    const empty   = document.getElementById('emptyState');
    const loading = document.getElementById('shopsLoading');
    loading.style.display = 'none';

    let shops = [...allShops];
    if (searchQuery) {
      shops = shops.filter(s =>
        s.name.toLowerCase().includes(searchQuery) ||
        (s.location || '').toLowerCase().includes(searchQuery)
      );
    }
    shops = shops.filter(s => matchesFilter(s, activeFilter));
    if (activeFilter === 'rating') {
      shops.sort((a, b) => (parseFloat(b.avg_rating) || 0) - (parseFloat(a.avg_rating) || 0));
    }

    if (!shops.length) {
      grid.style.display  = 'none';
      empty.style.display = 'flex';
      return;
    }
    empty.style.display = 'none';
    grid.style.display  = 'grid';

    grid.innerHTML = shops.map((s, idx) => {
      const avatar      = shopAvatarUrl(s.name, s.logo_url);
      const avgRating   = parseFloat(s.avg_rating) || 0;
      const reviewCount = parseInt(s.review_count) || 0;
      const ratingRow   = reviewCount > 0
        ? `<div class="shop-rating">
             <div class="card-stars">${starsHtml(avgRating, 'card-star', '.75rem')}</div>
             <span class="card-rating-num">${avgRating.toFixed(1)}</span>
             <span class="card-review-cnt">(${reviewCount})</span>
           </div>`
        : `<div class="shop-rating"><span style="color:var(--text-muted);font-size:.72rem;">No reviews yet</span></div>`;
      const services    = (s.services || []).slice(0, 3);
      const extraCount  = (s.services || []).length - 3;
      const serviceTags = services.length
        ? services.map(sv =>
            `<span class="service-tag">${escHtml(sv.service_name)}<strong>₱${Number(sv.service_fee).toLocaleString()}</strong></span>`
          ).join('') + (extraCount > 0 ? `<span class="service-tag service-tag-more">+${extraCount} more</span>` : '')
        : `<span style="color:var(--text-muted);font-size:.78rem;font-style:italic;">No services listed yet</span>`;

      return `
        <div class="shop-card" style="animation-delay:${idx * 0.05}s">
          <div class="shop-card-header">
            <img src="${avatar}" alt="${escHtml(s.name)}" class="shop-card-logo"
              onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.name)}&background=f59e0b&color=fff&size=128'" />
            <div class="shop-card-title">
              <h3>${escHtml(s.name)}</h3>
              ${ratingRow}
            </div>
          </div>
          <div class="shop-card-body">
            <div class="shop-info-row">
              <img src="../assets/icons/location.svg" width="13" height="13" alt="" />
              <span>${escHtml(s.location || 'Location not set')}</span>
            </div>
            <div class="shop-info-row">
              <img src="../assets/icons/mobile.svg" width="13" height="13" alt="" />
              <span>${escHtml(s.contact || 'No contact info')}</span>
            </div>
            <div class="shop-services-wrap">${serviceTags}</div>
          </div>
          <div class="shop-card-footer">
            <a href="../customer/book-shop.php?id=${s.id}" class="btn-book-now">Book Now</a>
            <button class="btn-view-details" onclick="openDetailsModal(${s.id})">View Details</button>
          </div>
        </div>`;
    }).join('');
  }

  // ── View Details Modal ───────────────────────────────────────
  const ALL_DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  let sdmMap = null;
  let sdmCurrentShopId = null;

  document.querySelectorAll('.sdm-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.sdm-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.sdm-tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById(`panel-${this.dataset.panel}`).classList.add('active');
      if (this.dataset.panel === 'location') initSdmMap();
    });
  });

  function openDetailsModal(shopId) {
    const s = allShops.find(x => String(x.id) === String(shopId));
    if (!s) return;
    document.querySelectorAll('.sdm-tab').forEach((t, i) => t.classList.toggle('active', i === 0));
    document.querySelectorAll('.sdm-tab-panel').forEach((p, i) => p.classList.toggle('active', i === 0));

    const avatar    = shopAvatarUrl(s.name, s.logo_url);
    const sdmLogoEl = document.getElementById('sdmLogo');
    sdmLogoEl.src   = avatar;
    sdmLogoEl.onerror = () => { sdmLogoEl.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(s.name)}&background=f59e0b&color=fff&size=128`; };
    document.getElementById('sdmName').textContent         = s.name;
    document.getElementById('sdmLocationText').textContent = s.location || 'Location not set';
    document.getElementById('sdmContactText').textContent  = s.contact  || 'No contact info';
    document.getElementById('sdmBookBtn').href = `../customer/book-shop.php?id=${s.id}`;

    const avg = parseFloat(s.avg_rating) || 0;
    const cnt = parseInt(s.review_count) || 0;
    document.getElementById('sdmRatingBadge').innerHTML = cnt > 0
      ? `<div class="sdm-badge-stars">${starsHtml(avg, 'sdm-badge-star', '.75rem')}</div>
         <span class="sdm-badge-num">${avg.toFixed(1)}</span>
         <span class="sdm-badge-cnt">${cnt} review${cnt !== 1 ? 's' : ''}</span>`
      : `<span style="font-size:.7rem;color:var(--text-muted);">No reviews yet</span>`;

    const todayIdx  = new Date().getDay();
    const todayName = ALL_DAYS[todayIdx === 0 ? 6 : todayIdx - 1];
    const hours     = s.operating_hours || {};
    const openDays  = s.open_days || [];
    document.getElementById('sdmHoursGrid').innerHTML = ALL_DAYS.map(day => {
      const isToday    = day === todayName;
      const dayData    = hours[day];
      const isOpen     = dayData ? dayData.is_open !== false : openDays.includes(day);
      const timeText   = dayData && dayData.open && dayData.close
        ? `${formatTime(dayData.open)} – ${formatTime(dayData.close)}` : '';
      const label      = day.charAt(0).toUpperCase() + day.slice(1);
      const todayBadge = isToday ? `<span class="hours-badge-today">TODAY</span>` : '';
      return isOpen
        ? `<div class="hours-row ${isToday ? 'today' : ''}">
             <div class="hours-open-dot"></div>
             <span class="hours-day"><span>${label}</span>${todayBadge}</span>
             <span class="hours-time">${timeText || 'Hours not set'}</span>
           </div>`
        : `<div class="hours-row closed">
             <div class="hours-closed-dot"></div>
             <span class="hours-day"><span>${label}</span>${todayBadge}</span>
             <span class="hours-closed-label">Closed</span>
           </div>`;
    }).join('');

    const services = s.services || [];
    document.getElementById('sdmServicesList').innerHTML = services.length
      ? services.map(sv => `
          <div class="sdm-service-row">
            <span class="sdm-service-name">${escHtml(sv.service_name)}</span>
            <span class="sdm-service-fee">₱${Number(sv.service_fee).toLocaleString()}</span>
          </div>`).join('')
      : `<p class="sdm-no-services">No services listed yet.</p>`;

    const reviews = s.recent_reviews || [];
    let reviewsHtml = '';
    if (cnt > 0) {
      reviewsHtml += `
        <div class="sdm-reviews-summary">
          <div class="sdm-avg-big">${avg.toFixed(1)}</div>
          <div>
            <div class="sdm-avg-stars">${starsHtml(avg, 'sdm-avg-star', '1rem')}</div>
            <div class="sdm-avg-label">${cnt} review${cnt !== 1 ? 's' : ''}</div>
          </div>
        </div>`;
    }
    if (!reviews.length) {
      reviewsHtml += `<div class="sdm-no-reviews">No reviews yet for this shop.<br><small>Be the first to leave a review after booking.</small></div>`;
    } else {
      reviewsHtml += reviews.map(r => {
        const custAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(r.customer_name || 'Customer')}&background=2563eb&color=fff&size=80`;
        const dateStr    = new Date(r.created_at).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'});
        const replyHtml  = r.reply ? `<div class="sdm-rv-reply"><strong>Shop reply:</strong> ${escHtml(r.reply)}</div>` : '';
        return `
          <div class="sdm-review-card">
            <div class="sdm-rv-top">
              <img src="${custAvatar}" alt="${escHtml(r.customer_name)}" class="sdm-rv-avatar"
                onerror="this.src='https://ui-avatars.com/api/?name=Customer&background=2563eb&color=fff&size=80'" />
              <div>
                <div class="sdm-rv-name">${escHtml(r.customer_name || 'Anonymous')}</div>
                <div class="sdm-rv-date">${dateStr}</div>
              </div>
            </div>
            <div class="sdm-rv-stars">${starsHtml(r.rating, 'sdm-rv-star', '.75rem')}</div>
            <div class="sdm-rv-text">${r.comment ? escHtml(r.comment) : '<em style="color:var(--text-muted)">No comment.</em>'}</div>
            ${replyHtml}
          </div>`;
      }).join('');
    }
    document.getElementById('sdmReviewsContent').innerHTML = reviewsHtml;

    sdmCurrentShopId = shopId;
    if (sdmMap) { sdmMap.remove(); sdmMap = null; }
    document.getElementById('shopDetailsModal').classList.add('visible');
  }

  function closeDetailsModal() { document.getElementById('shopDetailsModal').classList.remove('visible'); }

  // ── initSdmMap — OSRM only, zero routing panel ───────────────
  function initSdmMap() {
  const s = allShops.find(x => String(x.id) === String(sdmCurrentShopId));
  if (!s || sdmMap) return;
  const container = document.getElementById('sdmMap');
  if (!container) return;

  if (!s.latitude || !s.longitude) {
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:.82rem;">No location set for this shop.</div>';
    return;
  }

  const lat = parseFloat(s.latitude);
  const lng = parseFloat(s.longitude);

  setTimeout(() => {
    sdmMap = L.map('sdmMap', {
  zoomControl: true,
  dragging: true,
  tap: false,           // disable tap handler — causes issues on iOS
  tapTolerance: 15,
  touchZoom: true,
  scrollWheelZoom: false,
  doubleClickZoom: true,
}).setView([lat, lng], 16);
    setTimeout(() => sdmMap.invalidateSize(), 200);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap'
    }).addTo(sdmMap);

const shopMarker = L.marker([lat, lng], { icon: makeShopIcon(36) })
      .addTo(sdmMap)
      .bindPopup(buildPopupHtml(s), {
        maxWidth: window.innerWidth < 600 ? 160 : 220,
        minWidth: window.innerWidth < 600 ? 140 : 180,
        className: 'custom-shop-popup',
        closeButton: true,
        autoPan: false,
        keepInView: true,
        autoPanPaddingTopLeft: L.point(10, 60),
        autoPanPaddingBottomRight: L.point(10, 10)
      })
      .openPopup();

      // ── Click pin → route ──
    shopMarker.on('click', function() {
      const panel = document.getElementById('sdmTravelPanel');
      if (panel) panel.innerHTML = `<div style="padding:10px 0;font-size:12px;color:var(--text-muted);text-align:center;">⏳ Getting your location...</div>`;
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(pos => {
        const cLat = pos.coords.latitude;
        const cLng = pos.coords.longitude;

        sdmMap.eachLayer(l => { if (l._isUserMarker || l._isRouteLayer) sdmMap.removeLayer(l); });

        const um = L.marker([cLat, cLng], { icon: makeUserIcon(28) }).addTo(sdmMap);
        um._isUserMarker = true;
        um.bindPopup('<div style="font-family:\'Outfit\',sans-serif;font-size:12px;font-weight:700;color:#1e40af;">📍 Your Location</div>');

        sdmMap.fitBounds([[cLat, cLng],[lat, lng]], { padding: [40, 40] });

        fetch(`https://router.project-osrm.org/route/v1/driving/${cLng},${cLat};${lng},${lat}?overview=full&geometries=geojson`)
          .then(r => r.json())
          .then(d => {
            if (!d.routes?.[0]) return;
            const route = d.routes[0];
            const glow = L.geoJSON(route.geometry, { style: { color:'#fbbf24', weight:8, opacity:0.18, lineCap:'round', lineJoin:'round' } }).addTo(sdmMap);
            glow._isRouteLayer = true;
            const line = L.geoJSON(route.geometry, { style: { color:'#f59e0b', weight:3.5, opacity:0.9, lineCap:'round', lineJoin:'round' } }).addTo(sdmMap);
            line._isRouteLayer = true;
            sdmMap.fitBounds(route.geometry.coordinates.map(c => [c[1],c[0]]), { padding:[30,30] });
            renderTravelPanel(route.distance, route.duration);
          })
          .catch(() => {
            const fb = L.polyline([[cLat,cLng],[lat,lng]], { color:'#f59e0b', weight:3, opacity:0.7, dashArray:'8,8' }).addTo(sdmMap);
            fb._isRouteLayer = true;
          });
      }, () => {
        if (panel) panel.innerHTML = `<div style="padding:10px 0;font-size:12px;color:#ef4444;text-align:center;">❌ Location access denied.</div>`;
      });
    });


    
    document.getElementById('sdmMapAddress').innerHTML = `
  <img src="../assets/icons/location.svg" width="12" height="12" style="opacity:.55;flex-shrink:0;" alt="" />
  <span style="font-size:.78rem;font-weight:600;color:var(--text-secondary);">${s.location || 'Location not set'}</span>
`;

    // ── Build travel info panel ──────────────────────────────
    function renderTravelPanel(dist, duration, mode) {
      const distStr = dist >= 1000
        ? (dist / 1000).toFixed(1) + ' km'
        : Math.round(dist) + ' m';

      // Speed multipliers relative to OSRM driving duration
      const modes = [
  { icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path fill="rgb(37,99,235)" d="M199.2 181.4L173.1 256L466.9 256L440.8 181.4C436.3 168.6 424.2 160 410.6 160L229.4 160C215.8 160 203.7 168.6 199.2 181.4zM103.6 260.8L138.8 160.3C152.3 121.8 188.6 96 229.4 96L410.6 96C451.4 96 487.7 121.8 501.2 160.3L536.4 260.8C559.6 270.4 576 293.3 576 320L576 512C576 529.7 561.7 544 544 544L512 544C494.3 544 480 529.7 480 512L480 480L160 480L160 512C160 529.7 145.7 544 128 544L96 544C78.3 544 64 529.7 64 512L64 320C64 293.3 80.4 270.4 103.6 260.8zM192 368C192 350.3 177.7 336 160 336C142.3 336 128 350.3 128 368C128 385.7 142.3 400 160 400C177.7 400 192 385.7 192 368zM480 400C497.7 400 512 385.7 512 368C512 350.3 497.7 336 480 336C462.3 336 448 350.3 448 368C448 385.7 462.3 400 480 400z"/></svg>`, label: 'by car', color: '#60a5fa', bg: 'rgba(37,99,235,0.12)', border: 'rgba(37,99,235,0.3)', time: duration },
  { icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path fill="rgb(168,85,247)" d="M280 80C266.7 80 256 90.7 256 104C256 117.3 266.7 128 280 128L336.6 128L359.1 176.7L264 248C230.6 222.9 189 208 144 208L88 208C74.7 208 64 218.7 64 232C64 245.3 74.7 256 88 256L144 256C222.5 256 287.2 315.6 295.2 392L269.8 392C258.6 332.8 206.5 288 144 288C73.3 288 16 345.3 16 416C16 486.7 73.3 544 144 544C206.5 544 258.5 499.2 269.8 440L320 440C333.3 440 344 429.3 344 416L344 393.5C344 348.4 369.7 308.1 409.5 285.8L421.6 311.9C389.2 335.1 368.1 373.1 368.1 416C368.1 486.7 425.4 544 496.1 544C566.8 544 624.1 486.7 624.1 416C624.1 345.3 566.8 288 496.1 288C485.4 288 475.1 289.3 465.2 291.8L433.8 224L488 224C501.3 224 512 213.3 512 200L512 152C512 138.7 501.3 128 488 128L434.7 128C427.8 128 421 130.2 415.5 134.4L398.4 147.2L373.8 93.9C369.9 85.4 361.4 80 352 80L280 80z"/></svg>`, label: 'by motor', color: '#c084fc', bg: 'rgba(168,85,247,0.12)', border: 'rgba(168,85,247,0.3)', time: duration * 1.25 },
  { icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path fill="rgb(16,185,129)" d="M320 144C350.9 144 376 118.9 376 88C376 57.1 350.9 32 320 32C289.1 32 264 57.1 264 88C264 118.9 289.1 144 320 144zM233.4 291.9L256 269.3L256 338.6C256 366.6 268.2 393.3 289.5 411.5L360.9 472.7C366.8 477.8 370.7 484.8 371.8 492.5L384.4 580.6C386.9 598.1 403.1 610.3 420.6 607.8C438.1 605.3 450.3 589.1 447.8 571.6L435.2 483.5C431.9 460.4 420.3 439.4 402.6 424.2L368.1 394.6L368.1 279.4L371.9 284.1C390.1 306.9 417.7 320.1 446.9 320.1L480.1 320.1C497.8 320.1 512.1 305.8 512.1 288.1C512.1 270.4 497.8 256.1 480.1 256.1L446.9 256.1C437.2 256.1 428 251.7 421.9 244.1L404 221.7C381 192.9 346.1 176.1 309.2 176.1C277 176.1 246.1 188.9 223.4 211.7L188.1 246.6C170.1 264.6 160 289 160 314.5L160 352C160 369.7 174.3 384 192 384C209.7 384 224 369.7 224 352L224 314.5C224 306 227.4 297.9 233.4 291.9z"/></svg>`, label: 'walking', color: '#34d399', bg: 'rgba(16,185,129,0.12)', border: 'rgba(16,185,129,0.3)', time: duration * 8.0 },
];

      function fmtTime(secs) {
        const m = Math.max(1, Math.round(secs / 60));
        return m >= 60
          ? Math.floor(m / 60) + 'h ' + (m % 60) + 'min'
          : m + ' min';
      }

      const modeCards = modes.map(m => `
        <div style="display:flex;align-items:center;gap:6px;
             background:${m.bg};border:1px solid ${m.border};
             border-radius:8px;padding:6px 10px;flex:1;min-width:90px;">
          <div style="flex-shrink:0;">${m.icon}</div>
          <div>
            <div style="font-size:13px;font-weight:800;color:${m.color};line-height:1.2;">${fmtTime(m.time)}</div>
            <div style="font-size:10px;color:#64748b;">${m.label}</div>
          </div>
        </div>`).join('');

      const panel = document.getElementById('sdmTravelPanel');
      if (panel) {
        panel.innerHTML = `
          <div style="font-family:'Outfit',sans-serif;">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;padding:8px 0 0;">
              <span style="font-size:13px;">📍</span>
              <span style="font-size:12px;font-weight:700;color:var(--text-secondary);">${distStr} from your location</span>
              <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">via road route</span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">${modeCards}</div>
          </div>`;
      }
    }

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(pos => {
        const cLat = pos.coords.latitude;
        const cLng = pos.coords.longitude;

        L.marker([cLat, cLng], { icon: makeUserIcon(28) })
          .addTo(sdmMap)
          .bindPopup('<div style="font-family:\'Outfit\',sans-serif;font-size:12px;font-weight:700;color:#1e40af;padding:3px 2px;">📍 Your Location</div>');

        // OSRM route — no routing panel, just polyline + our custom panel
        fetch(`https://router.project-osrm.org/route/v1/driving/${cLng},${cLat};${lng},${lat}?overview=full&geometries=geojson`)
          .then(r => r.json())
          .then(d => {
            if (!d.routes || !d.routes[0]) {
              // fallback straight line
              L.polyline([[cLat, cLng],[lat, lng]], {
                color: '#f59e0b', weight: 3, opacity: 0.7, dashArray: '8,8'
              }).addTo(sdmMap);
              return;
            }
            const route = d.routes[0];
            // Glow layer
            L.geoJSON(route.geometry, {
              style: { color: '#fbbf24', weight: 8, opacity: 0.18, lineCap: 'round', lineJoin: 'round' }
            }).addTo(sdmMap);
            // Main line
            L.geoJSON(route.geometry, {
              style: { color: '#f59e0b', weight: 3.5, opacity: 0.9, lineCap: 'round', lineJoin: 'round' }
            }).addTo(sdmMap);

            const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
            sdmMap.fitBounds(coords, { padding: [30, 30] });

            renderTravelPanel(route.distance, route.duration);
          })
          .catch(() => {
            L.polyline([[cLat, cLng],[lat, lng]], {
              color: '#f59e0b', weight: 3, opacity: 0.7, dashArray: '8,8'
            }).addTo(sdmMap);
          });

      });
    }
  }, 150);
}

  document.getElementById('shopDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetailsModal();
  });

  // ── Filters ──────────────────────────────────────────────────
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      activeFilter = this.dataset.filter || 'all';
      renderShops();
    });
  });

  // ── Search ───────────────────────────────────────────────────
  const locationSearch = document.getElementById('locationSearch');
  const searchBtn      = document.getElementById('searchBtn');
  if (locationSearch) {
    locationSearch.addEventListener('input', () => { searchQuery = locationSearch.value.toLowerCase().trim(); renderShops(); });
    locationSearch.addEventListener('keydown', e => { if (e.key === 'Enter') { searchQuery = locationSearch.value.toLowerCase().trim(); renderShops(); } });
  }
  if (searchBtn) searchBtn.addEventListener('click', () => { searchQuery = (locationSearch?.value || '').toLowerCase().trim(); renderShops(); });

  // ── Load Shops ───────────────────────────────────────────────
  async function loadShops() {
    try {
      const res  = await fetch('../api/get_shops.php?customer=1');
      const data = await res.json();
      allShops = Array.isArray(data) ? data : [];
      renderShops();
    } catch (e) {
      document.getElementById('shopsLoading').style.display = 'none';
      const empty = document.getElementById('emptyState');
      empty.style.display = 'flex';
      empty.querySelector('p').textContent = 'Could not load shops. Please refresh.';
    }
  }
document.getElementById('nearMeBtn').addEventListener('click', function() {
  if (!navigator.geolocation) return alert('Geolocation not supported.');
  navigator.geolocation.getCurrentPosition(pos => {
    const uLat = pos.coords.latitude;
    const uLng = pos.coords.longitude;

    function haversine(lat1, lng1, lat2, lng2) {
      const R = 6371;
      const dLat = (lat2-lat1)*Math.PI/180;
      const dLng = (lng2-lng1)*Math.PI/180;
      const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

const sorted = allShops
  .filter(s => s.latitude && s.longitude)
  .map(s => ({ ...s, _dist: haversine(uLat, uLng, parseFloat(s.latitude), parseFloat(s.longitude)) }))
  .filter(s => s._dist <= 5)        // within 5km lang
  .sort((a, b) => a._dist - b._dist)
  .slice(0, 5);                      // max 5 results

    const grid = document.getElementById('shopsGrid');
    const empty = document.getElementById('emptyState');
    document.getElementById('shopsLoading').style.display = 'none';

   if (!sorted.length) {
  grid.style.display = 'none';
  empty.style.display = 'flex';
  empty.querySelector('h3').textContent = 'No Shops Nearby';
  empty.querySelector('p').textContent = 'No repair shops found within 5km of your location.';
  return;
}

    grid.innerHTML = sorted.map((s, idx) => {
      const distKm = s._dist < 1 ? Math.round(s._dist*1000)+'m' : s._dist.toFixed(1)+'km';
      const avatar = shopAvatarUrl(s.name, s.logo_url);
      const avgRating = parseFloat(s.avg_rating) || 0;
      const reviewCount = parseInt(s.review_count) || 0;
      const ratingRow = reviewCount > 0
        ? `<div class="shop-rating"><div class="card-stars">${starsHtml(avgRating,'card-star','.75rem')}</div><span class="card-rating-num">${avgRating.toFixed(1)}</span><span class="card-review-cnt">(${reviewCount})</span></div>`
        : `<div class="shop-rating"><span style="color:var(--text-muted);font-size:.72rem;">No reviews yet</span></div>`;
      const services = (s.services||[]).slice(0,3);
      const extraCount = (s.services||[]).length - 3;
      const serviceTags = services.length
        ? services.map(sv=>`<span class="service-tag">${escHtml(sv.service_name)}<strong>₱${Number(sv.service_fee).toLocaleString()}</strong></span>`).join('')+(extraCount>0?`<span class="service-tag service-tag-more">+${extraCount} more</span>`:'')
        : `<span style="color:var(--text-muted);font-size:.78rem;font-style:italic;">No services listed yet</span>`;

      return `
        <div class="shop-card" style="animation-delay:${idx*0.05}s">
          <div class="shop-card-header">
            <img src="${avatar}" alt="${escHtml(s.name)}" class="shop-card-logo" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.name)}&background=f59e0b&color=fff&size=128'" />
            <div class="shop-card-title">
              <h3>${escHtml(s.name)}</h3>
              ${ratingRow}
            </div>
            <span style="margin-left:auto;background:#dcfce7;color:#059669;font-size:.7rem;font-weight:700;padding:3px 8px;border-radius:20px;border:1px solid #bbf7d0;white-space:nowrap;">📍 ${distKm}</span>
          </div>
          <div class="shop-card-body">
            <div class="shop-info-row"><img src="../assets/icons/location.svg" width="13" height="13" alt="" /><span>${escHtml(s.location||'Location not set')}</span></div>
            <div class="shop-info-row"><img src="../assets/icons/mobile.svg" width="13" height="13" alt="" /><span>${escHtml(s.contact||'No contact info')}</span></div>
            <div class="shop-services-wrap">${serviceTags}</div>
          </div>
          <div class="shop-card-footer">
            <a href="../customer/book-shop.php?id=${s.id}" class="btn-book-now">Book Now</a>
            <button class="btn-view-details" onclick="openDetailsModal(${s.id})">View Details</button>
          </div>
        </div>`;
    }).join('');
  }, () => alert('Could not get your location. Please enable GPS.'));
});
  loadShops();
  <?php endif; ?>

  // ════════════════════════════════════════════════════════════
  // NOTIFICATIONS
  // ════════════════════════════════════════════════════════════
  let notifOpen = false;

  async function loadNotifications() {
    try {
      const res  = await fetch('../api/get_notifications.php');
      const data = await res.json();
      if (!data.success) return;
      const badge = document.getElementById('notifBadge');
      const list  = document.getElementById('notifList');
      if (data.unread_count > 0) {
        badge.textContent   = data.unread_count > 9 ? '9+' : data.unread_count;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
      if (!data.notifications || !data.notifications.length) {
        list.innerHTML = `<div class="notif-empty" style="padding:2rem 1rem;text-align:center;">
          <div style="font-size:.78rem;color:var(--text-muted);">No notifications yet.</div>
        </div>`;
        return;
      }
      const STATUS_MSG = {
        confirmed:    (shop)        => `<span>${shop}</span> confirmed your booking!`,
        completed:    (shop)        => `Your repair at <span>${shop}</span> is complete.`,
        cancelled:    (shop)        => `<span>${shop}</span> cancelled your booking.`,
        review_reply: (shop, reply) => `<span style="font-weight:800;color:var(--brand-dark);">${shop}:</span> ${reply}`,
      };
      list.innerHTML = data.notifications.map(n => {
        const logo      = n.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(n.shop_name || 'Shop')}&background=f59e0b&color=fff&size=80`;
        const msg       = STATUS_MSG[n.status] ? STATUS_MSG[n.status](n.shop_name || 'Shop', n.reply || '') : `<span>${n.shop_name || 'Shop'}:</span> ${n.reply || n.status}`;
        const time      = n.time ? new Date(n.time).toLocaleDateString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}) : '';
        const dest      = n.status === 'review_reply' ? '../customer/history.php' : '../customer/my-bookings.php';
        const replyHtml = (n.reply && n.status !== 'review_reply')
          ? `<div style="margin-top:5px;padding:5px 8px;background:var(--brand-faint);border-left:2px solid var(--brand);border-radius:0 4px 4px 0;font-size:.7rem;color:var(--text-primary);line-height:1.4;">
               <span style="font-weight:700;color:var(--brand-dark);">Shop replied:</span> ${n.reply}
             </div>` : '';
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
    } catch (e) { console.error('Notif error:', e); }
  }

  function toggleNotifDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    notifOpen = !notifOpen;
    dropdown.classList.toggle('open', notifOpen);
    if (notifOpen) { loadNotifications(); markAllRead(); }
  }

  async function markAllRead() {
    await fetch('../api/get_notifications.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({mark_read: true})
    });
    document.getElementById('notifBadge').style.display = 'none';
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    document.querySelectorAll('.notif-dot').forEach(el => el.remove());
  }

document.addEventListener('click', (e) => {
    if (e.target.closest('.nav-item')) return; // don't interfere with nav
    const wrapper = document.querySelector('.notif-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      document.getElementById('notifDropdown')?.classList.remove('open');
      notifOpen = false;
    }
  });

  loadNotifications();

  // ════════════════════════════════════════════════════════════
  // MAP MODAL — OSRM only, zero routing panel
  // ════════════════════════════════════════════════════════════
  let leafletMap = null;

  function openMapModal(shopId) {
  const s = allShops.find(x => String(x.id) === String(shopId));
  if (!s || !s.latitude || !s.longitude) return;

  document.getElementById('mapModalTitle').textContent = s.name;
  document.getElementById('mapModalAddress').innerHTML =
    `<div style="padding:12px 16px;color:#64748b;font-size:12px;font-family:'Outfit',sans-serif;">
       📍 Getting your location...
     </div>`;
  document.getElementById('mapModal').classList.add('visible');

  setTimeout(() => {
    if (leafletMap) { leafletMap.remove(); leafletMap = null; }

    const shopLat = parseFloat(s.latitude);
    const shopLng = parseFloat(s.longitude);

    leafletMap = L.map('leafletMap', {
      zoomControl: true,
      tap: false,
      dragging: true
    }).setView([shopLat, shopLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap'
    }).addTo(leafletMap);

    L.marker([shopLat, shopLng], { icon: makeShopIcon(42) })
      .addTo(leafletMap)
      .bindPopup(buildPopupHtml(s), {
  maxWidth: 220, minWidth: 180,
  className: 'custom-shop-popup',
  closeButton: true,
  autoPan: false,
  keepInView: false
})
      .openPopup();

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(pos => {
        const cLat = pos.coords.latitude;
        const cLng = pos.coords.longitude;

        L.marker([cLat, cLng], { icon: makeUserIcon(32) })
          .addTo(leafletMap)
          .bindPopup('<div style="font-family:\'Outfit\',sans-serif;font-size:12px;font-weight:700;color:#1e40af;padding:4px 2px;">📍 Your Location</div>');

        leafletMap.fitBounds([[cLat, cLng],[shopLat, shopLng]], { padding: [60, 60] });

        fetch(`https://router.project-osrm.org/route/v1/driving/${cLng},${cLat};${shopLng},${shopLat}?overview=full&geometries=geojson`)
          .then(r => r.json())
          .then(data => {
            if (!data.routes || !data.routes[0]) return;
            const route = data.routes[0];

            // Actual road route line
            L.geoJSON(route.geometry, {
              style: { color: '#fbbf24', weight: 8, opacity: 0.2, lineCap: 'round', lineJoin: 'round' }
            }).addTo(leafletMap);
            L.geoJSON(route.geometry, {
              style: { color: '#f59e0b', weight: 3.5, opacity: 0.9, lineCap: 'round', lineJoin: 'round' }
            }).addTo(leafletMap);

            // Fit to actual route
            const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
            leafletMap.fitBounds(coords, { padding: [50, 50] });

            const dist    = route.distance;
            const km      = dist >= 1000 ? (dist/1000).toFixed(1)+' km' : Math.round(dist)+' m';
            const carMin  = Math.max(1, Math.round(route.duration / 60));
            const bikeMin = Math.max(1, Math.round(route.duration / 60 * 1.3));
            const walkMin = Math.max(1, Math.round(dist / 80));
            const carStr  = carMin  >= 60 ? Math.floor(carMin/60)+'h '+(carMin%60)+'min' : carMin+'min';
            const bikeStr = bikeMin >= 60 ? Math.floor(bikeMin/60)+'h '+(bikeMin%60)+'min' : bikeMin+'min';
            const walkStr = walkMin >= 60 ? Math.floor(walkMin/60)+'h '+(walkMin%60)+'min' : walkMin+'min';

            document.getElementById('mapModalAddress').innerHTML = `
              <div style="width:100%;font-family:'Outfit',sans-serif;">
                <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;
                     background:linear-gradient(135deg,#1a2540,#141c2e);flex-wrap:wrap;">
                  <div style="display:flex;align-items:center;gap:6px;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);border-radius:8px;padding:6px 12px;">
                    <span style="font-size:15px;">📍</span>
                    <div><div style="font-size:13px;font-weight:800;color:#f59e0b;">${dist >= 1000 ? (dist/1000).toFixed(1)+' km' : Math.round(dist)+' m'}</div><div style="font-size:10px;color:#64748b;">distance</div></div>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.3);border-radius:8px;padding:6px 12px;">
                    <div style="flex-shrink:0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path opacity="0.3" fill="rgb(37,99,235)" d="M199.2 181.4L173.1 256L466.9 256L440.8 181.4C436.3 168.6 424.2 160 410.6 160L229.4 160C215.8 160 203.7 168.6 199.2 181.4zM103.6 260.8L138.8 160.3C152.3 121.8 188.6 96 229.4 96L410.6 96C451.4 96 487.7 121.8 501.2 160.3L536.4 260.8C559.6 270.4 576 293.3 576 320L576 512C576 529.7 561.7 544 544 544L512 544C494.3 544 480 529.7 480 512L480 480L160 480L160 512C160 529.7 145.7 544 128 544L96 544C78.3 544 64 529.7 64 512L64 320C64 293.3 80.4 270.4 103.6 260.8zM192 368C192 350.3 177.7 336 160 336C142.3 336 128 350.3 128 368C128 385.7 142.3 400 160 400C177.7 400 192 385.7 192 368zM480 400C497.7 400 512 385.7 512 368C512 350.3 497.7 336 480 336C462.3 336 448 350.3 448 368C448 385.7 462.3 400 480 400z"/></svg></div>

                    <div><div style="font-size:13px;font-weight:800;color:#60a5fa;">${carStr}</div><div style="font-size:10px;color:#64748b;">by car</div></div>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;background:rgba(168,85,247,0.12);border:1px solid rgba(168,85,247,0.3);border-radius:8px;padding:6px 12px;">
                    <div style="flex-shrink:0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path opacity="0.3" fill="rgb(168,85,247)" d="M280 80C266.7 80 256 90.7 256 104C256 117.3 266.7 128 280 128L336.6 128L359.1 176.7L264 248C230.6 222.9 189 208 144 208L88 208C74.7 208 64 218.7 64 232C64 245.3 74.7 256 88 256L144 256C222.5 256 287.2 315.6 295.2 392L269.8 392C258.6 332.8 206.5 288 144 288C73.3 288 16 345.3 16 416C16 486.7 73.3 544 144 544C206.5 544 258.5 499.2 269.8 440L320 440C333.3 440 344 429.3 344 416L344 393.5C344 348.4 369.7 308.1 409.5 285.8L421.6 311.9C389.2 335.1 368.1 373.1 368.1 416C368.1 486.7 425.4 544 496.1 544C566.8 544 624.1 486.7 624.1 416C624.1 345.3 566.8 288 496.1 288C485.4 288 475.1 289.3 465.2 291.8L433.8 224L488 224C501.3 224 512 213.3 512 200L512 152C512 138.7 501.3 128 488 128L434.7 128C427.8 128 421 130.2 415.5 134.4L398.4 147.2L373.8 93.9C369.9 85.4 361.4 80 352 80L280 80z"/></svg></div>

                    <div><div style="font-size:13px;font-weight:800;color:#c084fc;">${bikeStr}</div><div style="font-size:10px;color:#64748b;">by motor</div></div>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:6px 12px;">
                    <div style="flex-shrink:0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20"><path opacity="0.3" fill="rgb(16,185,129)" d="M320 144C350.9 144 376 118.9 376 88C376 57.1 350.9 32 320 32C289.1 32 264 57.1 264 88C264 118.9 289.1 144 320 144zM233.4 291.9L256 269.3L256 338.6C256 366.6 268.2 393.3 289.5 411.5L360.9 472.7C366.8 477.8 370.7 484.8 371.8 492.5L384.4 580.6C386.9 598.1 403.1 610.3 420.6 607.8C438.1 605.3 450.3 589.1 447.8 571.6L435.2 483.5C431.9 460.4 420.3 439.4 402.6 424.2L368.1 394.6L368.1 279.4L371.9 284.1C390.1 306.9 417.7 320.1 446.9 320.1L480.1 320.1C497.8 320.1 512.1 305.8 512.1 288.1C512.1 270.4 497.8 256.1 480.1 256.1L446.9 256.1C437.2 256.1 428 251.7 421.9 244.1L404 221.7C381 192.9 346.1 176.1 309.2 176.1C277 176.1 246.1 188.9 223.4 211.7L188.1 246.6C170.1 264.6 160 289 160 314.5L160 352C160 369.7 174.3 384 192 384C209.7 384 224 369.7 224 352L224 314.5C224 306 227.4 297.9 233.4 291.9z"/></svg></div>
                    <div><div style="font-size:13px;font-weight:800;color:#34d399;">${walkStr}</div><div style="font-size:10px;color:#64748b;">walking</div></div>
                  </div>
                </div>
              </div>`;
          })
          .catch(() => {
            L.polyline([[cLat, cLng],[shopLat, shopLng]], {
              color: '#f59e0b', weight: 3, opacity: 0.7, dashArray: '8,8'
            }).addTo(leafletMap);
          });

      }, () => {
        document.getElementById('mapModalAddress').innerHTML =
          `<div style="padding:10px 16px;color:#64748b;font-size:12px;font-family:'Outfit',sans-serif;">
             📍 ${s.location || ''} &nbsp;·&nbsp; <em>Enable location for directions</em>
           </div>`;
      });
    }
  }, 120);
}

  function closeMapModal() {
    document.getElementById('mapModal').classList.remove('visible');
    if (leafletMap) { leafletMap.remove(); leafletMap = null; }
  }

  document.getElementById('mapModal').addEventListener('click', function(e) {
    if (e.target === this) closeMapModal();
  });
  
function openProfileModal() {
  const saved = localStorage.getItem('profilePic_<?php echo $userId; ?>');
  const avatarEl = document.getElementById('profileInitials');
  if (saved) {
    avatarEl.innerHTML = `<img src="${saved}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" />`;
  } else {
    avatarEl.textContent = '<?php echo strtoupper(substr($userName, 0, 2)); ?>';
    avatarEl.style.background = 'linear-gradient(135deg,#ff6b35,#ef4444)';
  }
  document.getElementById('profileName').textContent  = '<?php echo htmlspecialchars($userName); ?>';
  document.getElementById('profileRole').textContent  = '<?php echo $roleLabel; ?>';
  document.getElementById('profileEmail').textContent = '<?php echo htmlspecialchars($userEmail); ?>';
  document.getElementById('profileType').textContent  = '<?php echo ucfirst($userRole); ?>';
  document.getElementById('profileModal').classList.add('visible');
}
function closeProfileModal() {
  document.getElementById('profileModal').classList.remove('visible');
}
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

// Auto-load saved pic sa top-bar avatar
(function() {
  const saved = localStorage.getItem('profilePic_<?php echo $userId; ?>');
  if (saved) {
    const topAvatar = document.querySelector('.user-avatar');
    if (topAvatar) topAvatar.src = saved;
  }
})();
document.getElementById('profileModal').addEventListener('click', function(e) {
  if (e.target === this) closeProfileModal();
});

  </script>
  <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>

</body>
</html>