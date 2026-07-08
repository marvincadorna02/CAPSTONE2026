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
if ($_SESSION['role'] !== 'repairshop') { header("Location: dashboard.php"); exit(); }

$userName  = $_SESSION['name'];
// Use shop_name for display if it exists
$conn_temp = new mysqli("localhost", "root", "", "fixitdavao");
$stmt_temp = $conn_temp->prepare("SELECT shop_name FROM users WHERE id = ?");
$stmt_temp->bind_param("i", $_SESSION['user_id']);
$stmt_temp->execute();
$row_temp = $stmt_temp->get_result()->fetch_assoc();
$stmt_temp->close();
$conn_temp->close();
if (!empty($row_temp['shop_name'])) $userName = $row_temp['shop_name'];
$userEmail = $_SESSION['email'];
$userId    = $_SESSION['user_id'];

// ── DB connection ─────────────────────────────────────────────
$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

// ── Auto-create missing columns ───────────────────────────────
function addColIfMissing($conn, $table, $col, $def) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $def");
}
addColIfMissing($conn, 'users', 'shop_name',      "VARCHAR(255) DEFAULT NULL");
addColIfMissing($conn, 'users', 'shop_location',  "VARCHAR(255) DEFAULT NULL");
addColIfMissing($conn, 'users', 'contact_number', "VARCHAR(50) DEFAULT NULL");
addColIfMissing($conn, 'users', 'logo_url',       "VARCHAR(255) DEFAULT NULL");

// ── Auto-create services & operating_hours tables ─────────────
$conn->query("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    service_fee DECIMAL(10,2) DEFAULT 0,
    service_duration VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS operating_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL
)");

// ── Load existing shop data ───────────────────────────────────
$stmt = $conn->prepare("SELECT name, shop_name, shop_location, contact_number, logo_url, latitude, longitude FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

$savedName = htmlspecialchars($shop['name'] ?? $shop['shop_name'] ?? '');
$savedLocation = htmlspecialchars($shop['shop_location']  ?? '');
$savedContact  = htmlspecialchars($shop['contact_number'] ?? '');
$savedLogoUrl  = $shop['logo_url'] ?? '';

// Build absolute logo URL
if ($savedLogoUrl) {
    $baseUrl    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    $savedLogoUrl = $baseUrl . $savedLogoUrl;
}

// ── Load existing services ────────────────────────────────────
$svcResult = $conn->query("SELECT service_name, service_fee, service_duration FROM services WHERE user_id = $userId ORDER BY id ASC");
$services  = [];
while ($row = $svcResult->fetch_assoc()) $services[] = $row;

// ── Load existing operating hours ────────────────────────────
$hrsResult = $conn->query("SELECT day, open_time, close_time FROM operating_hours WHERE user_id = $userId");
$hours     = [];
while ($row = $hrsResult->fetch_assoc()) $hours[$row['day']] = $row;

$savedLat  = $shop['latitude']  ?? '';
$savedLng  = $shop['longitude'] ?? '';
$conn->close();

$avatarUrl = $savedLogoUrl ?: "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=f59e0b&color=fff";

// Show success toast if redirected after save
$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Shop - Fix It Davao</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png" />
    <link rel="apple-touch-icon" href="../assets/images/logo.png" />
    <link rel="shortcut icon" href="../assets/images/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
    <style>
      .dashboard-footer {
        text-align: center; padding: 16px 24px; font-size: 11px; color: #94a3b8;
        letter-spacing: 0.5px; font-family: "Outfit", sans-serif; font-weight: 500;
        border-top: 1px solid #e2e8f0; margin-top: auto;
      }
      /* ── Success toast ── */
      .toast {
        position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(80px);
        background: #10b981; color: white; padding: 12px 24px; border-radius: 12px;
        font-size: 0.875rem; font-weight: 600; font-family: "Outfit", sans-serif;
        box-shadow: 0 8px 24px rgba(16,185,129,0.35); z-index: 9999;
        opacity: 0; transition: all 0.4s cubic-bezier(0.34,1.56,0.64,1);
        display: flex; align-items: center; gap: 8px; white-space: nowrap;
      }
      .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

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

.notif-dropdown {
  position:absolute; top:calc(100% + 10px); right:0;
  width:320px; background:white; border-radius:16px;
  border:1.5px solid #e2e8f0; box-shadow:0 10px 40px rgba(0,0,0,.15);
  z-index:500; display:none; overflow:hidden;
  animation:fadeInUp .2s ease both;
}
.notif-dropdown.open { display:block; }

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

/* ── MAP PICKER ── */
#mapPickerSection { margin-top: 12px; }
#shopMapPicker { height: 300px; width: 100%; border-radius: 12px; border: 1.5px solid #e2e8f0; overflow: hidden; }
.map-coords-row { display: flex; gap: 10px; margin-top: 10px; }
.map-coords-row .form-group { flex: 1; }
.map-picker-hint { font-size: .78rem; color: #64748b; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
    </style>
    <link rel="manifest" href="../manifest.json" />
<meta name="theme-color" content="#f59e0b">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('../service-worker.js');
  }
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  </head>
  <body class="role-repairshop">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <?php if ($saved): ?>
    <div class="toast" id="savedToast">✅ Shop information saved successfully!</div>
    <script>
      window.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('savedToast');
        setTimeout(() => t.classList.add('show'), 100);
        setTimeout(() => t.classList.remove('show'), 3500);
      });
    </script>
    <?php endif; ?>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
        <h2 class="brand-name">FIX IT DAVAO</h2>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section" data-role="repairshop">
          <a href="shop-information.php" class="nav-item active">
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
        <div class="page-header">
          <h1 class="current-page-title">My Shop</h1>
        </div>
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
        <div class="form-container">
          <div class="form-header">
            <h2>My Repair Shop Information</h2>
            <p>Update your shop details, services, and availability</p>
          </div>

          <form id="shopForm" class="shop-form" method="POST" action="save-shop.php" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />

            <!-- ── Shop Logo ── -->
            <div class="form-section">
              <h3 class="section-title">Shop Logo</h3>
              <div class="logo-upload-area">
                <div class="logo-preview" id="logoPreview">
                  <img
                    src="<?php echo $savedLogoUrl ?: 'https://ui-avatars.com/api/?name=' . urlencode($savedName ?: 'Shop') . '&background=f59e0b&color=fff&size=150'; ?>"
                    alt="Shop Logo"
                    id="logoImage"
                    style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"
                  />
                </div>
                <div class="upload-controls">
                  <input type="file" id="logoInput" name="shop_logo" accept="image/*" style="display:none" />
                  <button type="button" class="btn-upload" style="display:flex;align-items:center;gap:8px"
                    onclick="document.getElementById('logoInput').click()">
                    <img src="../assets/icons/camera.svg" alt="Upload" width="18" height="18" />
                    <?php echo $savedLogoUrl ? 'Change Logo' : 'Upload Logo'; ?>
                  </button>
                  <p class="upload-hint">Recommended: 500x500px, Max 5MB</p>
                </div>
              </div>
            </div>

            <!-- ── Basic Information ── -->
            <div class="form-section">
              <h3 class="section-title">Basic Information</h3>
              <div class="form-grid">
                <div class="form-group full-width">
                  <label for="shopName">Shop Name *</label>
                  <input type="text" id="shopName" name="shop_name"
                    value="<?php echo $savedName; ?>"
                    placeholder="Enter your repair shop name" required />
                </div>
                <div class="form-group full-width">
                  <label for="shopLocation">Location *</label>
                  <input type="text" id="shopLocation" name="shop_location"
                    value="<?php echo $savedLocation; ?>"
                    placeholder="Complete address (e.g., 123 Main St, Davao City)" required />
                </div>
                <div class="form-group full-width" id="mapPickerSection">
                  <label>Pin Your Shop Location <span style="color:#94a3b8;font-weight:400;font-size:.78rem;">(click map to set)</span></label>
<div style="display:flex;gap:8px;margin-bottom:8px;">
  <input type="text" id="mapSearchInput" placeholder="Search address (e.g., Matina, Davao City)..."
    style="flex:1;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.83rem;font-family:'Outfit',sans-serif;outline:none;" />
  <button type="button" id="mapSearchBtn"
    style="padding:9px 16px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;font-weight:700;font-size:.83rem;border:none;border-radius:10px;cursor:pointer;font-family:'Outfit',sans-serif;white-space:nowrap;">
    Search
  </button>
</div>
<div id="shopMapPicker"></div>
                  <div class="map-picker-hint">Click anywhere on the map to drop your shop pin</div>
                  <div class="map-coords-row">
                    <div class="form-group">
                      <label style="font-size:.75rem;">Latitude</label>
                      <input type="text" id="latInput" name="latitude" value="<?php echo $savedLat; ?>" placeholder="e.g., 7.0707" readonly style="background:#f8fafc;font-size:.82rem;" />
                    </div>
                    <div class="form-group">
                      <label style="font-size:.75rem;">Longitude</label>
                      <input type="text" id="lngInput" name="longitude" value="<?php echo $savedLng; ?>" placeholder="e.g., 125.6087" readonly style="background:#f8fafc;font-size:.82rem;" />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="contactNumber">Contact Number *</label>
                  <input type="tel" id="contactNumber" name="contact_number"
                    value="<?php echo $savedContact; ?>"
                    placeholder="0917-123-4567" required />
                </div>
                <div class="form-group">
                  <label for="email">Email Address *</label>
                  <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($userEmail); ?>"
                    placeholder="shop@example.com" required />
                </div>
              </div>
            </div>

            <!-- ── Services & Fees ── -->
            <div class="form-section">
              <h3 class="section-title">Services &amp; Fees</h3>
              <div id="servicesContainer">
                <?php if (!empty($services)): ?>
                  <?php foreach ($services as $svc): ?>
                  <div class="service-item">
                    <div class="form-grid">
                      <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="service_name[]"
                          value="<?php echo htmlspecialchars($svc['service_name']); ?>"
                          placeholder="e.g., Screen Replacement" class="service-name" />
                      </div>
                      <div class="form-group">
                        <label>Fee (₱)</label>
                        <input type="number" name="service_fee[]"
                          value="<?php echo htmlspecialchars($svc['service_fee']); ?>"
                          placeholder="1500" class="service-fee" />
                      </div>
                      <div class="form-group">
                        <label>Duration</label>
                        <input type="text" name="service_duration[]"
                          value="<?php echo htmlspecialchars($svc['service_duration']); ?>"
                          placeholder="1-2 hours" class="service-duration" />
                      </div>
                    </div>
                    <button type="button" class="btn-remove-service" style="display:flex;align-items:center;gap:6px;" onclick="this.parentElement.remove()"><img src="../assets/icons/remove.svg" alt="Remove" width="16" height="16" /> Remove</button>
                  </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <!-- Default blank row if no services saved yet -->
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
                        <input type="text" name="service_duration[]" placeholder="1-2 hours" class="service-duration" />
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-add-service" onclick="addServiceField()"
                style="display:flex;align-items:center;justify-content:center;gap:8px">
                <img src="../assets/icons/add.svg" alt="Add" width="16" height="16" /> Add Another Service
              </button>
            </div>

            <!-- ── Operating Hours ── -->
            <div class="form-section">
              <h3 class="section-title">Operating Hours</h3>
              <div class="schedule-grid">
                <?php
                $allDays      = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                $defaultOpen  = ['monday','tuesday','wednesday','thursday','friday'];
                $defaultStart = ['saturday' => '10:00', 'sunday' => '10:00'];
                $defaultEnd   = ['saturday' => '15:00', 'sunday' => '15:00'];
                foreach ($allDays as $day):
                    $hasAnySaved = !empty($hours);
$checked = $hasAnySaved
    ? (isset($hours[$day]) ? 'checked' : '')
    : (in_array($day, $defaultOpen) ? 'checked' : '');
                    $start = ($hasAnySaved && isset($hours[$day])) ? substr($hours[$day]['open_time'],  0, 5) : ($defaultStart[$day] ?? '09:00');
$end   = ($hasAnySaved && isset($hours[$day])) ? substr($hours[$day]['close_time'], 0, 5) : ($defaultEnd[$day]   ?? '18:00');
                ?>
                <div class="day-schedule">
                  <input type="checkbox" id="<?php echo $day; ?>" name="days[]" value="<?php echo $day; ?>" <?php echo $checked; ?> />
                  <label for="<?php echo $day; ?>"><?php echo ucfirst($day); ?></label>
                  <input type="time" name="open_<?php echo $day; ?>"  value="<?php echo $start; ?>" class="time-input" />
                  <span>to</span>
                  <input type="time" name="close_<?php echo $day; ?>" value="<?php echo $end; ?>"   class="time-input" />
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- ── Submit ── -->
            <div class="form-actions">
              <button type="submit" class="btn-submit-form"
                style="display:flex;align-items:center;justify-content:center;gap:8px;margin:0 auto">
                <img src="../assets/icons/save.svg" alt="Save" width="18" height="18" style="filter:brightness(0) invert(1)" />
                Save Shop Information
              </button>
              <p class="form-note">Your shop information will be visible to customers</p>
            </div>
          </form>
        </div>
      </div>

      <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
    </main>

    <script>
      const mobileMenuToggle = document.getElementById("mobileMenuToggle");
const sidebar = document.querySelector(".sidebar");
const backdrop = document.getElementById("sidebarBackdrop");

if (mobileMenuToggle) {
  mobileMenuToggle.addEventListener("click", function () {
    sidebar.classList.toggle("active");
    document.body.classList.toggle("sidebar-open");
  });

  // Close when backdrop is clicked
  if (backdrop) {
    backdrop.addEventListener("click", function () {
      sidebar.classList.remove("active");
      document.body.classList.remove("sidebar-open");
    });
  }
}

      // Live logo preview when a new file is selected
      const logoInput = document.getElementById("logoInput");
      if (logoInput) {
        logoInput.addEventListener("change", function (e) {
          const file = e.target.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
              document.getElementById("logoImage").src = e.target.result;
            };
            reader.readAsDataURL(file);
          }
        });
      }

      // Add service row
      function addServiceField() {
        const container = document.getElementById("servicesContainer");
        const item = document.createElement("div");
        item.className = "service-item";
        item.innerHTML = `
          <div class="form-grid">
            <div class="form-group">
              <label>Service Name</label>
              <input type="text" name="service_name[]" placeholder="e.g., Battery Replacement" class="service-name">
            </div>
            <div class="form-group">
              <label>Fee (₱)</label>
              <input type="number" name="service_fee[]" placeholder="800" class="service-fee">
            </div>
            <div class="form-group">
              <label>Duration</label>
              <input type="text" name="service_duration[]" placeholder="30 minutes" class="service-duration">
            </div>
          </div>
          <button type="button" class="btn-remove-service" style="display:flex;align-items:center;gap:6px;" onclick="this.parentElement.remove()"><img src="../assets/icons/remove.svg" alt="Remove" width="16" height="16" /> Remove</button>
        `;
        container.appendChild(item);
      }

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

    const AVATAR_BG = { pending: 'f59e0b', cancelled: 'ef4444', review: '8b5cf6' };

    list.innerHTML = data.notifications.map(n => {
      const time = n.time
        ? new Date(n.time).toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' })
        : '';
      const bg      = n.is_read ? '' : 'background:#fffbeb;';
      const dest    = n.type === 'review' ? 'shop-reviews.php' : 'shop-bookings.php';
      const avatarBg = AVATAR_BG[n.status] || '94a3b8';
      const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(n.customer_name || 'Customer')}&background=${avatarBg}&color=fff&size=80`;

      const msgText = n.type === 'reschedule'
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
              <span style="font-size:.82rem;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${n.customer_name || 'Customer'}</span>
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

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
// ── MAP PICKER ──────────────────────────────────────────
const savedLat = <?php echo $savedLat ? $savedLat : 'null'; ?>;
const savedLng = <?php echo $savedLng ? $savedLng : 'null'; ?>;

const defaultLat = savedLat || 7.0644;
const defaultLng = savedLng || 125.6078;
const defaultZoom = savedLat ? 16 : 13;

const pickerMap = L.map('shopMapPicker').setView([defaultLat, defaultLng], defaultZoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
}).addTo(pickerMap);

const shopIcon = L.divIcon({
  className: '',
  html: `<div style="background:#f59e0b;color:white;font-size:20px;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(0,0,0,.3);border:3px solid white;">🔧</div>`,
  iconSize: [36, 36], iconAnchor: [18, 18]
});

let marker = null;

// If naa saved coords, show marker dayon
<?php if ($savedLat && $savedLng): ?>
marker = L.marker([savedLat, savedLng], { icon: shopIcon })
  .addTo(pickerMap)
  .bindPopup('<strong><?php echo addslashes($savedName); ?></strong><br><?php echo addslashes($savedLocation); ?>')
  .openPopup();
<?php endif; ?>

// ── Address Search ──
async function searchMapAddress() {
  const query = document.getElementById('mapSearchInput').value.trim();
  if (!query) return;
  const btn = document.getElementById('mapSearchBtn');
  btn.textContent = '⏳ Searching...';
  btn.disabled = true;
  try {
    const res  = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=ph`);
    const data = await res.json();
    if (data && data.length > 0) {
      const lat = parseFloat(data[0].lat);
      const lng = parseFloat(data[0].lon);
      pickerMap.setView([lat, lng], 16);
      document.getElementById('latInput').value = lat.toFixed(7);
      document.getElementById('lngInput').value = lng.toFixed(7);
      if (marker) pickerMap.removeLayer(marker);
      marker = L.marker([lat, lng], { icon: shopIcon }).addTo(pickerMap)
        .bindPopup(data[0].display_name).openPopup();
    } else {
      alert('Address not found. Try a more specific search.');
    }
  } catch(e) {
    alert('Search failed. Check your connection.');
  }
  btn.textContent = '🔍 Search';
  btn.disabled = false;
}

document.getElementById('mapSearchBtn').addEventListener('click', searchMapAddress);
document.getElementById('mapSearchInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); searchMapAddress(); }
});

pickerMap.on('click', function(e) {
  const { lat, lng } = e.latlng;
  document.getElementById('latInput').value = lat.toFixed(7);
  document.getElementById('lngInput').value = lng.toFixed(7);
  if (marker) pickerMap.removeLayer(marker);
  marker = L.marker([lat, lng], { icon: shopIcon }).addTo(pickerMap)
    .bindPopup('Your shop location').openPopup();
});
    </script>
     <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
  </body>
</html>