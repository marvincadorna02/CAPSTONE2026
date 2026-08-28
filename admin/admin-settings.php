<?php
session_start();
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] !== 'admin') { header("Location: ../shop-owner/dashboard.php"); exit(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userName     = $_SESSION['name'];
$userInitials = strtoupper(substr($userName, 0, 2));

$conn = fixit_db();
if (!$conn) die("DB error: unable to connect.");

$msg = '';
$msgType = '';

// ── Handle actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $action = $_POST['action'] ?? '';

    if ($action === 'save_maintenance') {
        $mode    = isset($_POST['maintenance_mode']) ? '1' : '0';
        $message = trim($_POST['maintenance_message'] ?? '');
        $until   = trim($_POST['maintenance_until'] ?? '');

        if ($message === '') {
            $message = 'We are performing scheduled maintenance. Please check back shortly.';
        }
        // datetime-local gives "2026-08-30T14:00" — normalise for MySQL/strtotime.
        if ($until !== '') $until = str_replace('T', ' ', $until) . ':00';

        $was = fixit_setting($conn, 'maintenance_mode', '0');

        fixit_set_setting($conn, 'maintenance_mode',    $mode);
        fixit_set_setting($conn, 'maintenance_message', $message);
        fixit_set_setting($conn, 'maintenance_until',   $until);

        logAdminAction($conn, $mode === '1' ? 'maintenance_on' : 'maintenance_off', 'system', 0,
            $mode === '1' ? "Message: {$message}" : ($until ? "Scheduled: {$until}" : ''));

        $msg = $mode === '1'
            ? 'Maintenance mode is ON. Customers and shop owners now see the maintenance page.'
            : ($was === '1' ? 'Maintenance mode is OFF. The site is live again.' : 'Settings saved.');
        $msgType = $mode === '1' ? 'warn' : 'success';
    }

    if ($action === 'send_blast') {
        $subject = trim($_POST['blast_subject'] ?? '');
        $body    = trim($_POST['blast_body']    ?? '');
        $target  = $_POST['blast_target'] ?? 'all';
        $confirm = trim($_POST['blast_confirm'] ?? '');

        if ($confirm !== 'SEND') {
            $msg = 'Type SEND in the confirmation box to send the announcement.';
            $msgType = 'error';
        } elseif ($subject === '' || $body === '') {
            $msg = 'Subject and message are both required.';
            $msgType = 'error';
        } else {
            $roleWhere = '';
            if ($target === 'customers') $roleWhere = " AND role = 'customer'";
            if ($target === 'shops')     $roleWhere = " AND role = 'repairshop'";

            $rs = $conn->query("SELECT id, name, email, role FROM users
                                WHERE role IN ('customer','repairshop')
                                  AND email <> '' {$roleWhere}");

            // SMTP is slow — a blast to many accounts can outrun the default
            // execution limit, so lift it for the duration of this request only.
            @set_time_limit(0);

            $sent = 0; $failed = 0;
            while ($u = $rs->fetch_assoc()) {
                // In-app notification always; email is best-effort.
                pushNotification($conn, (int)$u['id'], $u['role'], 'maintenance',
                    $subject, $body, null, false);

                $html = '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';
                if (sendSystemEmail($u['email'], $u['name'], $subject, $html)) $sent++;
                else $failed++;
            }

            logAdminAction($conn, 'announcement_sent', 'system', 0,
                "Target: {$target} · Subject: {$subject} · Sent: {$sent} · Failed: {$failed}");

            $msg = "Announcement sent to {$sent} recipient(s)." . ($failed ? " {$failed} email(s) failed — check the error log." : '');
            $msgType = $failed ? 'warn' : 'success';
        }
    }
}

// ── Load current settings ─────────────────────────────────────
$maintMode    = fixit_setting($conn, 'maintenance_mode', '0') === '1';
$maintMessage = (string)fixit_setting($conn, 'maintenance_message', '');
$maintUntil   = (string)fixit_setting($conn, 'maintenance_until', '');
$maintUntilInput = $maintUntil !== '' ? date('Y-m-d\TH:i', strtotime($maintUntil)) : '';

// ── Audit log ─────────────────────────────────────────────────
$auditFilter = $_GET['audit'] ?? 'all';
$auditWhere  = $auditFilter === 'all' ? '' : " WHERE target_type = '" . $conn->real_escape_string($auditFilter) . "'";
$auditRows   = [];
$ares = $conn->query("SELECT * FROM admin_audit_log{$auditWhere} ORDER BY created_at DESC LIMIT 100");
if ($ares) while ($r = $ares->fetch_assoc()) $auditRows[] = $r;

$counts = $conn->query("SELECT
    (SELECT COUNT(*) FROM users WHERE role='customer')    AS customers,
    (SELECT COUNT(*) FROM users WHERE role='repairshop')  AS shops,
    (SELECT COUNT(*) FROM admin_audit_log)                AS audits,
    (SELECT COUNT(*) FROM notifications WHERE is_read=0)  AS unread
")->fetch_assoc();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>System Settings - Fix It Davao Admin</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
  <style>
    .set-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    @media (max-width: 1000px) { .set-grid { grid-template-columns:1fr; } }

    .set-card {
      background:#fff; border:1px solid #e2e8f0; border-radius:18px;
      padding:22px; box-shadow:0 2px 12px rgba(15,23,42,.04);
    }
    .set-card-title {
      font-size:.95rem; font-weight:800; color:#0f172a; margin-bottom:6px;
      display:flex; align-items:center; gap:8px;
    }
    .set-card-sub { font-size:.78rem; color:#94a3b8; margin-bottom:18px; line-height:1.55; }

    .set-field { margin-bottom:14px; }
    .set-field label {
      display:block; font-size:.75rem; font-weight:700; color:#475569;
      text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px;
    }
    .set-field input[type=text], .set-field input[type=datetime-local],
    .set-field textarea, .set-field select {
      width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px;
      font-family:'Outfit',sans-serif; font-size:.86rem; color:#0f172a; background:#fff;
      transition:border-color .18s ease;
    }
    .set-field input:focus, .set-field textarea:focus, .set-field select:focus {
      outline:none; border-color:#f59e0b;
    }
    .set-field textarea { min-height:90px; resize:vertical; line-height:1.55; }
    .set-hint { font-size:.72rem; color:#94a3b8; margin-top:5px; }

    /* Maintenance toggle */
    .maint-toggle {
      display:flex; align-items:center; gap:14px; padding:14px 16px;
      border-radius:12px; border:1.5px solid #e2e8f0; background:#f8fafc; margin-bottom:16px;
    }
    .maint-toggle.on { border-color:#fbbf24; background:#fffbeb; }
    .switch { position:relative; width:50px; height:27px; flex-shrink:0; }
    .switch input { opacity:0; width:0; height:0; }
    .switch-slider {
      position:absolute; inset:0; cursor:pointer; background:#cbd5e1;
      border-radius:27px; transition:background .22s ease;
    }
    .switch-slider:before {
      content:""; position:absolute; height:21px; width:21px; left:3px; bottom:3px;
      background:#fff; border-radius:50%; transition:transform .22s ease;
      box-shadow:0 1px 4px rgba(0,0,0,.25);
    }
    .switch input:checked + .switch-slider { background:#ef4444; }
    .switch input:checked + .switch-slider:before { transform:translateX(23px); }
    .maint-label { font-size:.86rem; font-weight:700; color:#0f172a; }
    .maint-state { font-size:.74rem; color:#64748b; margin-top:2px; }
    .maint-state.live  { color:#16a34a; font-weight:700; }
    .maint-state.downo { color:#dc2626; font-weight:700; }

    .set-btn {
      background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none;
      padding:11px 22px; border-radius:10px; font-family:'Outfit',sans-serif;
      font-size:.85rem; font-weight:700; cursor:pointer; transition:transform .15s ease, box-shadow .15s ease;
    }
    .set-btn:hover { transform:translateY(-1px); box-shadow:0 8px 18px rgba(245,158,11,.35); }
    .set-btn.danger { background:linear-gradient(135deg,#ef4444,#dc2626); }
    .set-btn.danger:hover { box-shadow:0 8px 18px rgba(239,68,68,.35); }
    .set-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }

    .blast-warn {
      display:flex; gap:10px; padding:12px 14px; border-radius:10px;
      background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
      font-size:.78rem; line-height:1.55; margin-bottom:14px;
    }

    .mini-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
    @media (max-width:760px) { .mini-stats { grid-template-columns:repeat(2,1fr); } }
    .mini-stat {
      background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px;
    }
    .mini-stat-val { font-size:1.4rem; font-weight:800; color:#0f172a; font-family:'Outfit',sans-serif; }
    .mini-stat-lbl { font-size:.7rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; margin-top:2px; }

    /* Audit log */
    .audit-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .audit-table th {
      text-align:left; padding:10px 12px; font-size:.7rem; font-weight:800; color:#94a3b8;
      text-transform:uppercase; letter-spacing:.6px; border-bottom:1px solid #e2e8f0; white-space:nowrap;
    }
    .audit-table td { padding:11px 12px; border-bottom:1px solid #f1f5f9; color:#475569; vertical-align:top; }
    .audit-table tr:last-child td { border-bottom:none; }
    .audit-action {
      display:inline-block; padding:3px 9px; border-radius:20px; font-size:.7rem; font-weight:700;
      background:#f1f5f9; color:#475569; white-space:nowrap;
    }
    .audit-action.warnish { background:#fef3c7; color:#92400e; }
    .audit-action.badish  { background:#fee2e2; color:#991b1b; }
    .audit-action.goodish { background:#dcfce7; color:#166534; }
    .audit-details { color:#64748b; font-size:.78rem; max-width:420px; word-break:break-word; }
    .audit-empty { text-align:center; padding:40px 20px; color:#94a3b8; font-size:.85rem; }
    .audit-scroll { overflow-x:auto; }
    .audit-filter {
      display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap;
    }
    .audit-chip {
      padding:6px 14px; border-radius:20px; border:1.5px solid #e2e8f0; background:#fff;
      font-size:.76rem; font-weight:700; color:#64748b; text-decoration:none; transition:all .16s ease;
    }
    .audit-chip:hover { border-color:#f59e0b; color:#d97706; }
    .audit-chip.active { background:#f59e0b; border-color:#f59e0b; color:#fff; }

    .alert { padding:13px 16px; border-radius:12px; font-size:.85rem; font-weight:600; margin-bottom:18px; }
    .alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .alert-warn    { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

    .sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:900; }
    body.sidebar-open .sidebar-backdrop { display:block; }
    .sidebar { z-index:950; }
  </style>
</head>
<body class="role-admin">
  <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
  <div class="sidebar-backdrop"></div>

  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
      <h2 class="brand-name">FIX IT DAVAO</h2>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section" data-role="admin">
        <a href="admin-dashboard.php" class="nav-item">
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
        <a href="admin-settings.php" class="nav-item active">
          <span class="nav-icon"><img src="../assets/icons/tools.svg" alt="System Settings" /></span>
          <span class="nav-text">System Settings</span>
        </a>
        <a href="../developers.php" class="nav-item">
          <span class="nav-icon"><img src="../assets/icons/developers.svg" alt="Developers" /></span>
          <span class="nav-text">Developers</span>
        </a>
      </div>
    </nav>
    <div class="sidebar-footer">
      <a href="../logout.php" class="nav-item" onclick="return confirmLogout(event)">
        <span class="nav-icon"><img src="../assets/icons/logout.svg" alt="Logout" /></span>
        <span class="nav-text">Logout</span>
      </a>
    </div>
  </aside>

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
        <h1 class="current-page-title">System Settings</h1>
      </div>
      <div class="top-bar-actions">
        <div class="user-profile">
          <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;font-size:.85rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;">
            <?php echo $userInitials; ?>
          </div>
          <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            <span class="user-role">Administrator</span>
          </div>
        </div>
      </div>
    </header>

    <div class="dashboard-content">

      <?php if ($msg): ?>
      <div class="alert alert-<?php echo $msgType; ?>">
        <?php echo $msgType === 'success' ? '✅' : ($msgType === 'warn' ? '⚠️' : '❌'); ?>
        <?php echo htmlspecialchars($msg); ?>
      </div>
      <?php endif; ?>

      <div class="mini-stats">
        <div class="mini-stat">
          <div class="mini-stat-val"><?php echo (int)$counts['customers']; ?></div>
          <div class="mini-stat-lbl">Customers</div>
        </div>
        <div class="mini-stat">
          <div class="mini-stat-val"><?php echo (int)$counts['shops']; ?></div>
          <div class="mini-stat-lbl">Repair Shops</div>
        </div>
        <div class="mini-stat">
          <div class="mini-stat-val"><?php echo (int)$counts['unread']; ?></div>
          <div class="mini-stat-lbl">Unread Notifs</div>
        </div>
        <div class="mini-stat">
          <div class="mini-stat-val"><?php echo (int)$counts['audits']; ?></div>
          <div class="mini-stat-lbl">Logged Actions</div>
        </div>
      </div>

      <div class="set-grid">

        <!-- ── Maintenance mode ── -->
        <div class="set-card">
          <div class="set-card-title">
            <img src="../assets/icons/tools.svg" width="16" height="16" alt="" />
            Maintenance Mode
          </div>
          <div class="set-card-sub">
            While ON, customers and shop owners see a maintenance page instead of the app.
            Admins are never blocked, so you can always switch it back off.
          </div>

          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <input type="hidden" name="action" value="save_maintenance" />

            <div class="maint-toggle <?php echo $maintMode ? 'on' : ''; ?>" id="maintToggleRow">
              <label class="switch">
                <input type="checkbox" name="maintenance_mode" id="maintCheck" <?php echo $maintMode ? 'checked' : ''; ?> />
                <span class="switch-slider"></span>
              </label>
              <div>
                <div class="maint-label">Take the site offline</div>
                <div class="maint-state <?php echo $maintMode ? 'downo' : 'live'; ?>" id="maintState">
                  <?php echo $maintMode ? '● Currently OFFLINE for users' : '● Site is live'; ?>
                </div>
              </div>
            </div>

            <div class="set-field">
              <label for="maintenance_message">Message shown to users</label>
              <textarea id="maintenance_message" name="maintenance_message" placeholder="We are performing scheduled maintenance..."><?php echo htmlspecialchars($maintMessage); ?></textarea>
            </div>

            <div class="set-field">
              <label for="maintenance_until">Scheduled maintenance (optional)</label>
              <input type="datetime-local" id="maintenance_until" name="maintenance_until" value="<?php echo htmlspecialchars($maintUntilInput); ?>" />
              <div class="set-hint">
                Set a future date to show a warning banner site-wide without taking anything
                offline. Leave blank to remove the banner.
              </div>
            </div>

            <button type="submit" class="set-btn">Save Settings</button>
          </form>
        </div>

        <!-- ── Announcement blast ── -->
        <div class="set-card">
          <div class="set-card-title">
            <img src="../assets/icons/email.svg" width="16" height="16" alt="" />
            Announcement
          </div>
          <div class="set-card-sub">
            Sends an in-app notification and an email to every selected account.
            Use this for downtime notices — not for marketing.
          </div>

          <div class="blast-warn">
            <span>⚠️</span>
            <span>This cannot be undone. Emails go out immediately and may take a while for large lists.</span>
          </div>

          <form method="POST" id="blastForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <input type="hidden" name="action" value="send_blast" />

            <div class="set-field">
              <label for="blast_target">Send to</label>
              <select id="blast_target" name="blast_target">
                <option value="all">Everyone (<?php echo (int)$counts['customers'] + (int)$counts['shops']; ?>)</option>
                <option value="customers">Customers only (<?php echo (int)$counts['customers']; ?>)</option>
                <option value="shops">Repair shops only (<?php echo (int)$counts['shops']; ?>)</option>
              </select>
            </div>

            <div class="set-field">
              <label for="blast_subject">Subject</label>
              <input type="text" id="blast_subject" name="blast_subject" maxlength="150" placeholder="Scheduled maintenance on Aug 30" />
            </div>

            <div class="set-field">
              <label for="blast_body">Message</label>
              <textarea id="blast_body" name="blast_body" placeholder="Fix It Davao will be unavailable from 10:00 PM to 12:00 AM..."></textarea>
            </div>

            <div class="set-field">
              <label for="blast_confirm">Type SEND to confirm</label>
              <input type="text" id="blast_confirm" name="blast_confirm" autocomplete="off" placeholder="SEND" />
            </div>

            <button type="submit" class="set-btn danger" id="blastBtn" disabled>Send Announcement</button>
          </form>
        </div>
      </div>

      <!-- ── Audit log ── -->
      <div class="set-card">
        <div class="set-card-title">
          <img src="../assets/icons/list.svg" width="16" height="16" alt="" />
          Admin Activity Log
        </div>
        <div class="set-card-sub">
          Every admin write is recorded here. Useful when two admins disagree about
          who changed what. Showing the 100 most recent.
        </div>

        <div class="audit-filter">
          <?php
          $chips = ['all' => 'All', 'user' => 'Users', 'shop' => 'Shops', 'subscription' => 'Subscriptions', 'report' => 'Reports', 'system' => 'System'];
          foreach ($chips as $key => $label):
          ?>
          <a class="audit-chip <?php echo $auditFilter === $key ? 'active' : ''; ?>"
             href="admin-settings.php?audit=<?php echo $key; ?>"><?php echo $label; ?></a>
          <?php endforeach; ?>
        </div>

        <div class="audit-scroll">
          <table class="audit-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Target</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($auditRows)): ?>
              <tr><td colspan="5" class="audit-empty">No admin actions logged yet.</td></tr>
              <?php else: foreach ($auditRows as $a):
                $act = $a['action'];
                $cls = 'audit-action';
                if (strpos($act, 'suspend') !== false || strpos($act, 'ban') !== false ||
                    strpos($act, 'reject') !== false || strpos($act, '_on') !== false) $cls .= ' badish';
                elseif (strpos($act, 'approve') !== false || strpos($act, 'reactivate') !== false ||
                        strpos($act, '_off') !== false) $cls .= ' goodish';
                elseif (strpos($act, 'warn') !== false) $cls .= ' warnish';
              ?>
              <tr>
                <td style="white-space:nowrap;"><?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($a['admin_name'] ?: ('#' . $a['admin_id'])); ?></td>
                <td><span class="<?php echo $cls; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $act)); ?></span></td>
                <td style="white-space:nowrap;">
                  <?php echo htmlspecialchars($a['target_type']); ?>
                  <?php echo $a['target_id'] ? ' #' . (int)$a['target_id'] : ''; ?>
                </td>
                <td class="audit-details"><?php echo htmlspecialchars($a['details'] ?? '—'); ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
  </main>

  <script>
    const mobileMenuToggle = document.getElementById("mobileMenuToggle");
    const sidebar = document.querySelector(".sidebar");
    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("active");
        document.body.classList.toggle("sidebar-open");
      });
      document.querySelector(".sidebar-backdrop").addEventListener("click", () => {
        sidebar.classList.remove("active");
        document.body.classList.remove("sidebar-open");
      });
    }
    function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').classList.add('visible'); return false; }
    function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('visible'); }

    // Live feedback on the maintenance switch before saving.
    const maintCheck = document.getElementById('maintCheck');
    maintCheck.addEventListener('change', function () {
      const row = document.getElementById('maintToggleRow');
      const st  = document.getElementById('maintState');
      row.classList.toggle('on', this.checked);
      st.textContent = this.checked ? '● Will go OFFLINE when saved' : '● Will go LIVE when saved';
      st.className   = 'maint-state ' + (this.checked ? 'downo' : 'live');
    });

    // The blast button unlocks only once SEND is typed exactly.
    const confirmInput = document.getElementById('blast_confirm');
    const blastBtn     = document.getElementById('blastBtn');
    confirmInput.addEventListener('input', function () {
      blastBtn.disabled = this.value.trim() !== 'SEND';
    });
    document.getElementById('blastForm').addEventListener('submit', function (e) {
      const target = document.getElementById('blast_target');
      const label  = target.options[target.selectedIndex].text;
      if (!confirm('Send this announcement to ' + label + '?\n\nThis cannot be undone.')) {
        e.preventDefault();
        return;
      }
      blastBtn.disabled = true;
      blastBtn.textContent = 'Sending…';
    });
  </script>
</body>
</html>
