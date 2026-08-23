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

// ── DB connection ─────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

// ── Load shop logo ────────────────────────────────────────────
$r = $conn->query("SHOW COLUMNS FROM `users` LIKE 'logo_url'");
if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `users` ADD COLUMN `logo_url` VARCHAR(255) DEFAULT NULL");
$stmt = $conn->prepare("SELECT logo_url FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$savedLogoUrl = $row['logo_url'] ?? '';
if ($savedLogoUrl) {
    $baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http')
             .'://'.$_SERVER['HTTP_HOST']
             .rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\').'/';
    $savedLogoUrl = $baseUrl . $savedLogoUrl;
}
$avatarUrl = $savedLogoUrl ?: "https://ui-avatars.com/api/?name=".urlencode($userName)."&background=f59e0b&color=fff";

// ── Load saved services from DB ───────────────────────────────
$userId = (int) $userId;
$stmt = $conn->prepare("SELECT service_name, service_fee, service_duration FROM services WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$svcResult = $stmt->get_result();
$dbServices = [];
while ($row = $svcResult->fetch_assoc()) $dbServices[] = $row;
$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Services &amp; Fees - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="apple-touch-icon" href="../assets/images/logo.png" />
    <link rel="shortcut icon" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>
      .dashboard-footer { text-align: center; padding: 16px 24px; font-size: 11px; color: #94a3b8; letter-spacing: 0.5px; font-family: "Outfit", sans-serif; font-weight: 500; border-top: 1px solid #e2e8f0; margin-top: auto; }
      .user-avatar { object-fit: cover; }
      .services-list { display: flex; flex-direction: column; gap: 16px; }
      .service-card { background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 18px; transition: box-shadow 0.2s; }
      .service-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
      .service-icon-circle { width: 52px; height: 52px; min-width: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
      .service-body { flex: 1; }
      .service-title { font-weight: 600; font-size: 15px; margin-bottom: 6px; color: var(--text-primary, #1e293b); }
      .service-meta { display: flex; gap: 20px; flex-wrap: wrap; }
      .service-meta span { font-size: 13px; color: var(--text-muted, #64748b); }
      .service-meta strong { color: var(--text-primary, #1e293b); }
      .summary-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
      .summary-chip { background: var(--bg-primary, #f1f5f9); border-radius: 10px; padding: 10px 18px; flex: 1; min-width: 140px; text-align: center; }
      .summary-chip .chip-val { font-size: 22px; font-weight: 700; color: var(--primary, #2563eb); }
      .summary-chip .chip-label { font-size: 12px; color: var(--text-muted, #64748b); margin-top: 2px; }
      .top-bar      { animation: fadeInUp 0.4s ease both; }
      .summary-row  { animation: fadeInUp 0.5s ease both; }
      #servicesList { animation: fadeInUp 0.6s ease both; }
      @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

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
          <a href="shop-information.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/shop.svg" alt="My Shop" /></span><span class="nav-text">My Shop</span></a>
          <a href="shop-bookings.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/booking.svg" alt="Bookings" /></span><span class="nav-text">Bookings</span></a>
          <a href="shop-services.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/services.svg" alt="Services" /></span><span class="nav-text">Services &amp; Fees</span></a>
          <a href="shop-reviews.php" class="nav-item"><span class="nav-icon"><img src="../assets/icons/reviews.svg" alt="Reviews" /></span><span class="nav-text">Reviews</span></a>
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

    <main class="main-content">
      <header class="top-bar">
        <div class="page-header"><h1 class="current-page-title">Services &amp; Fees</h1></div>
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
        <div class="summary-row">
          <div class="summary-chip"><div class="chip-val" id="totalServices">0</div><div class="chip-label">Total Services</div></div>
          <div class="summary-chip"><div class="chip-val" id="avgFee">—</div><div class="chip-label">Avg. Fee</div></div>
          <div class="summary-chip"><div class="chip-val" id="priceRange">—</div><div class="chip-label">Price Range</div></div>
        </div>

        <div class="services-list" id="servicesList"></div>

        <p style="text-align:center; margin-top:24px; font-size:13px; color:#94a3b8;">
          To add or edit services, go to
          <a href="shop-information.php" style="color:#2563eb; font-weight:600;">My Shop</a>.
        </p>
      </div>

      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script>
      // ── Services injected from DB via PHP ──────────────────────
      const services = <?php echo json_encode(array_map(function($s) {
        return [
          'name'     => $s['service_name'],
          'fee'      => (float)$s['service_fee'],
          'duration' => $s['service_duration'],
        ];
      }, $dbServices)); ?>;

      const iconColors = ["#fef3c7","#dbeafe","#e0e7ff","#f3e8ff","#dcfce7","#ffe4e6"];
      const icons      = ["🔧","📱","💻","🖥️","📲","⚙️"];

      function renderServices() {
        const list = document.getElementById("servicesList");
        list.innerHTML = "";

        if (services.length === 0) {
          list.innerHTML = `<div style="text-align:center;padding:60px 20px;color:#94a3b8">
            <img src="../assets/icons/services.svg" alt="No services" width="64" height="64" style="opacity:0.4;margin-bottom:16px;display:block;margin-left:auto;margin-right:auto" />
            <h3 style="font-size:1.1rem;font-weight:600;color:#64748b;margin-bottom:8px">No Services Yet</h3>
            <p style="font-size:0.9rem">Go to <a href="shop-information.php" style="color:#2563eb;font-weight:600;">My Shop</a> to add your services.</p>
          </div>`;
          updateSummary(); return;
        }

        services.forEach((svc, i) => {
          const div = document.createElement("div");
          div.className = "service-card";
          div.innerHTML = `
            <div class="service-icon-circle" style="background:${iconColors[i % iconColors.length]}">${icons[i % icons.length]}</div>
            <div class="service-body">
              <div class="service-title">${svc.name}</div>
              <div class="service-meta">
                <span>Fee: <strong>₱${Number(svc.fee).toLocaleString()}</strong></span>
                <span>Duration: <strong>${svc.duration || '—'}</strong></span>
              </div>
            </div>`;
          list.appendChild(div);
        });

        updateSummary();
      }

      function updateSummary() {
        document.getElementById("totalServices").textContent = services.length;
        if (services.length === 0) {
          document.getElementById("avgFee").textContent = "—";
          document.getElementById("priceRange").textContent = "—";
          return;
        }
        const fees = services.map(s => s.fee);
        const avg  = Math.round(fees.reduce((a,b) => a+b, 0) / fees.length);
        document.getElementById("avgFee").textContent    = "₱" + avg.toLocaleString();
        document.getElementById("priceRange").textContent = "₱" + Math.min(...fees).toLocaleString() + " – ₱" + Math.max(...fees).toLocaleString();
      }

      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
      const sidebar = document.querySelector(".sidebar");
      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener("click", function () { sidebar.classList.toggle("active"); document.body.classList.toggle("sidebar-open"); });
        document.addEventListener("click", function (e) { if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) { sidebar.classList.remove("active"); document.body.classList.remove("sidebar-open"); } });
      }

      renderServices();

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

// ── Shop Notifications ───────────────────────────────────────
let notifOpen = false;

async function loadNotifications() {
  const badge = document.getElementById('notifBadge');
  const list  = document.getElementById('notifList');
  try {
    const res  = await fetch('../api/get_shop_notifications.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    if (!text.trim()) { list.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;">No notifications yet.</div>'; return; }
    const data = JSON.parse(text);
    if (!data.success) { list.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;">No notifications yet.</div>'; return; }

    // Badge
    if (data.unread_count > 0) {
      badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }

      if (!data.notifications || !data.notifications.length) {
      list.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;"><img src="../assets/icons/bell.svg" width="32" height="32" style="opacity:.3;display:block;margin:0 auto 8px;" />No notifications yet.</div>';
      return;
    }

    const MSG = {
      pending:   (n) => `<span style="font-weight:800;">${n.customer_name}</span> booked your shop! 📬`,
      cancelled: (n) => `<span style="font-weight:800;">${n.customer_name}</span> cancelled their booking.`,
    };
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
  const bg       = n.is_read ? '' : 'background:#fffbeb;';
  const dest     = n.type === 'review' ? 'shop-reviews.php' : n.type === 'subscription' ? 'shop-subscription.php' : 'shop-bookings.php';
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
    document.getElementById('notifList').innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;">No notifications yet.</div>';
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
  document.querySelectorAll('[style*="background:#fffbeb"]').forEach(el => el.style.background = '');
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