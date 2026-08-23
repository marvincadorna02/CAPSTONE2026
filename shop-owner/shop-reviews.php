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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userName  = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

// ── Load shop logo ────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if (!$conn->connect_error) {
    $r = $conn->query("SHOW COLUMNS FROM `users` LIKE 'logo_url'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `users` ADD COLUMN `logo_url` VARCHAR(255) DEFAULT NULL");
    $stmt = $conn->prepare("SELECT logo_url, shop_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close(); $conn->close();
$savedLogoUrl = $row['logo_url'] ?? '';
// Use shop_name from DB if set, fallback to session name
if (!empty($row['shop_name'])) {
    $userName = $row['shop_name'];
    $_SESSION['name'] = $userName; // keep session in sync
}
} else { $savedLogoUrl = ''; }
$avatarUrl = $savedLogoUrl ?: "https://ui-avatars.com/api/?name=".urlencode($userName)."&background=f59e0b&color=fff";
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Reviews - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>
      .top-bar        { animation: fadeInUp 0.4s ease both; }
      .review-summary { animation: fadeInUp 0.5s ease both; }
      .reviews-filter { animation: fadeInUp 0.55s ease both; }
      #reviewsList    { animation: fadeInUp 0.6s ease both; }
      @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

      /* ── SUMMARY ── */
      .review-summary { display:flex; gap:16px; margin-bottom:24px; align-items:stretch; }
      .rating-big {
        background:white; border:1px solid #e2e8f0; border-radius:16px;
        padding:20px 24px; text-align:center; flex:0 0 auto;
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        gap:6px; min-width:110px;
      }
      .big-num   { font-size:40px; font-weight:800; color:#0f172a; font-family:'Space Mono',monospace; line-height:1; }
      .big-stars { display:flex; gap:3px; justify-content:center; }
      .big-star  { font-size:1rem; color:#d1d5db; transition:color .3s; }
      .big-star.filled { color:#f59e0b; }
      .big-label { font-size:11px; color:#94a3b8; font-weight:500; white-space:nowrap; }

      .rating-bars {
        flex:1; background:white; border:1px solid #e2e8f0; border-radius:16px;
        padding:16px 20px; display:flex; flex-direction:column; justify-content:center;
        gap:8px; min-width:0;
      }
      .bar-row   { display:flex; align-items:center; gap:8px; font-size:12px; }
      .bar-label { width:14px; text-align:right; color:#64748b; font-weight:600; flex-shrink:0; }
      .bar-track { flex:1; height:7px; background:#f1f5f9; border-radius:4px; overflow:hidden; min-width:0; }
      .bar-fill  { height:100%; border-radius:4px; background:#f59e0b; transition:width .6s ease; }
      .bar-count { width:20px; color:#94a3b8; font-size:11px; text-align:right; flex-shrink:0; }

      /* ── FILTER TABS ── */
  .reviews-filter {
  display:flex; align-items:center; gap:5px; margin-bottom:20px;
  flex-wrap:nowrap;
}
.reviews-filter::-webkit-scrollbar { display:none; }
      .reviews-filter::-webkit-scrollbar { display:none; }
      .filter-label { font-size:12px; color:#64748b; font-weight:600; white-space:nowrap; flex-shrink:0; }
      .tab-btn {
  display:inline-flex; align-items:center; gap:4px;
  padding:7px 10px; background:white; border:2px solid #e2e8f0;
  border-radius:20px; font-size:13px; font-weight:600; color:#64748b;
  cursor:pointer; transition:all .2s; white-space:nowrap; flex-shrink:0;
  font-family:"Outfit",sans-serif;
}
      .tab-btn:hover { border-color:#f59e0b; color:#f59e0b; }
      .tab-btn.active { background:#f59e0b; border-color:#f59e0b; color:white; }

      /* ── LOADING ── */
      .loading-state { text-align:center; padding:3rem 2rem; color:#94a3b8; }
      .spinner { width:32px; height:32px; border:3px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 12px; }
      @keyframes spin { to { transform:rotate(360deg); } }

      /* ── REVIEW CARD ── */
      .review-card {
        background:white; border:1.5px solid #e2e8f0; border-radius:14px;
        padding:18px; margin-bottom:12px; transition:box-shadow .2s, border-color .2s;
        animation:fadeInUp .35s ease both;
      }
      .review-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.08); border-color:#fde68a; }

      .review-top { display:flex; gap:12px; align-items:flex-start; margin-bottom:10px; }
      .review-avatar { width:42px; height:42px; border-radius:10px; object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0; }
      .reviewer-name { font-weight:700; font-size:14px; color:#0f172a; }
      .reviewer-sub  { font-size:12px; color:#94a3b8; margin-top:2px; }

      .review-stars { display:flex; gap:2px; margin-bottom:8px; }
      .rv-star { font-size:15px; color:#d1d5db; }
      .rv-star.filled { color:#f59e0b; }

      .review-text { font-size:13px; color:#475569; line-height:1.6; }
      .review-service-tag {
        display:inline-block; margin-top:10px;
        background:#eff6ff; color:#1e40af; font-size:11px;
        padding:4px 10px; border-radius:6px; font-weight:600; border:1px solid #dbeafe;
      }

      /* Reply section */
      .reply-wrap { margin-top:14px; padding-top:14px; border-top:1px solid #f1f5f9; }
      .reply-label { font-size:11px; font-weight:700; color:#94a3b8; margin-bottom:8px; text-transform:uppercase; letter-spacing:.5px; }

      /* Posted reply */
      .reply-posted-box {
        background:#fffbeb; border:1px solid #fde68a; border-radius:10px;
        padding:10px 14px; display:flex; gap:10px; align-items:flex-start;
      }
      .reply-posted-icon { font-size:1.2rem; flex-shrink:0; }
      .reply-posted-text { font-size:13px; color:#374151; line-height:1.55; }
      .reply-author { font-weight:700; color:#d97706; }
      .reply-date   { font-size:11px; color:#94a3b8; margin-top:3px; }

      /* Reply input */
      .reply-input-row { display:flex; gap:8px; align-items:flex-end; }
      .reply-input-row textarea {
        flex:1; resize:none; border:2px solid #e2e8f0; border-radius:10px;
        padding:10px 14px; font-family:"Outfit",sans-serif; font-size:13px;
        background:#f8fafc; min-height:64px; transition:border-color .2s; color:#0f172a;
      }
      .reply-input-row textarea:focus { outline:none; border-color:#f59e0b; background:white; }
      .btn-reply {
        padding:10px 16px; border:none; border-radius:10px;
        background:#f59e0b; color:white; font-family:"Outfit",sans-serif;
        font-size:13px; font-weight:700; cursor:pointer; flex-shrink:0;
        transition:all .2s; white-space:nowrap;
      }
      .btn-reply:hover { background:#d97706; transform:translateY(-1px); }
      .btn-reply:disabled { opacity:.5; cursor:not-allowed; transform:none; }

      /* ── EMPTY ── */
      .empty-state {
        text-align:center; padding:50px 20px;
        background:#f8fafc; border-radius:14px; border:2px dashed #e2e8f0;
      }
      .empty-state img { opacity:.25; margin-bottom:1rem; }
      .empty-state h3  { font-size:1rem; font-weight:700; color:#64748b; margin-bottom:6px; }
      .empty-state p   { font-size:.85rem; color:#94a3b8; max-width:300px; margin:0 auto; }

      /* ── LOGOUT MODAL ── */
      .modal-overlay { position:fixed; inset:0; background:rgba(10,15,30,.72); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:opacity .3s ease; padding:20px; }
      .modal-overlay.visible { opacity:1; pointer-events:all; }
      .modal-box { background:white; border-radius:20px; padding:32px 28px; max-width:420px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,.25); transform:scale(.9) translateY(20px); opacity:0; transition:transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease; }
      .modal-overlay.visible .modal-box { transform:scale(1) translateY(0); opacity:1; }
      .modal-title    { font-size:18px; font-weight:800; color:#0f172a; margin-bottom:6px; font-family:"Outfit",sans-serif; }
      .modal-subtitle { font-size:13px; color:#64748b; }
      .modal-actions  { display:flex; gap:10px; margin-top:20px; justify-content:center; }
      .modal-btn-cancel  { flex:1; padding:11px; border:2px solid #e2e8f0; border-radius:10px; background:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; color:#64748b; transition:all .2s; }
      .modal-btn-cancel:hover { background:#f8fafc; }
      .modal-btn-confirm { flex:1; padding:11px; border:none; border-radius:10px; color:white; font-size:13px; font-weight:700; font-family:"Outfit",sans-serif; cursor:pointer; transition:all .2s; }
      .modal-btn-confirm:hover { transform:translateY(-1px); opacity:.9; }

      .dashboard-footer { text-align:center; padding:16px 24px; font-size:11px; color:#94a3b8; letter-spacing:.5px; font-family:"Outfit",sans-serif; font-weight:500; border-top:1px solid #e2e8f0; margin-top:auto; }

      @media (max-width:768px) {
        .review-summary { gap:10px; }
        .rating-big { padding:14px 16px; min-width:90px; border-radius:12px; }
        .big-num { font-size:32px; }
        .rating-bars { padding:12px 14px; gap:6px; border-radius:12px; }
        .reply-input-row { flex-direction:column; }
        .btn-reply { width:100%; padding:11px; }
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
          <a href="shop-bookings.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/booking.svg" alt="" /></span><span class="nav-text">Bookings</span></a>
          <a href="shop-services.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/services.svg" alt="" /></span><span class="nav-text">Services &amp; Fees</span></a>
          <a href="shop-reviews.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/reviews.svg" alt="" /></span><span class="nav-text">Reviews</span></a>
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

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">Reviews</h1></div>
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

        <!-- Summary -->
        <div class="review-summary">
          <div class="rating-big">
            <div class="big-num" id="avgRatingNum">—</div>
            <div class="big-stars" id="bigStars">
              <span class="big-star">★</span><span class="big-star">★</span>
              <span class="big-star">★</span><span class="big-star">★</span><span class="big-star">★</span>
            </div>
            <div class="big-label" id="reviewCountLabel">Loading...</div>
          </div>
          <div class="rating-bars" id="ratingBars">
            <div class="loading-state" style="padding:1rem;">
              <div class="spinner"></div>
            </div>
          </div>
        </div>

        <!-- Filter -->
        <div class="reviews-filter">
          <span class="filter-label">Filter:</span>
          <button class="tab-btn active" data-star="all">All</button>
          <button class="tab-btn" data-star="5">★ 5</button>
          <button class="tab-btn" data-star="4">★ 4</button>
          <button class="tab-btn" data-star="3">★ 3</button>
          <button class="tab-btn" data-star="2">★ 2</button>
          <button class="tab-btn" data-star="1">★ 1</button>
        </div>

        <!-- Reviews list -->
        <div id="reviewsList">
          <div class="loading-state">
            <div class="spinner"></div>
            <p>Loading reviews...</p>
          </div>
        </div>

      </div>

      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script>
      const SHOP_NAME = <?php echo json_encode($userName); ?>;
      let allReviews  = [];
      let currentStar = 'all';

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

      // ── Load reviews ─────────────────────────────────────────
      async function loadReviews() {
        try {
          const res  = await fetch('../api/get_shop_reviews.php');
          const data = await res.json();
          if (data.error) throw new Error(data.error);
          allReviews = data.reviews || [];
          renderSummary(data.avg_rating, data.total, data.counts);
          renderReviews();
        } catch(e) {
          document.getElementById('reviewsList').innerHTML = `<p style="color:#ef4444;text-align:center;">Failed to load reviews. Please refresh.</p>`;
        }
      }

      // ── Summary bar ──────────────────────────────────────────
      function renderSummary(avg, total, counts) {
        document.getElementById('avgRatingNum').textContent = total > 0 ? avg.toFixed(1) : '—';
        document.getElementById('reviewCountLabel').textContent = `${total} Review${total !== 1 ? 's' : ''}`;

        // Big stars
        const bigStars = document.querySelectorAll('.big-star');
        const rounded  = Math.round(avg);
        bigStars.forEach((s, i) => s.classList.toggle('filled', i < rounded));

        // Bars
        const barsHtml = [5,4,3,2,1].map(star => {
          const cnt = counts[star] || 0;
          const pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
          return `<div class="bar-row">
            <span class="bar-label">${star}</span>
            <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
            <span class="bar-count">${cnt}</span>
          </div>`;
        }).join('');
        document.getElementById('ratingBars').innerHTML = barsHtml;
      }

      // ── Render review cards ──────────────────────────────────
      function renderReviews() {
        const list = document.getElementById('reviewsList');

        const filtered = currentStar === 'all'
          ? allReviews
          : allReviews.filter(r => parseInt(r.rating) === parseInt(currentStar));

        if (!allReviews.length) {
          list.innerHTML = `
            <div class="empty-state">
              <img src="../assets/icons/star.svg" alt="No reviews" width="56" height="56" />
              <h3>No Reviews Yet</h3>
              <p>Your shop hasn't received any reviews yet. Reviews will appear here once customers rate your services.</p>
            </div>`;
          return;
        }

        if (!filtered.length) {
          list.innerHTML = `
            <div class="empty-state">
              <img src="../assets/icons/star.svg" alt="No reviews" width="56" height="56" />
              <h3>No ${currentStar}-Star Reviews</h3>
              <p>No reviews with this rating yet.</p>
            </div>`;
          return;
        }

        list.innerHTML = filtered.map((r, i) => {
          const custAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(r.customer_name||'Customer')}&background=2563eb&color=fff&size=80`;
          const stars = Array.from({length:5}, (_, idx) =>
            `<span class="rv-star ${idx < r.rating ? 'filled' : ''}">★</span>`).join('');
          const dateStr = new Date(r.created_at).toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'});

          const replySection = r.reply
            ? `<div class="reply-wrap">
                 <div class="reply-label">Your Reply</div>
                 <div class="reply-posted-box">
                   <span class="reply-posted-icon">💬</span>
                   <div>
                     <div class="reply-posted-text"><span class="reply-author">${esc(SHOP_NAME)}:</span> ${esc(r.reply)}</div>
                     ${r.replied_at ? `<div class="reply-date">${new Date(r.replied_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</div>` : ''}
                   </div>
                 </div>
               </div>`
            : `<div class="reply-wrap">
                 <div class="reply-label">Reply to this review</div>
                 <div class="reply-input-row">
                   <textarea id="replyBox_${r.id}" placeholder="Write a professional reply to this customer..."></textarea>
                   <button class="btn-reply" onclick="postReply(${r.id}, this)">Send Reply</button>
                 </div>
               </div>`;

          return `
            <div class="review-card" style="animation-delay:${i * 0.05}s" id="review-card-${r.id}">
              <div class="review-top">
                <img src="${custAvatar}" alt="${esc(r.customer_name)}" class="review-avatar"
                  onerror="this.src='https://ui-avatars.com/api/?name=Customer&background=2563eb&color=fff&size=80'" />
                <div class="review-meta">
                  <div class="reviewer-name">${esc(r.customer_name || 'Anonymous')}</div>
                  <div class="reviewer-sub">${dateStr}</div>
                </div>
              </div>
              <div class="review-stars">${stars}</div>
              <div class="review-text">${r.comment ? esc(r.comment) : '<em style="color:#94a3b8;">No comment left.</em>'}</div>
              ${r.service_name ? `<span class="review-service-tag">🔧 ${esc(r.service_name)}</span>` : ''}
              ${replySection}
            </div>`;
        }).join('');
      }

      // ── Post reply ───────────────────────────────────────────
      async function postReply(reviewId, btn) {
        const box  = document.getElementById(`replyBox_${reviewId}`);
        const text = box ? box.value.trim() : '';
        if (!text) { box.style.borderColor = '#ef4444'; box.focus(); return; }
        box.style.borderColor = '';

        btn.disabled = true; btn.textContent = 'Sending...';
        try {
          const fd = new FormData();
          fd.append('review_id', reviewId);
          fd.append('reply', text);
          fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
          const res  = await fetch('reply_review.php', { method:'POST', body:fd });
          const data = await res.json();
          if (data.success) {
            // Update local data and re-render
            const rv = allReviews.find(r => r.id == reviewId);
            if (rv) { rv.reply = text; rv.replied_at = new Date().toISOString(); }
            renderReviews();
          } else {
            alert('Error: ' + (data.error || 'Failed to post reply.'));
            btn.disabled = false; btn.textContent = 'Send Reply';
          }
        } catch(e) {
          alert('Network error. Please try again.');
          btn.disabled = false; btn.textContent = 'Send Reply';
        }
      }

      // ── Filter tabs ──────────────────────────────────────────
      document.querySelectorAll('.reviews-filter .tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          document.querySelectorAll('.reviews-filter .tab-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          currentStar = this.dataset.star;
          renderReviews();
        });
      });

      // ── Helpers ──────────────────────────────────────────────
      function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

      // ── Init ─────────────────────────────────────────────────
      loadReviews();

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