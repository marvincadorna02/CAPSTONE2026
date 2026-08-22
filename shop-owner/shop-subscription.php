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

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $userId   = (int) $_SESSION['user_id'];
    $userName = $_SESSION['name'];

    $conn = new mysqli("localhost", "root", "", "fixitdavao");
    if ($conn->connect_error) die("DB error: " . $conn->connect_error);

    // ── Auto-expire subscriptions past end_date (runs on every load) ──
    $conn->query("UPDATE shop_subscriptions SET status='expired' WHERE status='active' AND end_date < CURDATE()");

    // ── Load shop name + logo ─────────────────────────────────────
    $stmt = $conn->prepare("SELECT shop_name, logo_url FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($row['shop_name'])) $userName = $row['shop_name'];

    // Build absolute logo URL (same pattern as shop-information.php)
    $savedLogoUrl = $row['logo_url'] ?? '';
    if ($savedLogoUrl) {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $baseUrl = ($isHttps ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
        $savedLogoUrl = $baseUrl . $savedLogoUrl;
    }
    $avatarUrl = $savedLogoUrl ?: null; // null = use initials fallback

    // ── Load active/pending subscription ─────────────────────────
    $stmt = $conn->prepare("
        SELECT ss.*, sp.name AS plan_name, sp.price, sp.duration_days
        FROM shop_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.shop_id = ?
        ORDER BY ss.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $currentSub = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ── Load all plans ────────────────────────────────────────────
    $plansResult = $conn->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");
    $plans = [];
    while ($p = $plansResult->fetch_assoc()) $plans[] = $p;

    // ── Handle subscription form submit ──────────────────────────
    $msg = '';
    $msgType = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
        if (!isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("Invalid request.");
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $planId    = (int) $_POST['plan_id'];
        $payRef    = trim($_POST['payment_ref'] ?? '');
        $gcashNum  = trim($_POST['gcash_number'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'gcash');
        $bankName      = trim($_POST['selected_bank'] ?? '');
        $uploadDir = '../uploads/gcash/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $screenshotPath = '';
        $uploadOk       = true; // stays true if no file was sent at all

        if (!empty($_FILES['gcash_screenshot']['name'])) {

            // ── Validate BEFORE touching the filesystem ──
            // Never trust the client-sent filename/extension — detect the
            // real MIME type from the file bytes and derive the extension
            // from THAT, so a renamed .php can't sneak in as "photo.jpg".
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if ($_FILES['gcash_screenshot']['error'] !== UPLOAD_ERR_OK) {
                $msg = 'Upload failed. Please try again.';
                $msgType = 'error';
                $uploadOk = false;
            } elseif ($_FILES['gcash_screenshot']['size'] > 5 * 1024 * 1024) {
                $msg = 'File too large. Max 5MB.';
                $msgType = 'error';
                $uploadOk = false;
            } else {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['gcash_screenshot']['tmp_name']);
                finfo_close($finfo);

                // Double-check it's really a readable image (belt and suspenders,
                // same approach used in api/update_profile_picture.php)
                $imageInfo = @getimagesize($_FILES['gcash_screenshot']['tmp_name']);

                if (!isset($allowedTypes[$mimeType]) || $imageInfo === false) {
                    $msg = 'Invalid file type. Only JPG, PNG, or WEBP allowed.';
                    $msgType = 'error';
                    $uploadOk = false;
                }
            }

            if ($uploadOk) {
                $ext      = $allowedTypes[$mimeType];
                $filename = 'gcash_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

                if (move_uploaded_file($_FILES['gcash_screenshot']['tmp_name'], $uploadDir . $filename)) {
                    $screenshotPath = $uploadDir . $filename;
                } else {
                    $msg = 'Failed to save uploaded file. Please try again.';
                    $msgType = 'error';
                    $uploadOk = false;
                }
            }
        }

        // Only insert the subscription request if the upload (when present)
        // actually passed validation — a rejected file must never silently
        // let the request through with a blank/garbage screenshot path.
        if ($uploadOk) {
            // Cancel any previous pending
            $cancelStmt = $conn->prepare("UPDATE shop_subscriptions SET status='rejected' WHERE shop_id=? AND status='pending'");
            $cancelStmt->bind_param("i", $userId);
            $cancelStmt->execute();
            $cancelStmt->close();

            $stmt = $conn->prepare("
              INSERT INTO shop_subscriptions (shop_id, plan_id, status, payment_ref, gcash_screenshot, gcash_number, payment_method, bank_name)
              VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)
          ");
          $stmt->bind_param("iisssss", $userId, $planId, $payRef, $screenshotPath, $gcashNum, $paymentMethod, $bankName);
            if ($stmt->execute()) {
                $msg     = 'Subscription request submitted! Wait for admin approval.';
                $msgType = 'success';
                // Reload current sub
                $stmt2 = $conn->prepare("
                    SELECT ss.*, sp.name AS plan_name, sp.price, sp.duration_days
                    FROM shop_subscriptions ss
                    JOIN subscription_plans sp ON ss.plan_id = sp.id
                    WHERE ss.shop_id = ?
                    ORDER BY ss.created_at DESC LIMIT 1
                ");
                $stmt2->bind_param("i", $userId);
                $stmt2->execute();
                $currentSub = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $msg     = 'Something went wrong. Please try again.';
                $msgType = 'error';
            }
            $stmt->close();
        }
    }

    $conn->close();

    // ── Compute status info ───────────────────────────────────────
    $isActive  = false;
    $isPending = false;
    $daysLeft  = 0;
    $statusLabel = 'No Subscription';

    if ($currentSub) {
        if ($currentSub['status'] === 'active') {
            $isActive    = true;
            $daysLeft    = max(0, (int)((strtotime($currentSub['end_date']) - time()) / 86400));
            $statusLabel = 'Active';
        } elseif ($currentSub['status'] === 'pending') {
            $isPending   = true;
            $statusLabel = 'Pending Approval';
        } elseif ($currentSub['status'] === 'expired') {
            $statusLabel = 'Expired';
        } elseif ($currentSub['status'] === 'rejected') {
            $statusLabel = 'Payment Rejected';
        }
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Subscription - Fix It Davao</title>
      <link rel="icon" type="image/png" href="../assets/images/logo.png" />
      <link rel="preconnect" href="https://fonts.googleapis.com" />
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
      <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
      <link rel="stylesheet" href="../assets/css/dashboard.css" />
      <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
      <style>

      /* ── PAGE LOAD ANIMATIONS (matches my-bookings.php / admin-dashboard.php) ── */
        @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        .top-bar            { animation: fadeInUp 0.4s ease both; }
        .sub-status-banner  { animation: fadeInUp 0.45s ease both; }
        .plans-grid         { animation: fadeInUp 0.5s ease both; }
        .payment-section.show { animation: fadeInUp 0.35s ease; }
        .pending-info-card  { animation: fadeInUp 0.5s ease both; }
        .dash-card          { animation: fadeInUp 0.55s ease both; }


        /* ── Notif dropdown toggle ── */
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
        /* ── STATUS BANNER ── */
        .sub-status-banner {
          border-radius: 16px; padding: 1.4rem 1.6rem;
          display: flex; align-items: center; gap: 1rem;
          margin-bottom: 1.5rem; position: relative; overflow: hidden;
        }
        .sub-status-banner::before {
          content: ''; position: absolute; inset: 0;
          background: inherit; opacity: 0.08; z-index: 0;
        }
        .banner-active   { background: linear-gradient(135deg,#d1fae5,#a7f3d0); border: 1.5px solid #10b981; }
        .banner-pending  { background: linear-gradient(135deg,#fef3c7,#fde68a); border: 1.5px solid #f59e0b; }
        .banner-expired  { background: linear-gradient(135deg,#fee2e2,#fecaca); border: 1.5px solid #ef4444; }
        .banner-none     { background: linear-gradient(135deg,#f1f5f9,#e2e8f0); border: 1.5px solid #cbd5e1; }

        .banner-icon { font-size: 2.2rem; flex-shrink: 0; z-index: 1; }
        .banner-info { flex: 1; z-index: 1; }
        .banner-title { font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0 0 3px; font-family: 'Outfit', sans-serif; }
        .banner-sub   { font-size: 0.82rem; color: #475569; margin: 0; }
        .banner-badge {
          padding: 5px 14px; border-radius: 20px; font-size: 0.75rem;
          font-weight: 800; flex-shrink: 0; z-index: 1; font-family: 'Outfit', sans-serif;
        }
        .badge-active  { background: #10b981; color: white; }
        .badge-pending { background: #f59e0b; color: white; }
        .badge-expired { background: #ef4444; color: white; }
        .badge-none    { background: #94a3b8; color: white; }

        /* ── PLAN CARDS ── */
        .plans-grid {
          display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
          gap: 1.25rem; margin-bottom: 1.75rem;
        }
        .plan-card {
          background: white; border-radius: 18px; padding: 1.6rem 1.4rem;
          border: 2px solid #e2e8f0; cursor: pointer; position: relative;
          transition: all 0.25s ease; text-align: center;
        }
        .plan-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.10); border-color: #f59e0b; }
        .plan-card.popular { border-color: #cbd5e1; }   /* neutral/gray na lang, ang "⭐ Most Popular" tag ra ang badge indicator */
        .plan-card.selected { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }

        .plan-popular-tag {
          position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
          background: linear-gradient(135deg, #f59e0b, #d97706);
          color: white; font-size: 0.7rem; font-weight: 800;
          padding: 4px 14px; border-radius: 20px; white-space: nowrap;
          font-family: 'Outfit', sans-serif;
        }
        .plan-name  { font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .plan-price { font-size: 2.4rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; line-height: 1; }
        .plan-price span { font-size: 1rem; font-weight: 600; color: #64748b; }
        .plan-duration { font-size: 0.78rem; color: #94a3b8; margin: 6px 0 12px; }
        .plan-desc { font-size: 0.8rem; color: #64748b; line-height: 1.5; }
        .plan-check { display: none; position: absolute; top: 12px; right: 12px; }
        .plan-card.selected .plan-check { display: block; }

        /* ── PAYMENT FORM ── */
        .payment-section {
          background: white; border-radius: 18px; padding: 1.6rem;
          border: 1.5px solid #e2e8f0; margin-bottom: 1.5rem;
          display: none;
        }
        .payment-section.show { display: block; animation: fadeInUp 0.35s ease; }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

        .section-label {
          font-size: 0.95rem; font-weight: 800; color: #0f172a;
          margin: 0 0 1.1rem; display: flex; align-items: center; gap: 8px;
          font-family: 'Outfit', sans-serif;
        }

        .gcash-info-box {
          background: linear-gradient(135deg, #fffbeb, #fef3c7);
          border: 1.5px solid #fde68a; border-radius: 14px;
          padding: 1.1rem 1.3rem; margin-bottom: 1.2rem;
          display: flex; align-items: center; gap: 12px;
        }
        .gcash-info-box .gcash-logo { font-size: 2rem; }
        .gcash-info-box .gcash-details { flex: 1; }
        .gcash-info-box .gcash-number { font-size: 1.3rem; font-weight: 800; color: #0f172a; font-family: 'Space Mono', monospace; }
        .gcash-info-box .gcash-name   { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
        .gcash-info-box .gcash-amount {
          font-size: 1.1rem; font-weight: 800; color: #d97706;
          font-family: 'Outfit', sans-serif; text-align: right;
        }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
        .form-group input[type=text],
        .form-group input[type=tel] {
          width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0;
          border-radius: 10px; font-size: 0.85rem; font-family: 'Outfit', sans-serif;
          outline: none; transition: border-color 0.2s;
          box-sizing: border-box;
        }
        .form-group input:focus { border-color: #f59e0b; }

        .upload-area {
          border: 2px dashed #e2e8f0; border-radius: 12px; padding: 1.4rem;
          text-align: center; cursor: pointer; transition: all 0.2s;
          position: relative; overflow: hidden;
        }
        .upload-area:hover { border-color: #f59e0b; background: #fffbeb; }
        .upload-area input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .upload-area .upload-icon { font-size: 2rem; margin-bottom: 6px; }
        .upload-area p { font-size: 0.82rem; color: #64748b; margin: 0; }
        .upload-area .upload-preview { max-width: 100%; max-height: 180px; border-radius: 8px; margin-top: 10px; display: none; }

        .btn-submit-sub {
          width: 100%; padding: 13px; border: none; border-radius: 12px;
          background: linear-gradient(135deg, #f59e0b, #d97706);
          color: white; font-size: 0.95rem; font-weight: 800;
          font-family: 'Outfit', sans-serif; cursor: pointer;
          transition: all 0.25s; margin-top: 0.5rem;
        }
        .btn-submit-sub:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(245,158,11,0.35); }
        .btn-submit-sub:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* ── PENDING INFO ── */
        .pending-info-card {
          background: white; border-radius: 18px; padding: 1.6rem;
          border: 1.5px solid #fde68a; text-align: center;
        }
        .pending-info-card .pi-icon { font-size: 3rem; margin-bottom: 12px; }
        .pending-info-card h3 { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .pending-info-card p  { font-size: 0.85rem; color: #64748b; margin: 0; line-height: 1.6; }

        /* ── ALERT / MSG ── */
        .alert {
          padding: 12px 16px; border-radius: 10px; font-size: 0.85rem;
          font-weight: 600; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;
          font-family: 'Outfit', sans-serif;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── HISTORY TABLE ── */
        .history-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .history-table th { padding: 8px 12px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #f1f5f9; }
        .history-table td { padding: 10px 12px; border-bottom: 1px solid #f8fafc; color: #374151; }
        .status-pill { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .pill-active  { background: #d1fae5; color: #065f46; }
        .pill-pending { background: #fef3c7; color: #92400e; }
        .pill-expired { background: #fee2e2; color: #991b1b; }
        .pill-rejected{ background: #f1f5f9; color: #64748b; }

        .dash-card { background: white; border-radius: 16px; padding: 1.4rem 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.07); border: 1px solid #f1f5f9; margin-bottom: 1.5rem; }

        /* ── LOGOUT MODAL ── */
        .modal-overlay { position:fixed;inset:0;background:rgba(10,15,30,.72);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity .3s ease;padding:20px; }
        .modal-overlay.visible { opacity:1;pointer-events:all; }
        .modal-box { background:white;border-radius:20px;padding:32px 28px;max-width:420px;width:100%;box-shadow:0 40px 100px rgba(0,0,0,.25);transform:scale(.9) translateY(20px);opacity:0;transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s ease; }
        .modal-overlay.visible .modal-box { transform:scale(1) translateY(0);opacity:1; }
        .modal-btn-cancel { flex:1;padding:11px;border:2px solid #e2e8f0;border-radius:10px;background:white;font-size:13px;font-weight:700;font-family:var(--font-primary);cursor:pointer;color:#64748b;transition:all .2s; }
        .modal-btn-confirm { flex:1;padding:11px;border:none;border-radius:10px;color:white;font-size:13px;font-weight:700;font-family:var(--font-primary);cursor:pointer;transition:all .2s; }

        @media (max-width: 640px) {
          .plans-grid { grid-template-columns: 1fr; }
          .gcash-info-box { flex-direction: column; text-align: center; }
          .gcash-info-box .gcash-amount { text-align: center; }
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
      <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
      <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

      <aside class="sidebar">
        <div class="sidebar-header">
          <div class="logo-mini"><img src="../assets/images/logo.png" alt="Fix It Davao" /></div>
          <h2 class="brand-name">FIX IT DAVAO</h2>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section" data-role="repairshop">
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
            <a href="shop-subscription.php" class="nav-item active">
              <span class="nav-icon"><img src="../assets/icons/approve.svg" alt="Subscription" /></span>
              <span class="nav-text">Subscription</span>
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

      <main class="main-content">
        <header class="top-bar">
          <div class="page-header">
            <h1 class="current-page-title">Subscription</h1>
          </div>
          <div class="top-bar-actions">
              <div class="notif-wrapper" style="position:relative;display:inline-block;">
      <button class="icon-btn notification-btn" id="notifBtn">
        <img src="../assets/icons/bell.svg" alt="" width="20" height="20" />
      </button>
      <span class="notif-badge" id="notifBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:.6rem;font-weight:700;min-width:18px;height:18px;border-radius:20px;align-items:center;justify-content:center;padding:0 4px;font-family:'Outfit',sans-serif;border:2px solid white;"></span>
      <div class="notif-dropdown" id="notifDropdown" style="position:absolute;top:calc(100% + 10px);right:0;width:320px;background:white;border-radius:16px;border:1.5px solid #e2e8f0;box-shadow:0 10px 40px rgba(0,0,0,.15);z-index:500;overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;">
          <span style="font-size:.85rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif;">Notifications</span>
          <button onclick="markAllRead()" style="font-size:.72rem;font-weight:700;color:#f59e0b;background:none;border:none;cursor:pointer;font-family:'Outfit',sans-serif;padding:3px 8px;border-radius:6px;" onmouseover="this.style.background='#fff7e6'" onmouseout="this.style.background='none'">Mark all read</button>
        </div>
        <div id="notifList" style="max-height:320px;overflow-y:auto;">
          <div style="padding:1.5rem;text-align:center;font-size:.83rem;color:#94a3b8;">Loading...</div>
        </div>
      </div>
    </div>
            <div class="user-profile">
              <?php if ($avatarUrl): ?>
              <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($userName); ?>"
                style="width:38px;height:38px;border-radius:12px;object-fit:cover;border:2px solid #f59e0b;flex-shrink:0;" />
              <?php else: ?>
              <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;font-size:.85rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;flex-shrink:0;">
                <?php echo strtoupper(substr($userName, 0, 2)); ?>
              </div>
              <?php endif; ?>
              <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                <span class="user-role">Repair Shop</span>
              </div>
            </div>
          </div>
        </header>

        <div class="dashboard-content">

          <?php if ($msg): ?>
          <div class="alert alert-<?php echo $msgType; ?>">
            <?php echo $msgType === 'success' ? '<img src="../assets/icons/approve.svg" width="16" height="16" style="vertical-align:middle;">' : '<img src="../assets/icons/suspend.svg" width="16" height="16" style="vertical-align:middle;">'; ?>
            <?php echo htmlspecialchars($msg); ?>
          </div>
          <?php endif; ?>

          <!-- ── STATUS BANNER ── -->
          <?php if ($isActive): ?>
          <div class="sub-status-banner banner-active">
            <div class="banner-icon"><img src="../assets/icons/approve.svg" width="30" height="30"></div>
            <div class="banner-info">
              <div class="banner-title"><?php echo htmlspecialchars($currentSub['plan_name']); ?> Plan — Active</div>
              <div class="banner-sub">
                <?php echo $daysLeft; ?> day<?php echo $daysLeft !== 1 ? 's' : ''; ?> remaining &nbsp;·&nbsp;
                Expires <?php echo date('F j, Y', strtotime($currentSub['end_date'])); ?>
              </div>
            </div>
            <div class="banner-badge badge-active">ACTIVE</div>
          </div>
          <?php elseif ($isPending): ?>
          <div class="sub-status-banner banner-pending">
            <div class="banner-icon"><img src="../assets/icons/glass.svg" width="30" height="30"></div>
            <div class="banner-info">
              <div class="banner-title">Payment Under Review</div>
              <div class="banner-sub">Your <?php echo htmlspecialchars($currentSub['plan_name']); ?> plan payment is being verified by admin.</div>
            </div>
            <div class="banner-badge badge-pending">PENDING</div>
          </div>
          <?php elseif ($currentSub && $currentSub['status'] === 'expired'): ?>
          <div class="sub-status-banner banner-expired">
            <div class="banner-icon"><img src="../assets/icons/danger.svg" alt="" width="26 "height="26" /></div>
            <div class="banner-info">
              <div class="banner-title">Subscription Expired</div>
              <div class="banner-sub">Your <?php echo htmlspecialchars($currentSub['plan_name']); ?> plan expired on <?php echo date('F j, Y', strtotime($currentSub['end_date'])); ?>. Renew to continue using the platform.</div>
            </div>
            <div class="banner-badge badge-expired">EXPIRED</div>
          </div>
          <?php elseif ($currentSub && $currentSub['status'] === 'rejected'): ?>
          <div class="sub-status-banner banner-expired">
            <div class="banner-icon"><img src="../assets/icons/suspend.svg" width="30" height="30"></div>
            <div class="banner-info">
              <div class="banner-title">Payment Rejected</div>
              <div class="banner-sub">
                <?php echo $currentSub['admin_note'] ? 'Note: ' . htmlspecialchars($currentSub['admin_note']) : 'Your last payment was rejected. Please resubmit with a valid GCash screenshot.'; ?>
              </div>
            </div>
            <div class="banner-badge badge-expired">REJECTED</div>
          </div>
          <?php else: ?>
          <div class="sub-status-banner banner-none">
            <div class="banner-icon"><img src="../assets/icons/lock.svg" width="30" height="30"></div>
            <div class="banner-info">
              <div class="banner-title">No Active Subscription</div>
              <div class="banner-sub">Subscribe to access all Fix It Davao platform features.</div>
            </div>
            <div class="banner-badge badge-none">INACTIVE</div>
          </div>
          <?php endif; ?>

          <?php if ($isPending): ?>
          <!-- ── PENDING WAITING STATE ── -->
          <div class="pending-info-card">
            <div class="pi-icon"><img src="../assets/icons/glass.svg" width="40" height="40"></div>
            <h3>Waiting for Admin Approval</h3>
            <p>Your payment for the <strong><?php echo htmlspecialchars($currentSub['plan_name']); ?> Plan (₱<?php echo number_format($currentSub['price'], 2); ?>)</strong> is currently under review.<br>
            Admin will verify your GCash screenshot and activate your subscription shortly.<br><br>
            <span style="color:#94a3b8;font-size:.78rem;">Reference: <?php echo htmlspecialchars($currentSub['payment_ref'] ?: 'N/A'); ?></span></p>
          </div>

          <?php else: ?>
          <!-- ── PLAN SELECTION ── -->
          <h2 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0 0 .85rem;font-family:'Outfit',sans-serif;">
            <?php if ($isActive): ?>
        <img src="../assets/icons/renew.svg" alt="" width="16" height="16" style="vertical-align:middle;margin-right:4px;" /> Renew / Upgrade Plan
        <?php else: ?>
      <img src="../assets/icons/lock.svg" alt="" width="16" height="16" style="vertical-align:middle;margin-right:4px;" /> Choose a Plan
      <?php endif; ?>
          </h2>

          <form method="POST" enctype="multipart/form-data" id="subForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <div class="plans-grid">
              <?php
// ── Current plan id, para ma-preselect dayon kung naa nay subscription (active o expired) ──
$currentPlanId = ($currentSub && in_array($currentSub['status'], ['active', 'expired'], true))
    ? (int) $currentSub['plan_id']
    : null;
?>
<?php foreach ($plans as $i => $plan): ?>
<?php $isCurrentPlan = ($currentPlanId !== null && (int)$plan['id'] === $currentPlanId); ?>
<div class="plan-card <?php echo $i === 1 ? 'popular' : ''; ?> <?php echo $isCurrentPlan ? 'selected' : ''; ?>"
  data-plan-id="<?php echo $plan['id']; ?>"
  data-plan-price="<?php echo $plan['price']; ?>"
  data-plan-name="<?php echo htmlspecialchars($plan['name'], ENT_QUOTES); ?>"
  onclick="selectPlan(<?php echo $plan['id']; ?>, <?php echo $plan['price']; ?>, '<?php echo addslashes($plan['name']); ?>')">
                <?php if ($i === 1): ?>
                <div class="plan-popular-tag"><img src="../assets/icons/shine.svg" width="12" height="12" style="filter:brightness(0) invert(1);vertical-align:middle;margin-right:3px;"> Most Popular</div>
                <?php endif; ?>
                <div class="plan-check">
                  <svg width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
                </div>
                <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                <div class="plan-price">₱<?php echo number_format($plan['price'], 0); ?><span>/<?php echo $plan['duration_days'] >= 365 ? 'year' : ($plan['duration_days'] >= 60 ? '3 months' : 'month'); ?></span></div>
                <div class="plan-duration"><?php echo $plan['duration_days']; ?> days access</div>
                <div class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <input type="hidden" name="plan_id" id="selectedPlanId" value="" />

            <!-- ── PAYMENT FORM ── -->
            <div class="payment-section" id="paymentSection">
          <div class="section-label"><img src="../assets/icons/credit.svg" width="18" height="18"> Payment Details</div>

          <!-- Payment method tabs -->
          <div class="payment-method-tabs" style="display:flex;gap:8px;margin-bottom:1.2rem;">
            <button type="button" class="pm-tab active" data-method="gcash" onclick="switchPaymentMethod('gcash', this)"
              style="flex:1;padding:10px;border:2px solid #f59e0b;border-radius:10px;background:#fffbeb;color:#d97706;font-weight:700;font-size:.85rem;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .2s;">
              <img src="../assets/icons/gcash.svg" width="16" height="16" style="vertical-align:middle;margin-right:4px;"> GCash
            </button>
            <button type="button" class="pm-tab" data-method="bank" onclick="switchPaymentMethod('bank', this)"
              style="flex:1;padding:10px;border:2px solid #e2e8f0;border-radius:10px;background:white;color:#64748b;font-weight:700;font-size:.85rem;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .2s;">
              <img src="../assets/icons/bank.svg" width="16" height="16" style="vertical-align:middle;margin-right:4px;"> Bank Transfer
            </button>
          </div>
          <input type="hidden" name="payment_method" id="paymentMethod" value="gcash" />

          <!-- GCash info box -->
          <div class="gcash-info-box" id="gcashInfoBox">
            <div class="gcash-logo"><img src="../assets/icons/gcash.svg" width="32" height="32"></div>
            <div class="gcash-details">
              <div class="gcash-number">0917-123-4567</div>
              <div class="gcash-name">Fix It Davao Admin</div>
            </div>
            <div class="gcash-amount" id="gcashAmount">₱0.00</div>
          </div>

    <!-- Bank info box (hidden by default) -->
    <div class="gcash-info-box" id="bankInfoBox" style="display:none;flex-direction:column;align-items:stretch;">
      <div class="form-group" style="margin-bottom:.8rem;">
        <label>Select Bank *</label>
        <select id="bankSelect" onchange="updateBankDetails(this.value)"
          style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.85rem;font-family:'Outfit',sans-serif;outline:none;box-sizing:border-box;cursor:pointer;">
          <option value="bdo">BDO</option>
          <option value="bpi">BPI</option>
          <option value="metrobank">Metrobank</option>
          <option value="unionbank">UnionBank</option>
          <option value="landbank">Landbank</option>
        </select>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="gcash-logo"><img src="../assets/icons/bank.svg" width="32" height="32"></div>
        <div class="gcash-details">
          <div class="gcash-number" id="bankAccountNumber" style="font-size:1rem;">BDO — 001234567890</div>
          <div class="gcash-name" id="bankAccountName">Fix It Davao Corp.</div>
        </div>
        <div class="gcash-amount" id="bankAmount">₱0.00</div>
      </div>
    </div>
    <input type="hidden" name="selected_bank" id="selectedBank" value="bdo" />

          <p style="font-size:.82rem;color:#64748b;margin:0 0 1rem;line-height:1.6;" id="paymentInstructions">
            Send the exact amount to the GCash number above, then fill in the details below and upload your screenshot as proof of payment.
          </p>

          <div class="form-group">
            <label id="senderLabel">Your GCash Number *</label>
            <input type="tel" name="gcash_number" id="senderInput" placeholder="e.g., 0917-123-4567" required />
          </div>
          <div class="form-group">
            <label id="refLabel">GCash Reference Number *</label>
            <input type="text" name="payment_ref" id="refInput" placeholder="e.g., 1234567890" required />
          </div>
          <div class="form-group">
            <label id="screenshotLabel">Upload GCash Screenshot *</label>
            <div class="upload-area" id="uploadArea">
              <input type="file" name="gcash_screenshot" accept="image/*" id="screenshotInput" onchange="previewScreenshot(this)" required />
              <div class="upload-icon"><img src="../assets/icons/upload.svg" width="32" height="32"></div>
              <p id="uploadHint">Click to upload your GCash payment screenshot</p>
              <img id="screenshotPreview" class="upload-preview" alt="Screenshot preview" />
            </div>
          </div>

          <button type="submit" class="btn-submit-sub" id="submitBtn">
            <img src="../assets/icons/upload.svg" width="16" height="16" style="filter:brightness(0) invert(1);vertical-align:middle;margin-right:6px;"> Submit Subscription Request
          </button>
          <p style="font-size:.75rem;color:#94a3b8;text-align:center;margin-top:8px;">
            Admin will verify your payment within 24 hours.
          </p>
        </div>
          </form>
          <?php endif; ?>

          <!-- ── SUBSCRIPTION HISTORY ── -->
          <?php
          $connH = new mysqli("localhost", "root", "", "fixitdavao");
            $stmtH = $connH->prepare("
          SELECT ss.*, sp.name AS plan_name, sp.price
          FROM shop_subscriptions ss
          JOIN subscription_plans sp ON ss.plan_id = sp.id
          WHERE ss.shop_id = ?
          ORDER BY ss.created_at DESC
          LIMIT 10
      ");
          $stmtH->bind_param("i", $userId);
          $stmtH->execute();
          $history = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
          $stmtH->close();
          $connH->close();
          ?>
          <?php if (!empty($history)): ?>
          <div class="dash-card">
            <div style="font-size:.95rem;font-weight:800;color:#0f172a;margin:0 0 1rem;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:8px;"><img src="../assets/icons/receipt.svg" width="18" height="18"> Subscription History</div>
            <div style="overflow-x:auto;">
              <table class="history-table">
                <thead>
                  <tr>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Reference</th>
                  </tr>
                </thead>
                <tbody>
                              <?php foreach ($history as $h): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($h['plan_name']); ?></strong></td>
                  <td>₱<?php echo number_format($h['price'], 2); ?></td>
                  <td>
                    <?php echo $h['payment_method'] === 'bank'
      ? '<img src="../assets/icons/bank.svg" width="14" height="14" style="vertical-align:middle;margin-right:4px;">' . strtoupper($h['bank_name'] ?: 'Bank')
      : '<img src="../assets/icons/gcash.svg" width="14" height="14" style="vertical-align:middle;margin-right:4px;">GCash'; ?>
                  </td>
                  <td><?php echo $h['start_date'] ? date('M j, Y', strtotime($h['start_date'])) : '—'; ?></td>
                  <td><?php echo $h['end_date']   ? date('M j, Y', strtotime($h['end_date']))   : '—'; ?></td>
                  <td><span class="status-pill pill-<?php echo $h['status']; ?>"><?php echo ucfirst($h['status']); ?></span></td>
                  <td style="font-family:'Space Mono',monospace;font-size:.75rem;"><?php echo htmlspecialchars($h['payment_ref'] ?: '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

        </div>
        <footer class="dashboard-footer">© 2026 All Rights Reserved. | FIX IT DAVAO</footer>
      </main>

      <script>
        // ── Sidebar ──
        const sidebar  = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        document.getElementById('mobileMenuToggle').addEventListener('click', () => {
          sidebar.classList.toggle('active');
          document.body.classList.toggle('sidebar-open');
        });
        if (backdrop) backdrop.addEventListener('click', () => {
          sidebar.classList.remove('active');
          document.body.classList.remove('sidebar-open');
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

        // ── Plan selection ──
    let selectedPlanId = null;

    function selectPlan(id, price, name) {
      selectedPlanId = id;
      document.getElementById('selectedPlanId').value = id;

      // Highlight selected card
      document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
      event.currentTarget.classList.add('selected');

      // Show payment section + update amount
      document.getElementById('paymentSection').classList.add('show');
      document.getElementById('gcashAmount').textContent = '₱' + parseFloat(price).toLocaleString('en-PH', { minimumFractionDigits: 2 });
      document.getElementById('bankAmount').textContent  = '₱' + parseFloat(price).toLocaleString('en-PH', { minimumFractionDigits: 2 }); // ← dungag pud ni, wa pa diay ni napatch

      // Scroll to payment
      setTimeout(() => {
        document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }

    const BANK_ACCOUNTS = {
      bdo:        { number: '001234567890', name: 'Fix It Davao Corp.', label: 'BDO' },
      bpi:        { number: '009876543210', name: 'Fix It Davao Corp.', label: 'BPI' },
      metrobank:  { number: '005566778899', name: 'Fix It Davao Corp.', label: 'Metrobank' },
      unionbank:  { number: '001122334455', name: 'Fix It Davao Corp.', label: 'UnionBank' },
      landbank:   { number: '009988776655', name: 'Fix It Davao Corp.', label: 'Landbank' },
    };

    function updateBankDetails(bankKey) {
      const bank = BANK_ACCOUNTS[bankKey];
      if (!bank) return;
      document.getElementById('bankAccountNumber').textContent = `${bank.label} — ${bank.number}`;
      document.getElementById('bankAccountName').textContent   = bank.name;
      document.getElementById('selectedBank').value = bankKey;
    }
    // ── Payment method toggle ── (separate na siya nga function, dili na sulod sa selectPlan)
    function switchPaymentMethod(method, btn) {
      document.querySelectorAll('.pm-tab').forEach(t => {
        t.classList.remove('active');
        t.style.borderColor = '#e2e8f0';
        t.style.background = 'white';
        t.style.color = '#64748b';
      });
      btn.classList.add('active');
      btn.style.borderColor = '#f59e0b';
      btn.style.background = '#fffbeb';
      btn.style.color = '#d97706';

      document.getElementById('paymentMethod').value = method;
      document.getElementById('gcashInfoBox').style.display = method === 'gcash' ? 'flex' : 'none';
      document.getElementById('bankInfoBox').style.display  = method === 'bank'  ? 'flex' : 'none';

      if (method === 'bank') {
        updateBankDetails(document.getElementById('bankSelect').value);
      }

      const isBank = method === 'bank';
      document.getElementById('paymentInstructions').textContent = isBank
        ? 'Transfer the exact amount to the bank account above, then fill in the details below and upload your deposit/transfer receipt as proof of payment.'
        : 'Send the exact amount to the GCash number above, then fill in the details below and upload your screenshot as proof of payment.';

      document.getElementById('senderLabel').textContent = isBank ? 'Your Bank Account Name *' : 'Your GCash Number *';
      document.getElementById('senderInput').placeholder  = isBank ? 'e.g., Juan Dela Cruz' : 'e.g., 0917-123-4567';
      document.getElementById('senderInput').type         = isBank ? 'text' : 'tel';

      document.getElementById('refLabel').textContent = isBank ? 'Bank Transaction Reference *' : 'GCash Reference Number *';
      document.getElementById('refInput').placeholder  = isBank ? 'e.g., TXN-20260729-001' : 'e.g., 1234567890';

      document.getElementById('screenshotLabel').textContent = isBank ? 'Upload Deposit/Transfer Receipt *' : 'Upload GCash Screenshot *';
      document.getElementById('uploadHint').textContent       = isBank ? 'Click to upload your bank receipt' : 'Click to upload your GCash payment screenshot';
        }

        // ── Screenshot preview ──
        function previewScreenshot(input) {
          const preview = document.getElementById('screenshotPreview');
          if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
              preview.src = e.target.result;
              preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
          }
        }

        // ── Notifications ──
        let notifOpen = false;

      document.getElementById('notifBtn').addEventListener('click', function(e) {
      e.stopPropagation();
      notifOpen = !notifOpen;
      const dropdown = document.getElementById('notifDropdown');
      dropdown.classList.toggle('open', notifOpen);
      if (notifOpen) { loadNotifications(); markAllRead(); }
    });

    document.addEventListener('click', (e) => {
      const wrapper = document.getElementById('notifBtn').parentElement;
      const dropdown = document.getElementById('notifDropdown');
      if (!wrapper.contains(e.target)) {
        dropdown.classList.remove('open');
        notifOpen = false;
      }
    });
        async function loadNotifications() {
          const badge = document.getElementById('notifBadge');
          const list  = document.getElementById('notifList');
          try {
            const res  = await fetch('../api/get_shop_notifications.php');
            const text = await res.text();
            const data = JSON.parse(text);
            if (!data.success) throw new Error('Not success');

            if (data.unread_count > 0) {
              badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
              badge.style.display = 'flex';
            } else {
              badge.style.display = 'none';
            }

            if (!data.notifications || !data.notifications.length) {
              list.innerHTML = `<div style="padding:2rem 1rem;text-align:center;"><img src="../assets/icons/bell.svg" width="32" style="opacity:.3;display:block;margin:0 auto 8px;"/><div style="font-size:.83rem;color:#94a3b8;font-family:'Outfit',sans-serif;">No notifications yet.</div></div>`;
              return;
            }

            const ICON = {
              pending:   `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#f59e0b"/><rect x="9" y="7" width="2" height="6" rx="1" fill="white"/><rect x="13" y="7" width="2" height="6" rx="1" fill="white"/><rect x="8" y="15" width="8" height="2" rx="1" fill="white"/></svg>`,
              cancelled: `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>`,
              review:    `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#8b5cf6"/><text x="12" y="16" text-anchor="middle" font-size="11" fill="white">★</text></svg>`,
              active:    `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#10b981"/><polyline points="7,12 10.5,15.5 17,9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>`,
              rejected:  `<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#ef4444"/><line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>`,
            };

            const AVATAR_BG = { pending: 'f59e0b', cancelled: 'ef4444', review: '8b5cf6', active: '10b981', rejected: 'ef4444' };
            list.innerHTML = data.notifications.map(n => {
              const time = n.time ? new Date(n.time).toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' }) : '';
              const bg   = n.is_read ? '' : 'background:#fffbeb;';
              const dest = n.type === 'review' ? 'shop-reviews.php' : n.type === 'subscription' ? 'shop-subscription.php' : 'shop-bookings.php';
              const avatarBg = AVATAR_BG[n.status] || '94a3b8';
              const displayName = n.type === 'subscription' ? 'Subscription' : (n.customer_name || 'Customer');
              const avatarUrl = n.customer_picture 
    ? n.customer_picture 
    : `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=${avatarBg}&color=fff&size=80`;
              const msgText = n.type === 'subscription'
                ? (n.status === 'active'
                    ? `Your ${n.plan_name || ''} subscription was approved ✅`
                    : `Your ${n.plan_name || ''} subscription was declined`)
                : n.type === 'reschedule' ? `${n.customer_name} rescheduled their booking 📅`
                : n.type === 'review' ? 'Left you a review'
                : n.status === 'pending' ? 'Booked your shop'
                : n.status === 'confirmed' ? 'Booking confirmed ✅'
                : n.status === 'completed' ? 'Booking completed 🎉'
                : 'Cancelled their booking';

              return `<div onclick="window.location.href='${dest}'" style="display:flex;align-items:flex-start;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid #f8fafc;cursor:pointer;${bg}">
  <img src="${avatarUrl}"
    style="width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid #e2e8f0;"
    onerror="this.src='https://ui-avatars.com/api/?name=Customer&background=94a3b8&color=fff&size=80'" />
  <div style="flex:1;min-width:0;">                                                                                                                                                             
                  <div style="font-size:.82rem;font-weight:800;color:#0f172a;">${displayName}</div>
                  <div style="font-size:.75rem;color:#64748b;margin-top:1px;">${msgText}</div>
                  ${n.service_name ? `<div style="font-size:.72rem;color:#d97706;margin-top:2px;">🔧 ${n.service_name}</div>` : ''}
                  <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">${time}</div>
                </div>
                ${!n.is_read ? '<div style="width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0;margin-top:4px;"></div>' : ''}
              </div>`;
            }).join('');

          } catch(e) {
            list.innerHTML = `<div style="padding:2rem 1rem;text-align:center;font-size:.83rem;color:#94a3b8;font-family:'Outfit',sans-serif;">No notifications yet.</div>`;
          }
        }

        async function markAllRead() {
          await fetch('../api/get_shop_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_read: true })
          });
          document.getElementById('notifBadge').style.display = 'none';
        }

        loadNotifications();
        document.getElementById('subForm')?.addEventListener('submit', function(e) {
          if (!selectedPlanId) {
            e.preventDefault();
            alert('Please select a subscription plan first.');
            return;
          }
          const btn = document.getElementById('submitBtn');
          btn.disabled = true;
          btn.textContent = '⏳ Submitting...';
        });
      </script>
      <script>
    setTimeout(function () {
        window.location.href = "../login.php?timeout=1";
    }, 1800000); // 30 minutes
    </script>
    </body>
    </html>