<?php
session_start();
require_once __DIR__ . '/../config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) { header("../login.php"); exit(); }
if ($_SESSION['role'] !== 'admin') { header("../shop-owner/dashboard.php"); exit(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userName     = $_SESSION['name'];
$userInitials = strtoupper(substr($userName, 0, 2));

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

$msg = '';
$msgType = '';

// ── Handle admin actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $action = $_POST['action'] ?? '';
    $subId  = (int)($_POST['sub_id'] ?? 0);
    $note   = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve' && $subId) {
        // Get plan duration + payment proof fields for verification
        $stmt = $conn->prepare("
            SELECT ss.shop_id, ss.payment_ref, ss.gcash_screenshot, sp.duration_days
            FROM shop_subscriptions ss
            JOIN subscription_plans sp ON ss.plan_id = sp.id
            WHERE ss.id = ?
        ");
        $stmt->bind_param("i", $subId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // ── Verify proof of receipt before activating ──
        $verifyErr = '';
        if ($row) {
            if (empty($row['gcash_screenshot'])) {
                $verifyErr = 'Cannot approve: no payment proof/screenshot was uploaded.';
            } elseif (!empty($row['payment_ref'])) {
                // Reject reference numbers already used by an approved payment
                $dupStmt = $conn->prepare("
                    SELECT COUNT(*) AS c FROM shop_subscriptions
                    WHERE payment_ref = ? AND id <> ? AND status IN ('active','expired')
                ");
                $dupStmt->bind_param("si", $row['payment_ref'], $subId);
                $dupStmt->execute();
                $dupCount = (int) $dupStmt->get_result()->fetch_assoc()['c'];
                $dupStmt->close();
                if ($dupCount > 0) {
                    $verifyErr = 'Cannot approve: reference # ' . $row['payment_ref'] . ' was already used by an approved payment (possible duplicate/fake).';
                }
            }
        }

        if ($row && $verifyErr === '') {
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime("+{$row['duration_days']} days"));
            $stmt2 = $conn->prepare("
                UPDATE shop_subscriptions
                SET status='active', start_date=?, end_date=?, admin_note=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt2->bind_param("sssi", $start, $end, $note, $subId);
            $stmt2->execute();
            $stmt2->close();

            // ── Send approval email ───────────────────────
            require_once '../PHPMailer/src/Exception.php';
            require_once '../PHPMailer/src/PHPMailer.php';
            require_once '../PHPMailer/src/SMTP.php';

            $connMail = new mysqli("localhost", "root", "", "fixitdavao");
            $stmtMail = $connMail->prepare("SELECT email, shop_name, name FROM users WHERE id = ?");
            $stmtMail->bind_param("i", $row['shop_id']);
            $stmtMail->execute();
            $shopUser = $stmtMail->get_result()->fetch_assoc();
            $stmtMail->close();
            $connMail->close();

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USERNAME'];
                $mail->Password   = $_ENV['MAIL_PASSWORD'];
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
                $mail->addAddress($shopUser['email'], $shopUser['shop_name'] ?: $shopUser['name']);
                $mail->isHTML(true);
                $mail->Subject = '✅ Subscription Approved - Fix It Davao';
                $mail->Body    = "
                <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px;text-align:center;border-radius:12px 12px 0 0;'>
                        <h2 style='color:white;margin:0;'>🔧 Fix It Davao</h2>
                    </div>
                    <div style='padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:0 0 12px 12px;'>
                        <h3 style='color:#0f172a;'>🎉 Subscription Approved!</h3>
                        <p style='color:#64748b;'>Hi <strong>" . htmlspecialchars($shopUser['shop_name'] ?: $shopUser['name']) . "</strong>,</p>
                        <p style='color:#64748b;'>Your subscription has been approved! Your shop is now visible to customers on Fix It Davao.</p>
                        <div style='background:#d1fae5;border-radius:8px;padding:12px;margin:16px 0;'>
                            <p style='color:#065f46;margin:0;font-weight:700;'>✅ Status: ACTIVE</p>
                            <p style='color:#065f46;margin:4px 0 0;'>Valid until: <strong>{$end}</strong></p>
                        </div>
                        <p style='color:#94a3b8;font-size:12px;margin-top:24px;'>Fix It Davao — Repair Shop Booking Platform, Davao City</p>
                    </div>
                </div>";
                $mail->send();
            } catch (Exception $e) {
                // Silent fail
            }

            $msg     = 'Subscription approved and activated!';
            $msgType = 'success';
        } elseif ($verifyErr !== '') {
            $msg     = $verifyErr;
            $msgType = 'error';
        }
    } elseif ($action === 'reject' && $subId) {
        $stmt = $conn->prepare("UPDATE shop_subscriptions SET status='rejected', admin_note=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("si", $note, $subId);
        $stmt->execute();
        $stmt->close();
        $msg     = 'Subscription rejected.';
        $msgType = 'error';
    } elseif ($action === 'expire' && $subId) {
        $stmt = $conn->prepare("UPDATE shop_subscriptions SET status='expired', updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $subId);
        $stmt->execute();
        $stmt->close();
        $msg     = 'Subscription manually expired.';
        $msgType = 'warn';
    }
}

// ── Auto-expire subscriptions past end_date ───────────────────
$conn->query("UPDATE shop_subscriptions SET status='expired' WHERE status='active' AND end_date < CURDATE()");

// ── Load pending subscriptions ────────────────────────────────
$pendingResult = $conn->query("
    SELECT ss.*, sp.name AS plan_name, sp.price, sp.duration_days,
           u.name AS shop_owner, u.shop_name, u.email
    FROM shop_subscriptions ss
    JOIN subscription_plans sp ON ss.plan_id = sp.id
    JOIN users u ON ss.shop_id = u.id
    WHERE ss.status = 'pending'
    ORDER BY ss.created_at ASC
");
$pending = $pendingResult->fetch_all(MYSQLI_ASSOC);

// ── Flag pending items whose payment_ref was already used by an approved sub ──
$approvedRefs = [];
$refRes = $conn->query("SELECT DISTINCT payment_ref FROM shop_subscriptions WHERE status IN ('active','expired') AND payment_ref <> '' AND payment_ref IS NOT NULL");
while ($r = $refRes->fetch_assoc()) { $approvedRefs[$r['payment_ref']] = true; }
foreach ($pending as &$p) {
    $p['dup_ref'] = (!empty($p['payment_ref']) && isset($approvedRefs[$p['payment_ref']]));
}
unset($p);

// ── Build notification list from pending subscriptions ─────────
$notifItems = array_map(function($p) {
    return [
        'id'    => 'sub_' . $p['id'],
        'title' => 'New Subscription Payment',
        'name'  => ($p['shop_name'] ?: $p['shop_owner']) . ' — ₱' . number_format($p['price'], 2),
        'time'  => $p['created_at'],
    ];
}, $pending);

// ── Load all subscriptions ────────────────────────────────────
$allResult = $conn->query("
    SELECT ss.*, sp.name AS plan_name, sp.price,
           u.name AS shop_owner, u.shop_name, u.email
    FROM shop_subscriptions ss
    JOIN subscription_plans sp ON ss.plan_id = sp.id
    JOIN users u ON ss.shop_id = u.id
    ORDER BY ss.created_at DESC
    LIMIT 50
");
$allSubs = $allResult->fetch_all(MYSQLI_ASSOC);

// ── Summary stats ─────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(CASE WHEN status='active'  THEN 1 END) AS active_count,
        COUNT(CASE WHEN status='pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status='expired' THEN 1 END) AS expired_count,
        COALESCE(SUM(CASE WHEN status='active' THEN sp.price END), 0) AS active_revenue,
        COALESCE(SUM(CASE WHEN status IN ('active','expired') THEN sp.price END), 0) AS total_revenue
    FROM shop_subscriptions ss
    JOIN subscription_plans sp ON ss.plan_id = sp.id
")->fetch_assoc();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Subscriptions - Fix It Davao Admin</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
  <style>

    /* ── NOTIFICATION BELL (from admin-dashboard.php) ── */
    .notif-wrapper { position:relative; }
    .notification-btn { position:relative; }
    .notif-badge {
      position:absolute; top:-3px; right:-3px;
      min-width:17px; height:17px; padding:0 4px;
      background:#ef4444; color:white; border-radius:10px;
      font-size:0.65rem; font-weight:800; display:none;
      align-items:center; justify-content:center;
      font-family:'Outfit',sans-serif; border:2px solid white;
      line-height:1;
    }
    .notif-badge.show { display:flex; }
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
    .notif-header-title { font-size:0.88rem; font-weight:800; color:#0f172a; font-family:'Outfit',sans-serif; }
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
      transition:background 0.15s ease; cursor:default;
    }
    .notif-item:last-child { border-bottom:none; }
    .notif-item:hover { background:#fafafa; }
    .notif-item.unread { background:#fffbeb; }
    .notif-item.unread:hover { background:#fef9e7; }
    .notif-dot-icon {
      width:34px; height:34px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center; margin-top:1px;
    }
    .notif-content { flex:1; min-width:0; }
    .notif-title { font-size:0.8rem; font-weight:700; color:#0f172a; margin:0 0 2px; font-family:'Outfit',sans-serif; }
    .notif-name  { font-size:0.75rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .notif-time  { font-size:0.7rem; color:#94a3b8; margin-top:3px; }
    .notif-unread-dot {
      width:7px; height:7px; background:#f59e0b; border-radius:50%;
      flex-shrink:0; margin-top:6px;
    }
    .notif-footer { padding:10px 16px; border-top:1px solid #f1f5f9; text-align:center; }
    .notif-footer a { font-size:0.78rem; font-weight:700; color:#f59e0b; text-decoration:none; font-family:'Outfit',sans-serif; }
    .notif-footer a:hover { text-decoration:underline; }
    .notif-empty { text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.82rem; font-family:'Outfit',sans-serif; }
    .notif-loading { text-align:center; padding:24px 20px; }
    .notif-spinner { width:22px; height:22px; border:2.5px solid #e2e8f0; border-top-color:#f59e0b; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto; }
    @keyframes spin { to { transform:rotate(360deg); } }


       /* ── PAGE LOAD ANIMATIONS (matches my-bookings.php / admin-dashboard.php) ── */
    @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .top-bar            { animation: fadeInUp 0.4s ease both; }
    .sub-status-banner  { animation: fadeInUp 0.45s ease both; }
    .plans-grid         { animation: fadeInUp 0.5s ease both; }
    .payment-section.show { animation: fadeInUp 0.35s ease; }
    .pending-info-card  { animation: fadeInUp 0.5s ease both; }
    .dash-card          { animation: fadeInUp 0.55s ease both; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

    /* ── STAT CARDS ── */
    .sub-stats { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.1rem;margin-bottom:1.5rem;animation:fadeInUp .4s ease; }
    .sub-stat-card { background:white;border-radius:14px;padding:1.2rem 1.3rem;border:1px solid #f1f5f9;box-shadow:0 2px 8px rgba(0,0,0,.06);display:flex;align-items:center;gap:.9rem;position:relative;overflow:hidden;transition: transform 0.25s ease, box-shadow 0.25s}
    .sub-stat-card:hover { /* ADD THIS BLOCK */
  transform: translateY(-4px);
  box-shadow: 0 10px 28px rgba(0,0,0,0.11);
}
    .sub-stat-card::after { content:'';position:absolute;top:0;left:0;width:4px;height:100%;border-radius:4px 0 0 4px; }
    .sc-active::after  { background:#10b981; }
    .sc-pending::after { background:#f59e0b; }
    .sc-expired::after { background:#ef4444; }
    .sc-revenue::after { background:#8b5cf6; }
    .sc-total::after { background:#3b82f6; }
    .sc-total .sub-stat-icon { background:#dbeafe; }
    .sub-stat-icon { width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
    .sc-active  .sub-stat-icon { background:#d1fae5; }
    .sc-pending .sub-stat-icon { background:#fef3c7; }
    .sc-expired .sub-stat-icon { background:#fee2e2; }
    .sc-revenue .sub-stat-icon { background:#ede9fe; }
    .sub-stat-val { font-size:1.7rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif;line-height:1; }
    .sub-stat-lbl { font-size:.75rem;color:#64748b;font-weight:500;margin-top:2px; }

    /* ── PENDING CARDS ── */
    .pending-list { display:flex;flex-direction:column;gap:1rem;margin-bottom:1.75rem; }
    .pending-card { background:white;border-radius:16px;padding:1.4rem 1.5rem;border:1.5px solid #fde68a;box-shadow:0 2px 10px rgba(0,0,0,.07);animation:fadeInUp .35s ease; }
    .pending-card-header { display:flex;align-items:flex-start;gap:1rem;margin-bottom:1rem;flex-wrap:wrap; }
    .pc-avatar { width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;font-size:.9rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;flex-shrink:0; }
    .pc-info { flex:1;min-width:0; }
    .pc-shop  { font-size:.95rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif; }
    .pc-email { font-size:.78rem;color:#64748b;margin-top:1px; }
    .pc-plan-badge { padding:5px 12px;border-radius:20px;background:#fef3c7;color:#92400e;font-size:.75rem;font-weight:800;flex-shrink:0; }

    .pending-details { display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem; }
    .pd-item { background:#f8fafc;border-radius:8px;padding:6px 12px;font-size:.78rem; }
    .pd-item strong { color:#0f172a; }
    .pd-item span   { color:#64748b; }

    .screenshot-thumb {
      width:100%;max-height:220px;object-fit:contain;border-radius:10px;
      border:1px solid #e2e8f0;cursor:pointer;margin-bottom:.75rem;
      transition:transform .2s; display:block;
    }
    .screenshot-thumb:hover { transform:scale(1.01); }

    .action-row { display:flex;gap:.75rem;flex-wrap:wrap; }
    .note-input {
      flex:1;min-width:180px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:10px;
      font-size:.82rem;font-family:'Outfit',sans-serif;outline:none;
    }
    .note-input:focus { border-color:#f59e0b; }
    .btn-approve { padding:9px 18px;border:none;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);color:white;font-size:.82rem;font-weight:800;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .2s; }
    .btn-approve:hover { transform:translateY(-1px);box-shadow:0 6px 16px rgba(16,185,129,.3); }
    .btn-reject  { padding:9px 18px;border:none;border-radius:10px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;font-size:.82rem;font-weight:800;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .2s; }
    .btn-reject:hover { transform:translateY(-1px);box-shadow:0 6px 16px rgba(239,68,68,.3); }

    /* ── ALL SUBS TABLE ── */
    .dash-card { background:white;border-radius:16px;padding:1.4rem 1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #f1f5f9;margin-bottom:1.5rem; }
    .card-title { font-size:.95rem;font-weight:800;color:#0f172a;margin:0 0 1rem;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:8px; }
    table.sub-table { width:100%;border-collapse:collapse;font-size:.82rem; }
    .sub-table th { padding:8px 12px;text-align:left;font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;border-bottom:1.5px solid #f1f5f9; }
    .sub-table td { padding:10px 12px;border-bottom:1px solid #f8fafc;color:#374151;vertical-align:middle; }
    .sub-table tr:last-child td { border-bottom:none; }
    .status-pill { padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;display:inline-block; }
    .pill-active   { background:#d1fae5;color:#065f46; }
    .pill-pending  { background:#fef3c7;color:#92400e; }
    .pill-expired  { background:#fee2e2;color:#991b1b; }
    .pill-rejected { background:#f1f5f9;color:#64748b; }
    .btn-expire-sm { padding:4px 10px;border:1px solid #ef4444;border-radius:8px;background:white;color:#ef4444;font-size:.72rem;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .2s; }
    .btn-expire-sm:hover { background:#fee2e2; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align:center;padding:3rem 2rem;color:#94a3b8; }
    .empty-state p { font-size:.85rem;margin:8px 0 0; }

    /* ── ALERT ── */
    .alert { padding:12px 16px;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:1.2rem;display:flex;align-items:center;gap:8px;font-family:'Outfit',sans-serif;animation:fadeInUp .3s ease; }
    .alert-success { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7; }
    .alert-error   { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5; }
    .alert-warn    { background:#fef3c7;color:#92400e;border:1px solid #fde68a; }

    /* ── MODAL (screenshot full view) ── */
    .img-modal { position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px; }
    .img-modal.open { display:flex; }
    .img-modal img { max-width:90vw;max-height:85vh;border-radius:12px;object-fit:contain; }
    .img-modal-close { position:absolute;top:16px;right:20px;color:white;font-size:2rem;cursor:pointer;font-weight:300;line-height:1; }

    /* ── LOGOUT MODAL ── */
    .modal-overlay { position:fixed;inset:0;background:rgba(10,15,30,.72);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity .3s ease;padding:20px; }
    .modal-overlay.visible { opacity:1;pointer-events:all; }
    .modal-box { background:white;border-radius:20px;padding:32px 28px;max-width:420px;width:100%;box-shadow:0 40px 100px rgba(0,0,0,.25);transform:scale(.9) translateY(20px);opacity:0;transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s ease; }
    .modal-overlay.visible .modal-box { transform:scale(1) translateY(0);opacity:1; }
    .modal-btn-cancel  { flex:1;padding:11px;border:2px solid #e2e8f0;border-radius:10px;background:white;font-size:13px;font-weight:700;font-family:var(--font-primary);cursor:pointer;color:#64748b;transition:all .2s; }
    .modal-btn-confirm { flex:1;padding:11px;border:none;border-radius:10px;color:white;font-size:13px;font-weight:700;font-family:var(--font-primary);cursor:pointer;transition:all .2s; }

    @media(max-width:640px) {
      .action-row { flex-direction:column; }
      .note-input { min-width:unset; }
      .pending-details { gap:.5rem; }
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
<body class="role-admin">
  <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

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
        <a href="admin-subscriptions.php" class="nav-item active">
          <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscriptions" /></span>
          <span class="nav-text">Subscriptions</span>
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

  <!-- Screenshot fullscreen modal -->
  <div class="img-modal" id="imgModal" onclick="closeImgModal()">
    <span class="img-modal-close">×</span>
    <img id="imgModalSrc" src="" alt="GCash Screenshot" onclick="event.stopPropagation()" />
  </div>

  <main class="main-content">
    <header class="top-bar">
  <div class="page-header">
    <h1 class="current-page-title">Subscriptions</h1>
  </div>
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
        <div class="notif-footer"><a href="admin-subscriptions.php">View all →</a></div>
      </div>
    </div>

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

      <!-- ── STAT CARDS ── -->
      <div class="sub-stats">
        <div class="sub-stat-card sc-active">
          <div class="sub-stat-icon"><img src="../assets/icons/approve.svg" alt="" width="26" height="26"></div>
          <div>
            <div class="sub-stat-val" data-target="<?php echo $stats['active_count']; ?>">0</div>
            <div class="sub-stat-lbl">Active Subscriptions</div>
          </div>
        </div>
        <div class="sub-stat-card sc-pending">
          <div class="sub-stat-icon"><img src="../assets/icons/glass.svg" alt="" width="26" height="26"></div>
          <div>
            <div class="sub-stat-val"><?php echo $stats['pending_count']; ?></div>
            <div class="sub-stat-lbl">Pending Verification</div>
          </div>
        </div>
        <div class="sub-stat-card sc-expired">
        <div class="sub-stat-icon"><img src="../assets/icons/danger.svg" alt="" width="26" height="26" /></div>
          <div>
            <div class="sub-stat-val"><?php echo $stats['expired_count']; ?></div>
            <div class="sub-stat-lbl">Expired</div>
          </div>
        </div>
        <div class="sub-stat-card sc-revenue">
          <div class="sub-stat-icon"><img src="../assets/icons/bag.svg" alt="" width="26" height="26" /></div>
          <div>
            <div class="sub-stat-val">₱<?php echo number_format($stats['active_revenue'], 0); ?></div>
            <div class="sub-stat-lbl">Active Revenue</div>
          </div>
        </div>
        <div class="sub-stat-card sc-total">
  <div class="sub-stat-icon"><img src="../assets/icons/bag.svg" alt="" width="26" height="26" /></div>
  <div>
    <div class="sub-stat-val">₱<?php echo number_format($stats['total_revenue'], 0); ?></div>
    <div class="sub-stat-lbl">Total Revenue (All Time)</div>
  </div>
</div>
      </div>

      <!-- ── PENDING APPROVALS ── -->
      <h2 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0 0 .85rem;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:6px;">
        <img src="../assets/icons/glass.svg" alt="" width="16" height="16" />
        Pending Payment Verification
        <?php if (count($pending) > 0): ?>
        <span style="background:#ef4444;color:white;font-size:.7rem;padding:2px 8px;border-radius:12px;margin-left:6px;"><?php echo count($pending); ?></span>
        <?php endif; ?>
      </h2>

      <?php if (empty($pending)): ?>
      <div class="dash-card" style="margin-bottom:1.75rem;">
        <div class="empty-state">
          <div style="font-size:2.5rem;">🎉</div>
          <p>No pending subscription payments. All verified!</p>
        </div>
      </div>
      <?php else: ?>
      <div class="pending-list">
        <?php foreach ($pending as $sub): ?>
        <div class="pending-card">
          <div class="pending-card-header">
            <div class="pc-avatar"><?php echo strtoupper(substr($sub['shop_name'] ?: $sub['shop_owner'], 0, 2)); ?></div>
            <div class="pc-info">
              <div class="pc-shop"><?php echo htmlspecialchars($sub['shop_name'] ?: $sub['shop_owner']); ?></div>
              <div class="pc-email"><?php echo htmlspecialchars($sub['email']); ?></div>
            </div>
            <div class="pc-plan-badge"><?php echo htmlspecialchars($sub['plan_name']); ?> — ₱<?php echo number_format($sub['price'], 2); ?></div>
          </div>

          <div class="pending-details">
            <div class="pd-item"><span>Submitted: </span><strong><?php echo date('M j, Y g:i A', strtotime($sub['created_at'])); ?></strong></div>
            <div class="pd-item"><span>GCash #: </span><strong style="font-family:'Space Mono',monospace;"><?php echo htmlspecialchars($sub['gcash_number'] ?: '—'); ?></strong></div>
            <div class="pd-item"><span>Ref #: </span><strong style="font-family:'Space Mono',monospace;"><?php echo htmlspecialchars($sub['payment_ref'] ?: '—'); ?></strong></div>
            <div class="pd-item"><span>Duration: </span><strong><?php echo $sub['duration_days']; ?> days</strong></div>
          </div>

          <?php if ($sub['gcash_screenshot']): ?>
          <img
            src="<?php echo htmlspecialchars($sub['gcash_screenshot']); ?>"
            alt="GCash Screenshot"
            class="screenshot-thumb"
            onclick="openImgModal('<?php echo htmlspecialchars($sub['gcash_screenshot']); ?>')"
            title="Click to view full size"
          />
          <?php else: ?>
          <div style="background:#f8fafc;border-radius:10px;padding:1rem;text-align:center;color:#94a3b8;font-size:.82rem;margin-bottom:.75rem;">
            📎 No screenshot uploaded
          </div>
          <?php endif; ?>

          <?php if (!empty($sub['dup_ref'])): ?>
          <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:8px 12px;font-size:.78rem;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:6px;">
            ⚠️ This reference # was already used by an approved payment — verify carefully, likely duplicate/fake.
          </div>
          <?php endif; ?>

          <form method="POST" style="margin:0;">
            <input type="hidden" name="sub_id" value="<?php echo $sub['id']; ?>" />
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <div class="action-row">
              <input type="text" name="admin_note" class="note-input" placeholder="Optional note to shop (e.g., reason for rejection)..." />
              <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this subscription?')">✅ Approve</button>
              <button type="submit" name="action" value="reject"  class="btn-reject"  onclick="return confirm('Reject this payment?')">❌ Reject</button>
            </div>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ── ALL SUBSCRIPTIONS TABLE ── -->
      <div class="dash-card">
        <div class="card-title"><img src="../assets/icons/clip.svg" alt="" width="26" height="26" style="vertical-align:middle;margin-right:4px;" /> All Subscriptions</div>
        <?php if (empty($allSubs)): ?>
        <div class="empty-state">
          <div style="font-size:2rem;">📭</div>
          <p>No subscriptions yet.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="sub-table">
            <thead>
              <tr>
                <th>Shop</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
                <th>Ref #</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allSubs as $sub): ?>
              <tr>
                <td>
                  <div style="font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($sub['shop_name'] ?: $sub['shop_owner']); ?></div>
                  <div style="font-size:.72rem;color:#94a3b8;"><?php echo htmlspecialchars($sub['email']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($sub['plan_name']); ?></td>
                <td>₱<?php echo number_format($sub['price'], 2); ?></td>
                <td><?php echo $sub['start_date'] ? date('M j, Y', strtotime($sub['start_date'])) : '—'; ?></td>
                <td><?php echo $sub['end_date']   ? date('M j, Y', strtotime($sub['end_date']))   : '—'; ?></td>
                <td><span class="status-pill pill-<?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                <td style="font-family:'Space Mono',monospace;font-size:.72rem;"><?php echo htmlspecialchars($sub['payment_ref'] ?: '—'); ?></td>
                <td>
                  <?php if ($sub['status'] === 'active'): ?>
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Manually expire this subscription?')">
                    <input type="hidden" name="sub_id" value="<?php echo $sub['id']; ?>" />
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                    <button type="submit" name="action" value="expire" class="btn-expire-sm">Expire</button>
                  </form>
                  <?php else: ?>
                  <span style="color:#cbd5e1;font-size:.72rem;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div>
    <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
  </main>

  <script>
    const sidebar = document.querySelector('.sidebar');
    document.getElementById('mobileMenuToggle').addEventListener('click', () => {
      sidebar.classList.toggle('active');
      document.body.classList.toggle('sidebar-open');
    });
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && !document.getElementById('mobileMenuToggle').contains(e.target)) {
        sidebar.classList.remove('active');
        document.body.classList.remove('sidebar-open');
      }
    });

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

    function openImgModal(src) {
      document.getElementById('imgModalSrc').src = src;
      document.getElementById('imgModal').classList.add('open');
    }
    function closeImgModal() {
      document.getElementById('imgModal').classList.remove('open');
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeImgModal();
    });

    // ── NOTIFICATION BELL ─────────────────────────────────────
const notifItems     = <?php echo json_encode($notifItems); ?>;
const notifBtn        = document.getElementById("notifBtn");
const notifDropdown   = document.getElementById("notifDropdown");
const notifBadge      = document.getElementById("notifBadge");
const notifList       = document.getElementById("notifList");
let notifOpen         = false;
let seenIds            = JSON.parse(localStorage.getItem("notifSeenIds_sub") || "[]");

function timeAgoShort(dateStr) {
  if (!dateStr) return "";
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)    return "just now";
  if (diff < 3600)  return Math.floor(diff / 60) + "m ago";
  if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
  return Math.floor(diff / 86400) + "d ago";
}

function renderNotifications() {
  const unread = notifItems.filter(n => !seenIds.includes(n.id)).length;
  if (unread > 0) {
    notifBadge.textContent = unread > 9 ? "9+" : unread;
    notifBadge.classList.add("show");
  } else {
    notifBadge.classList.remove("show");
  }

  if (!notifItems.length) {
    notifList.innerHTML = `<div class="notif-empty">🎉 No pending payments.</div>`;
    return;
  }

  notifList.innerHTML = notifItems.map(n => {
    const isUnread = !seenIds.includes(n.id);
    return `
      <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${n.id}">
        <div class="notif-dot-icon">
          <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>
        </div>
        <div class="notif-content">
          <div class="notif-title">${n.title}</div>
          <div class="notif-name">${n.name}</div>
          <div class="notif-time">${timeAgoShort(n.time)}</div>
        </div>
        ${isUnread ? '<div class="notif-unread-dot"></div>' : ''}
      </div>`;
  }).join("");
}

notifBtn.addEventListener("click", (e) => {
  e.stopPropagation();
  notifOpen = !notifOpen;
  notifDropdown.classList.toggle("open", notifOpen);
  if (notifOpen) renderNotifications();
});

document.addEventListener("click", (e) => {
  if (!notifBtn.closest(".notif-wrapper").contains(e.target)) {
    notifOpen = false;
    notifDropdown.classList.remove("open");
  }
});

document.getElementById("markAllRead").addEventListener("click", () => {
  notifItems.forEach(n => { if (!seenIds.includes(n.id)) seenIds.push(n.id); });
  localStorage.setItem("notifSeenIds_sub", JSON.stringify(seenIds));
  renderNotifications();
});

renderNotifications();
  </script>
</body>
</html>