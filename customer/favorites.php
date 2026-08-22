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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

// ── Fetch profile picture from DB (not localStorage — device-specific) ──
$userProfilePic = null;
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if (!$conn->connect_error) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $userProfilePic = $row['profile_picture'] ?? null;
    $stmt->close();
    $conn->close();
}

$avatarUrl = $userProfilePic ?: ("https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff");
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Favorites - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>

      /* ── NOTIFICATIONS ── */
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
      .top-bar        { animation: fadeInUp 0.4s ease both; }
      #favoritesGrid  { animation: fadeInUp 0.5s ease both; }
      @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

      /* ── GRID ── */
      #favoritesGrid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.1rem;
      }

      /* ── SHOP CARD ── */
      .fav-card {
        background: white; border-radius: 18px; border: 1.5px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden;
        transition: box-shadow .2s, border-color .2s, transform .2s;
        animation: fadeInUp .35s ease both;
        display: flex; flex-direction: column;
      }
      .fav-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,.11); border-color: #fbbf24; transform: translateY(-2px); }

      /* Card top banner */
      .fav-card-banner {
        height: 72px;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        position: relative; flex-shrink: 0;
      }
      .fav-card-banner-remove {
        position: absolute; top: 10px; right: 10px;
        width: 30px; height: 30px; border-radius: 8px;
        border: none; background: rgba(255,255,255,.75);
        font-size: .9rem; cursor: pointer; display: flex;
        align-items: center; justify-content: center;
        transition: all .2s; backdrop-filter: blur(4px);
      }
      .fav-card-banner-remove:hover { background: white; transform: scale(1.1); }

      /* Logo */
      .fav-logo-wrap {
        position: absolute; bottom: -22px; left: 16px;
        width: 52px; height: 52px; border-radius: 14px;
        border: 3px solid white; overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
      }
      .fav-logo-wrap img { width: 100%; height: 100%; object-fit: cover; }

      /* Body */
      .fav-card-body { padding: 2rem 1rem 1rem; flex: 1; }
      .fav-shop-name { font-size: .95rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
      .fav-shop-loc  {
        font-size: .76rem; color: #64748b; display: flex;
        align-items: center; gap: 4px; margin-bottom: .75rem;
      }
      .fav-shop-loc img { opacity: .5; flex-shrink: 0; }

      /* Rating row */
      .fav-rating-row { display: flex; align-items: center; gap: 6px; margin-bottom: .75rem; }
      .fav-stars { display: flex; gap: 2px; }
      .fav-star  { font-size: .8rem; color: #d1d5db; }
      .fav-star.filled { color: #f59e0b; }
      .fav-rating-num { font-size: .78rem; font-weight: 700; color: #92400e; }
      .fav-rating-count { font-size: .72rem; color: #94a3b8; }

      /* Tags */
      .fav-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: .75rem; }
      .fav-tag {
        font-size: .7rem; font-weight: 700; padding: 3px 9px;
        border-radius: 20px; background: #f1f5f9; color: #475569;
      }
      .fav-tag.bookings { background: #dbeafe; color: #1e40af; }

      /* Contact */
      .fav-contact { font-size: .76rem; color: #64748b; display: flex; align-items: center; gap: 5px; }
      .fav-contact img { opacity: .5; }

      /* Footer */
      .fav-card-footer {
        padding: .75rem 1rem; border-top: 1px solid #f1f5f9;
        display: flex; gap: 7px;
      }
      .fav-btn {
        flex: 1; padding: 9px; border-radius: 10px; font-size: .8rem;
        font-weight: 700; font-family: "Outfit",sans-serif;
        cursor: pointer; border: none; transition: all .2s;
        display: flex; align-items: center; justify-content: center; gap: 5px;
      }
      .fav-btn img { width: 13px; height: 13px; }
      .fav-btn-book { background: linear-gradient(135deg,#f59e0b,#d97706); color: white; box-shadow: 0 3px 10px rgba(245,158,11,.3); }
      .fav-btn-book:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(245,158,11,.4); }
      .fav-btn-remove { background: #fee2e2; color: #991b1b; flex: 0 0 auto; padding: 9px 13px; }
      .fav-btn-remove:hover { background: #fecaca; }
      .fav-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }

      /* ── LOADING ── */
      .loading-state { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
      .spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #f59e0b; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 14px; }
      @keyframes spin { to { transform: rotate(360deg); } }

      /* ── EMPTY ── */
      .empty-state {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 4rem 2rem; text-align: center;
        animation: fadeInUp .5s ease both;
      }
      .empty-state img  { width: 72px; height: 72px; opacity: .35; margin-bottom: 1.25rem; }
      .empty-state h3   { font-size: 1.1rem; font-weight: 700; color: #64748b; margin-bottom: 6px; }
      .empty-state p    { font-size: .875rem; color: #94a3b8; margin-bottom: 1.5rem; }
      .btn-find-shops {
        display: inline-flex; align-items: center; gap: 6px; padding: .65rem 1.5rem;
        border-radius: 12px; background: linear-gradient(135deg,#f59e0b,#d97706);
        color: white; font-weight: 700; font-size: .875rem;
        font-family: "Outfit",sans-serif; text-decoration: none;
        box-shadow: 0 4px 14px rgba(245,158,11,.35); transition: all .2s;
      }
      .btn-find-shops:hover { transform: translateY(-2px); }

      /* ── MODALS ── */
      .modal-overlay {
        position: fixed; inset: 0; background: rgba(10,15,30,.75);
        backdrop-filter: blur(4px); display: flex; align-items: center;
        justify-content: center; z-index: 1000; opacity: 0;
        pointer-events: none; transition: opacity .3s ease; padding: 20px;
      }
      .modal-overlay.visible { opacity: 1; pointer-events: all; }
      .modal-box {
        background: white; border-radius: 20px; padding: 32px 28px;
        max-width: 420px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,.25);
        transform: scale(.9) translateY(20px); opacity: 0;
        transition: transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
      }
      .modal-overlay.visible .modal-box { transform: scale(1) translateY(0); opacity: 1; }
      .modal-title    { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 6px; font-family: "Outfit",sans-serif; }
      .modal-subtitle { font-size: 13px; color: #64748b; }
      .modal-actions  { display: flex; gap: 10px; margin-top: 20px; }
      .modal-btn-cancel  { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s; }
      .modal-btn-cancel:hover { background: #f8fafc; }
      .modal-btn-confirm { flex:1; padding:11px; border:none; border-radius:10px; color:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; transition:all .2s; }
      .modal-btn-confirm:hover { transform: translateY(-1px); opacity: .9; }

      .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:.5px; font-family:"Outfit",sans-serif; font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }

      @media (max-width: 600px) {
        #favoritesGrid { grid-template-columns: 1fr; }
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
          <a href="favorites.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/favorite.svg" alt="" /></span><span class="nav-text">Favorites</span></a>
          <a href="history.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/history.svg" alt="" /></span><span class="nav-text">History</span></a>
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

    <!-- Remove Confirm Modal -->
    <div class="modal-overlay" id="removeModal">
      <div class="modal-box" style="text-align:center;">
        <div style="margin-bottom:12px;">
  <img src="../assets/icons/hawa.svg" width="48" height="48" alt="" style="opacity:.7;" />
</div>
        <div class="modal-title">Remove Favorite?</div>
        <div class="modal-subtitle" id="removeModalSubtitle" style="margin-bottom:24px;"></div>
        <div class="modal-actions" style="justify-content:center;">
          <button class="modal-btn-cancel" onclick="closeRemoveModal()">Cancel</button>
          <button class="modal-btn-confirm" id="removeConfirmBtn" style="background:linear-gradient(135deg,#ef4444,#dc2626);">Yes, Remove</button>
        </div>
      </div>
    </div>

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header">
          <h1 class="current-page-title">My Favorite Shops <span id="favCount" style="font-size:.85rem;font-weight:600;color:#94a3b8;"></span></h1>
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

        <div class="loading-state" id="loadingState">
          <div class="spinner"></div>
          <p>Loading your favorites...</p>
        </div>

        <div id="favoritesGrid" style="display:none;"></div>

        <div class="empty-state" id="emptyState" style="display:none;">
          <img src="../assets/icons/favorite.svg" alt="No favorites" />
          <h3>No Favorite Shops Yet</h3>
          <p>Complete a booking and tap <img src="../assets/icons/puso.svg" width="14" height="14" alt="" style="width:25px;height:25px;vertical-align:middle;opacity:.7;display:inline;" /> on the shop in your History to save it here.</p>
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
  const serverPic = <?php echo json_encode($userProfilePic); ?>;
  const saved = serverPic || localStorage.getItem('profilePic_<?php echo $userId; ?>');
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
      let allFavorites = [];

      // ── Load ─────────────────────────────────────────────────
      async function loadFavorites() {
        try {
          const res  = await fetch('../api/get_my_favorites.php');
          const data = await res.json();
          if (data.error) throw new Error(data.error);
          allFavorites = data.shops || [];
          renderFavorites();
        } catch(e) {
          document.getElementById('loadingState').innerHTML = `<p style="color:#ef4444;">Failed to load. Please refresh.</p>`;
        }
      }

      // ── Render ───────────────────────────────────────────────
      function renderFavorites() {
        document.getElementById('loadingState').style.display = 'none';
        const grid  = document.getElementById('favoritesGrid');
        const empty = document.getElementById('emptyState');
        const count = document.getElementById('favCount');

        if (!allFavorites.length) {
          grid.style.display  = 'none';
          empty.style.display = 'flex';
          count.textContent   = '';
          return;
        }

        empty.style.display = 'none';
        grid.style.display  = 'grid';
        count.textContent   = `(${allFavorites.length})`;

        grid.innerHTML = allFavorites.map((s, i) => {
          const logo = s.shop_logo
            ? s.shop_logo
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(s.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;

          // Stars
          const avgRating = parseFloat(s.avg_rating) || 0;
          const stars = Array.from({length:5}, (_, idx) => {
            const filled = idx < Math.round(avgRating);
            return `<span class="fav-star ${filled ? 'filled' : ''}">★</span>`;
          }).join('');

          const ratingHtml = s.review_count > 0
            ? `<div class="fav-rating-row">
                 <div class="fav-stars">${stars}</div>
                 <span class="fav-rating-num">${avgRating.toFixed(1)}</span>
                 <span class="fav-rating-count">(${s.review_count} review${s.review_count!=1?'s':''})</span>
               </div>`
            : `<div class="fav-rating-row"><span class="fav-rating-count" style="font-size:.74rem;">No reviews yet</span></div>`;

          return `
            <div class="fav-card" style="animation-delay:${i * 0.06}s" id="fav-card-${s.shop_id}">
              <div class="fav-card-banner">
                <div class="fav-logo-wrap">
                  <img src="${logo}" alt="${esc(s.shop_name)}"
                    onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.shop_name||'Shop')}&background=f59e0b&color=fff&size=80'" />
                </div>
                <button class="fav-card-banner-remove" title="Remove from favorites"
                  onclick="confirmRemove(${s.shop_id}, '${esc(s.shop_name)}')">✕</button>
              </div>

              <div class="fav-card-body">
                <div class="fav-shop-name">${esc(s.shop_name || 'Repair Shop')}</div>
                ${s.shop_location
                  ? `<div class="fav-shop-loc"><img src="../assets/icons/location.svg" width="13" height="13" alt="" />${esc(s.shop_location)}</div>`
                  : ''}
                ${ratingHtml}
                <div class="fav-tags">
                  <span class="fav-tag bookings"><img src="../assets/icons/naysu.svg" width="11" height="11" alt="" style="vertical-align:middle;margin-right:3px;" /> ${s.completed_count} completed booking${s.completed_count!=1?'s':''}</span>
<span class="fav-tag"><img src="../assets/icons/puso.svg" width="11" height="11" alt="" style="vertical-align:middle;margin-right:3px;" /> Favorited ${fmtDate(s.favorited_at)}</span>
                </div>
                ${s.shop_contact
                  ? `<div class="fav-contact"><img src="../assets/icons/mobile.svg" width="12" height="12" alt="" />${esc(s.shop_contact)}</div>`
                  : ''}
              </div>

              <div class="fav-card-footer">
                <button class="fav-btn fav-btn-book" onclick="window.location.href='book-shop.php?id=${s.shop_id}'">
                  <img src="../assets/icons/book.svg" alt="" style="filter:brightness(0) invert(1);" /> Book Now
                </button>
              </div>
            </div>`;
        }).join('');
      }

      // ── Remove Favorite ──────────────────────────────────────
      let pendingRemoveShopId   = null;
      let pendingRemoveShopName = null;

      function confirmRemove(shopId, shopName) {
        pendingRemoveShopId   = shopId;
        pendingRemoveShopName = shopName;
        document.getElementById('removeModalSubtitle').textContent =
          `Remove "${shopName}" from your favorites?`;
        document.getElementById('removeConfirmBtn').onclick = () => doRemove(shopId);
        document.getElementById('removeModal').classList.add('visible');
      }

      function closeRemoveModal() {
        document.getElementById('removeModal').classList.remove('visible');
        pendingRemoveShopId = null;
      }

      async function doRemove(shopId) {
        closeRemoveModal();
        const card = document.getElementById(`fav-card-${shopId}`);
        if (card) { card.style.opacity = '.4'; card.style.pointerEvents = 'none'; }

        try {
          const fd = new FormData();
          fd.append('shop_id', shopId);
          fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
          const res  = await fetch('toggle_favorite.php', { method:'POST', body:fd });
          const data = await res.json();
          if (data.success) {
            // Remove from local array and re-render
            allFavorites = allFavorites.filter(s => s.shop_id != shopId);
            renderFavorites();
          } else {
            alert('Error: ' + (data.error || 'Failed to remove.'));
            if (card) { card.style.opacity = ''; card.style.pointerEvents = ''; }
          }
        } catch(e) {
          alert('Network error. Please try again.');
          if (card) { card.style.opacity = ''; card.style.pointerEvents = ''; }
        }
      }

      // Close modals on backdrop click
      document.getElementById('removeModal').addEventListener('click', function(e) { if(e.target===this) closeRemoveModal(); });

      // ── Helpers ──────────────────────────────────────────────
      function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
      function fmtDate(d) {
        if (!d) return '';
        return new Date(d).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
      }

      // ── Init ─────────────────────────────────────────────────
      loadFavorites();

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
  const serverPic = <?php echo json_encode($userProfilePic); ?>;
  const saved = serverPic || localStorage.getItem('profilePic_<?php echo $userId; ?>');
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
  const serverPic = <?php echo json_encode($userProfilePic); ?>;
  const saved = serverPic || localStorage.getItem('profilePic_<?php echo $userId; ?>');
  if (saved) {
    const topAvatar = document.querySelector('.user-avatar');
    if (topAvatar) topAvatar.src = saved;
  }
})();
</script>
  </body>
</html>