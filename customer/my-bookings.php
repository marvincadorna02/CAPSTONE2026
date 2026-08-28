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
      header("Location: " . ($_SESSION['role'] === 'repairshop' ? '../shop-owner/shop-dashboard.php' : '../admin/admin-dashboard.php'));
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
  $userContact    = '';
  $conn = new mysqli("localhost", "root", "", "fixitdavao");
  if (!$conn->connect_error) {
      $stmt = $conn->prepare("SELECT profile_picture, contact_number FROM users WHERE id = ?");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $userProfilePic = $row['profile_picture'] ?? null;
      $userContact    = $row['contact_number'] ?? '';
      $stmt->close();
      $conn->close();
  }

  $avatarUrl = $userProfilePic ?: ("https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff");
  ?>
  <!doctype html>
  <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
      <title>My Bookings - Fix It Davao</title>
      <link rel="icon" type="image/png" href="../assets/images/logo.png" />
      <link rel="preconnect" href="https://fonts.googleapis.com" />
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
      <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
      <link rel="stylesheet" href="../assets/css/dashboard.css" />
      <link rel="stylesheet" href="../assets/css/dashboard-mobile-additions.css" />
      <style>
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
  .notif-mark-read:hover { background:#fff7e6; color:#d97706; }
  .notif-list { max-height:340px; overflow-y:auto; }
  .notif-item {
    display:flex; align-items:flex-start; gap:.75rem;
    padding:.85rem 1rem; border-bottom:1px solid #f8fafc;
    cursor:pointer; transition:background .15s;
  }
  .notif-item:hover { background:#f8fafc; }
  .notif-item.unread { background:#eff6ff; }
  .notif-item.unread:hover { background:#dbeafe; }
  .notif-logo { width:36px; height:36px; border-radius:10px; object-fit:cover; flex-shrink:0; border:1px solid #e2e8f0; }
  .notif-content { flex:1; }
  .notif-message { font-size:.8rem; font-weight:600; color:#0f172a; line-height:1.4; }
  .notif-message span { font-weight:800; }
  .notif-time { font-size:.7rem; color:#94a3b8; margin-top:2px; }
  .notif-dot { width:8px; height:8px; border-radius:50%; background:#3b82f6; flex-shrink:0; margin-top:4px; }
  .notif-loading { padding:1.5rem; text-align:center; font-size:.83rem; color:#94a3b8; }
  .notif-empty { padding:2rem 1rem; text-align:center; font-size:.83rem; color:#94a3b8; }

        html { background: #f8fafc; }
        .top-bar        { animation: fadeInUp 0.4s ease both; }
        .approval-tabs  { animation: fadeInUp 0.5s ease both; }
        #bookingsGrid   { animation: fadeInUp 0.6s ease both; }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

        /* ── TABS ── */
        .approval-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .tab-btn {
          padding: 7px 16px; border-radius: 20px; border: 2px solid #e2e8f0;
          background: white; font-size: .8rem; font-weight: 700;
          font-family: "Outfit", sans-serif; cursor: pointer; color: #64748b;
          transition: all .2s ease;
        }
        .tab-btn:hover { border-color: #d97706; color: #d97706; }
        .tab-btn.active { background: linear-gradient(135deg,#d97706,#f59e0b); color: white; border-color: transparent; box-shadow: 0 3px 10px rgba(37,99,235,.3); }

        /* ── BOOKING CARDS ── */
        #bookingsGrid { display: flex; flex-direction: column; gap: .85rem; }
        .booking-card {
          background: white; border-radius: 16px; border: 1.5px solid #e2e8f0;
          box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden;
          transition: box-shadow .2s, border-color .2s;
          animation: fadeInUp .35s ease both;
        }
        .booking-card:hover { box-shadow: 0 6px 22px rgba(0,0,0,.1); border-color: #93c5fd; }

        .bc-header {
          display: flex; align-items: center; justify-content: space-between;
          padding: .9rem 1.1rem; border-bottom: 1px solid #f1f5f9;
          gap: 1rem; flex-wrap: wrap;
        }
        .bc-shop-info { display: flex; align-items: center; gap: .75rem; }
        .bc-shop-logo { width: 46px; height: 46px; border-radius: 12px; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0; }
        .bc-shop-name { font-size: .9rem; font-weight: 800; color: #0f172a; }
        .bc-shop-loc  { font-size: .75rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 3px; }

        .status-badge { font-size: .72rem; font-weight: 700; padding: 4px 11px; border-radius: 20px; white-space: nowrap; }
        .status-pending   { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-no_show   { background: #f3e8ff; color: #6b21a8; }
        .status-paid      { background: #ccfbf1; color: #115e59; }
        .status-claimed   { background: #e0e7ff; color: #3730a3; }

        .bc-body { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem; padding: .9rem 1.1rem; }
        .bc-detail { display: flex; flex-direction: column; gap: 2px; }
        .bc-label  { font-size: .67rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; }
        .bc-value  { font-size: .83rem; font-weight: 600; color: #374151; }
        .bc-value.service { color: #d97706; }

        .bc-problem {
          padding: .6rem 1.1rem .85rem;
          font-size: .82rem; color: #475569; line-height: 1.5;
          border-top: 1px solid #f8fafc;
        }
        .bc-problem strong { color: #374151; }

        .bc-footer {
          padding: .7rem 1.1rem; border-top: 1px solid #f1f5f9;
          display: flex; gap: 7px; flex-wrap: wrap; align-items: center;
        }
        .bc-date-label { font-size: .72rem; color: #94a3b8; margin-left: auto; }

        .action-btn {
          padding: 7px 14px; border-radius: 9px; font-size: .78rem;
          font-weight: 700; font-family: "Outfit", sans-serif;
          cursor: pointer; border: none; transition: all .2s ease;
          display: flex; align-items: center; gap: 5px;
        }
        .action-btn img { width: 13px; height: 13px; }
        .btn-view    { background: #f1f5f9; color: #475569; }
        .btn-view:hover { background: #e2e8f0; }
        .btn-cancel  { background: linear-gradient(135deg,#ef4444,#dc2626); color: white; }
        .btn-cancel:hover { background: linear-gradient(135deg,#dc2626,#b91c1c); color: white; }
        .btn-reschedule { background: linear-gradient(135deg,#f59e0b,#d97706); color: white; }
        .btn-reschedule:hover { background: linear-gradient(135deg,#d97706,#b45309); color: white; }
        .action-btn:disabled { opacity: .4; cursor: not-allowed; }

        /* ── LOADING ── */
        .loading-state { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
        .spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 14px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── EMPTY ── */
        .empty-state {
          display: flex; flex-direction: column; align-items: center;
          justify-content: center; padding: 4rem 2rem; text-align: center;
          animation: fadeInUp .5s ease both;
        }
        .empty-state img { width: 72px; height: 72px; opacity: .35; margin-bottom: 1.25rem; }
        .empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #64748b; margin-bottom: 6px; }
        .empty-state p  { font-size: .875rem; color: #94a3b8; margin-bottom: 1.5rem; }
        .btn-find-shops {
          display: inline-flex; align-items: center; gap: 6px;
          padding: .65rem 1.5rem; border-radius: 12px;
          background: linear-gradient(135deg,#2563eb,#1d4ed8);
          color: white; font-weight: 700; font-size: .875rem;
          font-family: "Outfit", sans-serif; text-decoration: none;
          box-shadow: 0 4px 14px rgba(37,99,235,.3); transition: all .2s;
        }
        .btn-find-shops:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,99,235,.4); }

        /* ── DETAIL MODAL (renamed to avoid clash with logout/profile modal classes) ── */
      .detail-modal-overlay {
        position: fixed; inset: 0; background: rgba(10,15,30,.75);
        backdrop-filter: blur(4px); display: flex; align-items: center;
        justify-content: center; z-index: 1000; opacity: 0;
        pointer-events: none; transition: opacity .3s ease; padding: 20px;
      }
      .detail-modal-overlay.visible { opacity: 1; pointer-events: all; }
      .detail-modal-box {
        background: white; border-radius: 22px; padding: 0;
        max-width: 480px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,.28);
        transform: scale(.9) translateY(20px); opacity: 0;
        transition: transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
        overflow: hidden;
      }
      .detail-modal-overlay.visible .detail-modal-box { transform: scale(1) translateY(0); opacity: 1; }
        .modal-banner { padding: 1.25rem 1.5rem 1rem; display: flex; align-items: center; gap: 1rem; }
        .modal-shop-logo { width: 52px; height: 52px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(255,255,255,.4); flex-shrink: 0; }
        .modal-shop-name { font-size: 1.05rem; font-weight: 800; color: white; }
        .modal-booking-id { font-size: .75rem; color: rgba(255,255,255,.75); margin-top: 2px; }
        .modal-body { padding: 1.25rem 1.5rem 1.5rem; }
        .modal-status-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-section-title { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .7px; color: #94a3b8; margin-bottom: 8px; margin-top: 1rem; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
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
        .detail-item { background: #f8fafc; border-radius: 10px; padding: 9px 12px; }
        .detail-item-label { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 3px; }
        .detail-item-value { font-size: .85rem; font-weight: 600; color: #0f172a; }
        .problem-box { background: #fff7ed; border: 1px solid #fde68a; border-radius: 10px; padding: 11px 13px; margin-top: 8px; font-size: .83rem; color: #374151; line-height: 1.55; }
        .modal-footer { display: flex; gap: 8px; padding: 0 1.5rem 1.5rem; }
        .modal-btn-close {
          flex: 1; padding: 11px; border: 2px solid #e2e8f0; border-radius: 10px;
          background: white; font-size: 13px; font-weight: 700;
          font-family: "Outfit", sans-serif; cursor: pointer; color: #64748b; transition: all .2s;
        }
        .modal-btn-close:hover { background: #f8fafc; }
        .modal-btn-cancel-booking {
          flex: 1; padding: 11px; border: none; border-radius: 10px;
          background: linear-gradient(135deg,#ef4444,#dc2626); color: white;
          font-size: 13px; font-weight: 700; font-family: "Outfit", sans-serif;
          cursor: pointer; box-shadow: 0 4px 14px rgba(239,68,68,.3); transition: all .2s;
        }
        .modal-btn-cancel-booking:hover { transform: translateY(-1px); }
        .modal-btn-cancel-booking:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .modal-title    { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 6px; font-family: "Outfit",sans-serif; }
        .modal-subtitle { font-size: 13px; color: #64748b; }
        .modal-actions  { display: flex; gap: 10px; margin-top: 20px; }
        .modal-btn-confirm { flex: 1; padding: 11px; border: none; border-radius: 10px; color: white; font-size: 13px; font-weight: 700; font-family: "Outfit",sans-serif; cursor: pointer; transition: all .2s; }
        .modal-btn-confirm:hover { transform: translateY(-1px); opacity: .9; }

        /* ── RESCHEDULE MODAL ── */
        .reschedule-modal-overlay {
          position: fixed; inset: 0;
          background: rgba(10,15,30,.75);
          backdrop-filter: blur(4px);
          display: flex; align-items: center; justify-content: center;
          z-index: 1100; opacity: 0; pointer-events: none;
          transition: opacity .3s ease; padding: 20px;
        }
        .reschedule-modal-overlay.visible { opacity: 1; pointer-events: all; }
        .reschedule-modal-box {
          background: white; border-radius: 22px;
          padding: 0; max-width: 440px; width: 100%;
          box-shadow: 0 40px 100px rgba(0,0,0,.28);
          transform: scale(.9) translateY(20px); opacity: 0;
          transition: transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
          overflow: hidden;
        }
        .reschedule-modal-overlay.visible .reschedule-modal-box { transform: scale(1) translateY(0); opacity: 1; }
        .reschedule-modal-header {
          background: linear-gradient(135deg, #f59e0b, #d97706);
          padding: 1.25rem 1.5rem;
          display: flex; align-items: center; gap: .75rem;
        }
        .reschedule-modal-header h3 { color: white; font-size: 1rem; font-weight: 800; margin: 0; }
        .reschedule-modal-header p { color: rgba(255,255,255,.8); font-size: .78rem; margin: 2px 0 0; }
        .reschedule-modal-body { padding: 1.5rem; }
        .reschedule-form-group { margin-bottom: 1rem; }
        .reschedule-form-group label { display: block; font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
        .reschedule-form-group input {
          width: 100%; padding: .7rem .9rem;
          border: 2px solid #e2e8f0; border-radius: 10px;
          font-size: .875rem; font-family: "Outfit", sans-serif;
          color: #0f172a; background: #f8fafc;
          transition: border-color .2s, box-shadow .2s;
          box-sizing: border-box;
        }
        .reschedule-form-group input:focus {
          outline: none; border-color: #f59e0b; background: white;
          box-shadow: 0 0 0 3px rgba(245,158,11,.12);
        }
        .reschedule-old-schedule {
          background: #fff7ed; border: 1px solid #fde68a;
          border-radius: 10px; padding: 10px 14px;
          font-size: .8rem; color: #92400e; margin-bottom: 1rem;
        }
        .reschedule-old-schedule strong { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .6px; color: #d97706; margin-bottom: 3px; }
        .reschedule-closed-days-hint {
          font-size: .75rem; color: #64748b; margin-bottom: 1rem;
          padding: 8px 12px; background: #f8fafc; border-radius: 8px;
          border: 1px solid #e2e8f0;
        }
        .reschedule-closed-days-hint strong { color: #374151; }
        .reschedule-policy-note {
          font-size: .75rem; color: #92400e; margin-bottom: 1rem;
          padding: 10px 14px; background: #fffbeb; border-radius: 10px;
          border: 1px solid #fde68a;
        }
        .reschedule-policy-note strong { display:block; color:#b45309; margin-bottom:6px; font-size:.78rem; }
        .reschedule-policy-note ul { margin:0; padding-left:1.1rem; display:flex; flex-direction:column; gap:3px; }
        .reschedule-policy-note li { line-height:1.35; }
        .reschedule-modal-footer { display: flex; gap: 8px; padding: 0 1.5rem 1.5rem; }
        .btn-reschedule-confirm {
          flex: 1; padding: 11px; border: none; border-radius: 10px;
          background: linear-gradient(135deg, #f59e0b, #d97706);
          color: white; font-size: 13px; font-weight: 700;
          font-family: "Outfit", sans-serif; cursor: pointer;
          box-shadow: 0 4px 14px rgba(245,158,11,.3); transition: all .2s;
        }
        .btn-reschedule-confirm:hover { transform: translateY(-1px); }
        .btn-reschedule-confirm:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-reschedule-cancel {
          flex: 1; padding: 11px; border: 2px solid #e2e8f0;
          border-radius: 10px; background: white; font-size: 13px;
          font-weight: 700; font-family: "Outfit", sans-serif;
          cursor: pointer; color: #64748b; transition: all .2s;
        }
        .btn-reschedule-cancel:hover { background: #f8fafc; }

        .dashboard-footer { text-align: center; padding: 16px 24px; font-size: 11px; color: #94a3b8; letter-spacing: .5px; font-family: "Outfit",sans-serif; font-weight: 500; border-top: 1px solid #e2e8f0; margin-top: auto; }

        @media (max-width: 768px) {
          .bc-body { grid-template-columns: 1fr 1fr; }
          .detail-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
          .bc-body { grid-template-columns: 1fr; }
          .approval-tabs { gap: 5px; }
          .tab-btn { padding: 6px 12px; font-size: .76rem; }
        }
        @media (max-width: 768px) {
  .bc-body { grid-template-columns: 1fr 1fr; }
  .detail-grid { grid-template-columns: 1fr; }
  
  /* ← ADD NI */
  .approval-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
  }
  .tab-btn { flex-shrink: 0; }
}
@media (max-width: 768px) {
  .bc-footer {
    gap: 5px;
  }

  .action-btn {
    padding: 7px 10px;
    font-size: .72rem;
  }

  .bc-date-label {
    width: 100%;
    text-align: right;
    margin-left: 0;
    margin-top: 2px;
  }
}
/* ── CANCEL CONFIRM MODAL ── */
.cancel-modal-overlay {
  position:fixed; inset:0; background:rgba(10,15,30,.75);
  backdrop-filter:blur(4px); display:flex; align-items:center;
  justify-content:center; z-index:1200; opacity:0;
  pointer-events:none; transition:opacity .3s ease; padding:20px;
}
.cancel-modal-overlay.visible { opacity:1; pointer-events:all; }
.cancel-modal-box {
  background:white; border-radius:22px; padding:0;
  max-width:380px; width:100%; overflow:hidden;
  box-shadow:0 40px 100px rgba(0,0,0,.28);
  transform:scale(.9) translateY(20px); opacity:0;
  transition:transform .35s cubic-bezier(0.34,1.56,.64,1), opacity .3s ease;
}
.cancel-modal-overlay.visible .cancel-modal-box {
  transform:scale(1) translateY(0); opacity:1;
}
.cancel-modal-header {
  background:linear-gradient(135deg,#ef4444,#dc2626);
  padding:1.5rem; text-align:center;
}
.cancel-modal-icon {
  display: flex;
  justify-content: center;
  margin-bottom: 8px;
}
.cancel-modal-title {
  font-size:1.1rem; font-weight:800; color:white;
  font-family:"Outfit",sans-serif;
}
.cancel-modal-body { padding:1.25rem 1.5rem 1.5rem; text-align:center; }
.cancel-modal-msg {
  font-size:.875rem; color:#475569; line-height:1.6;
  margin-bottom:1.25rem;
}
.cancel-modal-msg strong { color:#0f172a; }
.cancel-modal-actions { display:flex; gap:10px; }
.cancel-modal-btn-no {
  flex:1; padding:11px; border:2px solid #e2e8f0;
  border-radius:10px; background:white; font-size:.875rem;
  font-weight:700; font-family:"Outfit",sans-serif;
  cursor:pointer; color:#64748b; transition:all .2s;
}
.cancel-modal-btn-no:hover { background:#f8fafc; }
.cancel-modal-btn-yes {
  flex:1; padding:11px; border:none; border-radius:10px;
  background:linear-gradient(135deg,#ef4444,#dc2626);
  color:white; font-size:.875rem; font-weight:700;
  font-family:"Outfit",sans-serif; cursor:pointer;
  box-shadow:0 4px 14px rgba(239,68,68,.3); transition:all .2s;
}
.cancel-modal-btn-yes:hover { transform:translateY(-1px); }
.cancel-modal-btn-yes:disabled { opacity:.6; cursor:not-allowed; transform:none; }

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
            <a href="my-bookings.php" class="nav-item active"><span class="nav-icon"><img src="../assets/icons/book.svg" alt="" /></span><span class="nav-text">My Bookings</span></a>
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
      <button onclick="closeProfileModal();openAccountModal();" style="width:100%;margin-top:16px;padding:11px;background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0;border-radius:10px;font-size:.85rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;">
        ⚙ Account Settings
      </button>
      <button onclick="confirmLogout(event)" style="width:100%;margin-top:10px;padding:11px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:10px;font-size:.85rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;">
        Logout
      </button>
    </div>
  </div>
</div>

<style>
  .acct-tab { flex:1; padding:9px; background:var(--canvas,#f1f5f9); border:none; border-radius:10px 10px 0 0; font-size:.8rem; font-weight:700; color:var(--text-muted,#64748b); cursor:pointer; font-family:var(--font,'Outfit',sans-serif); }
  .acct-tab-active { background:#fff; color:var(--text-primary,#0f172a); box-shadow:inset 0 -2px 0 #2563eb; }
  .acct-field { margin-bottom:14px; }
  .acct-field label { display:block; font-size:.72rem; font-weight:700; color:var(--text-muted,#64748b); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
  .acct-field input { width:100%; padding:10px 12px; border:1px solid var(--border,#e2e8f0); border-radius:10px; font-size:.88rem; font-family:var(--font,'Outfit',sans-serif); box-sizing:border-box; }
  .acct-field input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
  .acct-msg { display:none; padding:9px 12px; border-radius:8px; font-size:.78rem; font-weight:600; margin-bottom:12px; }
  .acct-submit { width:100%; padding:11px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:10px; font-size:.85rem; font-weight:700; font-family:var(--font,'Outfit',sans-serif); cursor:pointer; }
  .acct-submit:disabled { opacity:.6; cursor:not-allowed; }
</style>

<!-- ════════════════ ACCOUNT SETTINGS MODAL ════════════════ -->
<div class="modal-overlay" id="accountModal">
  <div class="modal-box" style="max-width:420px;padding:0;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border,#e2e8f0);">
      <span style="font-size:1.05rem;font-weight:800;color:var(--text-primary,#0f172a);font-family:var(--font,'Outfit',sans-serif);">Account Settings</span>
      <button onclick="closeAccountModal()" style="background:var(--canvas,#f1f5f9);border:none;width:28px;height:28px;border-radius:8px;cursor:pointer;font-size:14px;color:var(--text-muted,#64748b);">✕</button>
    </div>
    <div style="display:flex;gap:6px;padding:12px 22px 0;">
      <button id="acctTabProfile" class="acct-tab acct-tab-active" onclick="acctSwitch('profile')">Edit Profile</button>
      <button id="acctTabPass" class="acct-tab" onclick="acctSwitch('password')">Change Password</button>
    </div>
    <form id="acctProfileForm" onsubmit="return saveProfile(event)" style="padding:18px 22px 22px;">
      <div class="acct-field"><label>Full Name</label><input type="text" id="acctName" required /></div>
      <div class="acct-field"><label>Email</label><input type="email" id="acctEmail" required /></div>
      <div class="acct-field"><label>Contact Number</label><input type="text" id="acctContact" /></div>
      <div id="acctProfileMsg" class="acct-msg"></div>
      <button type="submit" class="acct-submit">Save Changes</button>
    </form>
    <form id="acctPassForm" onsubmit="return savePassword(event)" style="padding:18px 22px 22px;display:none;">
      <div class="acct-field"><label>Current Password</label><input type="password" id="acctCurrent" required /></div>
      <div class="acct-field"><label>New Password</label><input type="password" id="acctNew" required /></div>
      <div class="acct-field"><label>Confirm New Password</label><input type="password" id="acctConfirm" required /></div>
      <div id="acctPassMsg" class="acct-msg"></div>
      <button type="submit" class="acct-submit">Update Password</button>
    </form>
  </div>
</div>
      

      <!-- Booking Detail Modal -->
      <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
          <div class="modal-banner" id="modalBanner"></div>
          <div class="modal-body">
            <div id="modalStepper"></div>
            <div class="modal-status-row">
              <span id="modalStatusBadge" class="status-badge"></span>
              <span id="modalBookingId" style="font-size:.72rem;color:#94a3b8;"></span>
            </div>
            <div class="modal-section-title">Service & Device</div>
            <div class="detail-grid" id="modalDetailGrid"></div>
            <div class="modal-section-title">Problem Description</div>
            <div class="problem-box" id="modalProblem"></div>
            <div class="modal-section-title">Schedule</div>
            <div class="detail-grid" id="modalScheduleGrid"></div>
          </div>
          <div class="modal-footer" id="modalFooter"></div>
        </div>
      </div>

      <!-- Reschedule Modal -->
      <div class="reschedule-modal-overlay" id="rescheduleModal">
        <div class="reschedule-modal-box">
          <div class="reschedule-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width:1.8rem;height:1.8rem;">
              <path fill="rgb(255,255,255)" d="M128 0c17.7 0 32 14.3 32 32l0 32 128 0 0-32c0-17.7 14.3-32 32-32s32 14.3 32 32l0 32 32 0c35.3 0 64 28.7 64 64l0 288c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 128C0 92.7 28.7 64 64 64l32 0 0-32c0-17.7 14.3-32 32-32zM64 240l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm128 0l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0zM64 368l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0zm112 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16z"/>
            </svg>
            <div>
              <h3>Reschedule Booking</h3>
              <p id="rescheduleShopName">Select a new date and time</p>
            </div>
          </div>
          <div class="reschedule-modal-body">
            <div class="reschedule-old-schedule">
              <strong>Current Schedule</strong>
              <span id="rescheduleOldSchedule">—</span>
            </div>
            <!-- FIX: closed-days hint shown dynamically -->
            <div class="reschedule-closed-days-hint" id="rescheduleClosedHint" style="display:none;">
              <strong>🗓 Shop open days:</strong> <span id="rescheduleOpenDaysList"></span>
            </div>
            <div class="reschedule-policy-note">
              <strong>ℹ️ Rescheduling Policy</strong>
              <ul>
                <li>Only <b>Pending</b> or <b>Confirmed</b> bookings can be rescheduled.</li>
                <li>Must be done at least <b>24 hours</b> before your current appointment.</li>
                <li>New time must fall within the shop's <b>operating hours</b>.</li>
                <li>Rescheduling resets your booking to <b>Pending</b> for the shop to re-confirm.</li>
              </ul>
            </div>
            <div class="reschedule-form-group">
              <label for="rescheduleDate">New Date *</label>
              <input type="date" id="rescheduleDate" required />
            </div>
            <div class="reschedule-form-group">
              <label for="rescheduleTime">New Time *</label>
              <input type="time" id="rescheduleTime" required />
            </div>
          </div>
          <div class="reschedule-modal-footer">
            <button class="btn-reschedule-cancel" onclick="closeRescheduleModal()">Cancel</button>
            <button class="btn-reschedule-confirm" id="rescheduleConfirmBtn" onclick="submitReschedule()">
              ✓ Confirm Reschedule
            </button>
          </div>
        </div>
      </div>

      <!-- Cancel Confirm Modal -->
<div class="cancel-modal-overlay" id="cancelModal">
  <div class="cancel-modal-box">
    <div class="cancel-modal-header">
      <div class="cancel-modal-icon">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="48" height="48">
    <path fill="rgb(255, 255, 255)" d="M528 320C528 205.1 434.9 112 320 112C205.1 112 112 205.1 112 320C112 434.9 205.1 528 320 528C434.9 528 528 434.9 528 320zM64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320zM398.7 448.6C383.7 433 357.6 416 320 416C282.4 416 256.3 433 241.3 448.6C232.1 458.2 216.9 458.5 207.4 449.3C197.9 440.1 197.5 424.9 206.7 415.4C228.8 392.4 266.7 368 320 368C373.3 368 411.2 392.4 433.3 415.4C442.5 425 442.2 440.2 432.6 449.3C423 458.4 407.8 458.2 398.7 448.6zM208 272C208 254.3 222.3 240 240 240C257.7 240 272 254.3 272 272C272 289.7 257.7 304 240 304C222.3 304 208 289.7 208 272zM400 240C417.7 240 432 254.3 432 272C432 289.7 417.7 304 400 304C382.3 304 368 289.7 368 272C368 254.3 382.3 240 400 240z"/>
  </svg>
</div>
      <div class="cancel-modal-title">Cancel Booking?</div>
    </div>
    <div class="cancel-modal-body">
      <div class="cancel-modal-msg">
        Are you sure you want to cancel your booking at<br>
        <strong id="cancelShopName"></strong>?<br><br>
        <span style="font-size:.8rem;color:#94a3b8;">This action cannot be undone.</span>
      </div>
      <div class="cancel-modal-actions">
        <button class="cancel-modal-btn-no" onclick="closeCancelModal()">Keep Booking</button>
        <button class="cancel-modal-btn-yes" id="cancelConfirmBtn" onclick="confirmCancelBooking()">Yes, Cancel</button>
      </div>
    </div>
  </div>
</div>

      <main class="main-content">
        <header class="top-bar">
          <div class="page-header"><h1 class="current-page-title">My Bookings</h1></div>
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
                <span class="user-name" data-acct-name><?php echo htmlspecialchars($userName); ?></span>
                <span class="user-role">Customer</span>
              </div>
            </div>
          </div>
        </header>

        <div class="dashboard-content">
          <!-- Tabs -->
          <div class="approval-tabs">
            <button class="tab-btn active" data-status="all">All</button>
            <button class="tab-btn" data-status="pending">Pending</button>
            <button class="tab-btn" data-status="confirmed">Confirmed</button>
            <button class="tab-btn" data-status="completed">Completed</button>
            <button class="tab-btn" data-status="paid">Paid</button>
            <button class="tab-btn" data-status="claimed">Claimed</button>
            <button class="tab-btn" data-status="cancelled">Cancelled</button>
          </div>

          <!-- Loading -->
          <div class="loading-state" id="loadingState">
            <div class="spinner"></div>
            <p>Loading your bookings...</p>
          </div>

          <!-- Cards -->
          <div id="bookingsGrid" style="display:none;"></div>

          <!-- Empty -->
          <div class="empty-state" id="emptyState" style="display:none;">
            <img src="../assets/icons/book.svg" alt="No bookings" />
            <h3>No Bookings Yet</h3>
            <p>You haven't made any bookings yet.<br>Find a repair shop to get started!</p>
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
        let allBookings   = [];
        let currentFilter = 'all';

        // ── Load bookings ────────────────────────────────────────
        async function loadBookings() {
          try {
            const res  = await fetch('../api/get_my_bookings.php');
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            allBookings = data.bookings || [];
            updateTabCounts();
            renderBookings();
          } catch(e) {
            document.getElementById('loadingState').innerHTML = `<p style="color:#ef4444;">Failed to load bookings. Please refresh.</p>`;
            console.error(e);
          }
        }

        // ── Count & update tabs ──────────────────────────────────
        function updateTabCounts() {
          const counts = { all: allBookings.length, pending:0, confirmed:0, completed:0, paid:0, claimed:0, cancelled:0, no_show:0 };
          allBookings.forEach(b => { if (counts[b.status] !== undefined) counts[b.status]++; });
          document.querySelectorAll('.tab-btn').forEach(btn => {
            const s = btn.dataset.status;
            btn.textContent = s.charAt(0).toUpperCase() + s.slice(1) + ` (${counts[s] ?? 0})`;
          });
        }

        // ── Render ───────────────────────────────────────────────
        function renderBookings() {
          document.getElementById('loadingState').style.display = 'none';
          const grid  = document.getElementById('bookingsGrid');
          const empty = document.getElementById('emptyState');

          const filtered = currentFilter === 'all'
            ? [...allBookings].sort((a, b) =>
                new Date(b.created_at || `${b.booking_date} ${b.booking_time}`) -
                new Date(a.created_at || `${a.booking_date} ${a.booking_time}`))
            : allBookings.filter(b => b.status === currentFilter);

          if (!filtered.length) {
            grid.style.display  = 'none';
            empty.style.display = 'flex';
            if (currentFilter !== 'all') {
              document.querySelector('#emptyState h3').textContent = `No ${currentFilter.charAt(0).toUpperCase()+currentFilter.slice(1)} Bookings`;
              document.querySelector('#emptyState p').innerHTML = `You don't have any ${currentFilter} bookings.`;
            } else {
              document.querySelector('#emptyState h3').textContent = 'No Bookings Yet';
              document.querySelector('#emptyState p').innerHTML = "You haven't made any bookings yet.<br>Find a repair shop to get started!";
            }
            return;
          }

          empty.style.display = 'none';
          grid.style.display  = 'flex';

          grid.innerHTML = filtered.map((b, i) => {
            const shopLogo = b.shop_logo
              ? b.shop_logo
              : `https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
            const dateStr    = fmtDate(b.booking_date);
            const timeStr    = fmtTime(b.booking_time);
            const createdStr = fmtDatetime(b.created_at);
            const canCancel  = b.status === 'pending' || b.status === 'confirmed';

            return `
              <div class="booking-card" style="animation-delay:${i * 0.05}s" data-status="${esc(b.status)}">
                <!-- Header -->
                <div class="bc-header">
                  <div class="bc-shop-info">
                    <img src="${shopLogo}" class="bc-shop-logo" alt="${esc(b.shop_name)}"
                      onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80'" />
                    <div>
                      <div class="bc-shop-name">${esc(b.shop_name || 'Repair Shop')}</div>
                      ${b.shop_location ? `<div class="bc-shop-loc"><img src="../assets/icons/location.svg" width="13" height="13" alt="" style="opacity:.6;" /> ${esc(b.shop_location)}</div>` : ''}
                    </div>
                  </div>
                  <span class="status-badge status-${esc(b.status)}">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>
                </div>

                <!-- Body -->
                <div class="bc-body">
                  <div class="bc-detail">
                    <span class="bc-label">Service</span>
                    <span class="bc-value service">${esc(b.service_name || '—')}</span>
                  </div>
                  <div class="bc-detail">
                    <span class="bc-label">Device</span>
                    <span class="bc-value">${esc(b.device_type)}${b.device_brand ? ' · '+esc(b.device_brand) : ''}</span>
                  </div>
                  <div class="bc-detail">
                    <span class="bc-label">Schedule</span>
                    <span class="bc-value">${dateStr} at ${timeStr}</span>
                  </div>
                </div>

                <!-- Problem -->
                ${b.problem_description ? `
                <div class="bc-problem">
                  <strong>Problem:</strong> ${esc(b.problem_description)}
                </div>` : ''}

                <!-- Footer -->
                <div class="bc-footer">
                  <button class="action-btn btn-view" onclick='openDetail(${JSON.stringify(b)})'>
                    <img src="../assets/icons/view.svg" alt="" /> View Details
                  </button>
                  ${canCancel ? `
                  <button class="action-btn btn-reschedule" onclick='openRescheduleModal(${b.id}, ${b.shop_id}, "${esc(b.shop_name)}", "${b.booking_date}", "${b.booking_time}")'>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width:1em;height:1em;vertical-align:middle;"><path fill="currentColor" d="M128 0c17.7 0 32 14.3 32 32l0 32 128 0 0-32c0-17.7 14.3-32 32-32s32 14.3 32 32l0 32 32 0c35.3 0 64 28.7 64 64l0 288c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 128C0 92.7 28.7 64 64 64l32 0 0-32c0-17.7 14.3-32 32-32zM64 240l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm128 0l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0zM64 368l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0zm112 16l0 32c0 8.8 7.2 16 16 16l32 0c8.8 0 16-7.2 16-16l0-32c0-8.8-7.2-16-16-16l-32 0c-8.8 0-16 7.2-16 16z"/></svg>
                    Reschedule
                  </button>
                  <button class="action-btn btn-cancel" onclick="cancelBooking(${b.id}, this)">
                    <img src="../assets/icons/xmark.svg" alt="" /> Cancel
                  </button>` : ''}
                  <span class="bc-date-label">Booked ${createdStr}</span>
                </div>
              </div>`;
          }).join('');
        }

        // ── Tabs ─────────────────────────────────────────────────
        document.querySelectorAll('.tab-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.status;
            renderBookings();
          });
        });

        // ── Cancel booking ───────────────────────────────────────
        let cancelBookingId = null;
let cancelBookingBtn = null;

function cancelBooking(id, btn) {
  const card = btn.closest('.booking-card');
  const shopName = card.querySelector('.bc-shop-name')?.textContent || 'this shop';
  cancelBookingId = id;
  cancelBookingBtn = btn;
  document.getElementById('cancelShopName').textContent = shopName;
  document.getElementById('cancelModal').classList.add('visible');
}

function closeCancelModal() {
  document.getElementById('cancelModal').classList.remove('visible');
  cancelBookingId = null;
  cancelBookingBtn = null;
}

async function confirmCancelBooking() {
  if (!cancelBookingId) return;
  const btn = document.getElementById('cancelConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Cancelling...';
  try {
    const fd = new FormData();
    fd.append('booking_id', cancelBookingId);
    fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    const res  = await fetch('cancel_booking.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      closeCancelModal();
      loadBookings();
    } else {
      alert('Error: ' + (data.error || 'Failed to cancel.'));
    }
  } catch(e) {
    alert('Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Yes, Cancel';
  }
}

// Close when clicking outside
document.getElementById('cancelModal').addEventListener('click', function(e) {
  if (e.target === this) closeCancelModal();
});

        // ── Detail modal ─────────────────────────────────────────
        const detailModal = document.getElementById('detailModal');
        const STATUS_BG = {
          pending:'linear-gradient(135deg,#f59e0b,#d97706)',
          confirmed:'linear-gradient(135deg,#10b981,#059669)',
          completed:'linear-gradient(135deg,#3b82f6,#2563eb)',
          cancelled:'linear-gradient(135deg,#ef4444,#dc2626)',
          no_show:'linear-gradient(135deg,#8b5cf6,#7c3aed)',
          paid:'linear-gradient(135deg,#14b8a6,#0d9488)',
          claimed:'linear-gradient(135deg,#6366f1,#4f46e5)'
        };

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

          const order = ['pending', 'confirmed', 'completed', 'paid', 'claimed'];
          const labels = { pending: 'Pending', confirmed: 'Confirmed', completed: 'Completed', paid: 'Paid', claimed: 'Claimed' };
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

        function openDetail(b) {
          const shopLogo = b.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
          document.getElementById('modalBanner').style.background = STATUS_BG[b.status] || '#f59e0b';
          document.getElementById('modalBanner').innerHTML = `
            <img src="${shopLogo}" class="modal-shop-logo" alt="${esc(b.shop_name)}"
              onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(b.shop_name||'Shop')}&background=f59e0b&color=fff&size=80'" />
            <div>
              <div class="modal-shop-name">${esc(b.shop_name || 'Repair Shop')}</div>
              <div class="modal-booking-id">Booking #${b.id}</div>
            </div>`;
          document.getElementById('modalStepper').innerHTML = renderStepper(b.status);
          document.getElementById('modalStatusBadge').className = `status-badge status-${b.status}`;
          document.getElementById('modalStatusBadge').textContent = b.status.charAt(0).toUpperCase() + b.status.slice(1);
          document.getElementById('modalBookingId').textContent = `Submitted: ${fmtDatetime(b.created_at)}`;
          document.getElementById('modalDetailGrid').innerHTML = `
            <div class="detail-item"><div class="detail-item-label">Service</div><div class="detail-item-value" style="color:#d97706">${esc(b.service_name||'—')}</div></div>
            <div class="detail-item"><div class="detail-item-label">Device Type</div><div class="detail-item-value">${esc(b.device_type||'—')}</div></div>
            <div class="detail-item" style="grid-column:span 2"><div class="detail-item-label">Brand / Model</div><div class="detail-item-value">${esc(b.device_brand||'—')}</div></div>`;
          document.getElementById('modalProblem').textContent = b.problem_description || 'No description provided.';
          document.getElementById('modalScheduleGrid').innerHTML = `
            <div class="detail-item"><div class="detail-item-label">Date</div><div class="detail-item-value">${fmtDate(b.booking_date)}</div></div>
            <div class="detail-item"><div class="detail-item-label">Time</div><div class="detail-item-value">${fmtTime(b.booking_time)}</div></div>
            ${b.shop_contact ? `<div class="detail-item" style="grid-column:span 2"><div class="detail-item-label">Shop Contact</div><div class="detail-item-value">${esc(b.shop_contact)}</div></div>` : ''}`;
          const canCancel = b.status === 'pending' || b.status === 'confirmed';
          document.getElementById('modalFooter').innerHTML = `
            <button class="modal-btn-close" onclick="closeDetailModal()">Close</button>
            ${canCancel ? `<button class="modal-btn-cancel-booking" onclick="closeDetailModal();cancelBooking(${b.id},document.createElement('button'))">Cancel Booking</button>` : ''}`;
          detailModal.classList.add('visible');
        }

        function closeDetailModal() { detailModal.classList.remove('visible'); }
        detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetailModal(); });

        // ── Reschedule Modal ──────────────────────────────────────
        let rescheduleBookingId = null;
        let shopOpenDays        = []; // e.g. ['monday','tuesday',...]

        const DAY_NAMES = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];

        // FIX ①: shared helper — checks if a date string falls on an open day
        function isShopOpenOnDate(dateStr) {
          if (!shopOpenDays.length) return true; // no hours configured → no restriction
          const d       = new Date(dateStr + 'T00:00:00');
          const dayName = DAY_NAMES[d.getDay()];
          return shopOpenDays.includes(dayName);
        }

        // Capitalise first letter of a day name for display
        function capDay(d) { return d.charAt(0).toUpperCase() + d.slice(1); }

        async function openRescheduleModal(bookingId, shopId, shopName, oldDate, oldTime) {
          // ── Rescheduling policy: block within 24 hours of the current appointment ──
          const apptTs = new Date(oldDate + 'T' + (oldTime.length === 5 ? oldTime + ':00' : oldTime)).getTime();
          if (!isNaN(apptTs) && (apptTs - Date.now()) < 86400000) {
            alert('Rescheduling is only allowed at least 24 hours before your appointment. Please contact the shop directly.');
            return;
          }

          rescheduleBookingId = bookingId;
          document.getElementById('rescheduleShopName').textContent = shopName;

          const oldDateFmt = new Date(oldDate + 'T00:00:00').toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
          const oldTimeFmt = fmtTime(oldTime);
          document.getElementById('rescheduleOldSchedule').textContent = `${oldDateFmt} at ${oldTimeFmt}`;

          // Reset fields
          const dateInput = document.getElementById('rescheduleDate');
          dateInput.value = '';
          document.getElementById('rescheduleTime').value = '';

          // Set min = tomorrow
          const tomorrow = new Date();
          tomorrow.setDate(tomorrow.getDate() + 1);
          dateInput.min = tomorrow.toISOString().split('T')[0];

          // Fetch open days for this shop
          try {
            const res  = await fetch(`get_shop_hours.php?shop_id=${shopId}`);
            const data = await res.json();
            shopOpenDays = data.open_days || [];
          } catch(e) {
            shopOpenDays = [];
          }

          // Show open-days hint inside modal
          const hint     = document.getElementById('rescheduleClosedHint');
          const daysList = document.getElementById('rescheduleOpenDaysList');
          if (shopOpenDays.length) {
            daysList.textContent = shopOpenDays.map(capDay).join(', ');
            hint.style.display   = 'block';
          } else {
            hint.style.display   = 'none';
          }

          // FIX ②: use onchange (more reliable than oninput for date pickers)
          dateInput.onchange = function() {
            if (!this.value) return;
            if (!isShopOpenOnDate(this.value)) {
              const day = DAY_NAMES[new Date(this.value + 'T00:00:00').getDay()];
              alert(`This shop is closed on ${capDay(day)}s. Please pick another day.\n\nOpen days: ${shopOpenDays.map(capDay).join(', ')}`);
              this.value = '';
            }
          };

          document.getElementById('rescheduleModal').classList.add('visible');
        }

        function closeRescheduleModal() {
          document.getElementById('rescheduleModal').classList.remove('visible');
          rescheduleBookingId = null;
        }

        async function submitReschedule() {
          const newDate = document.getElementById('rescheduleDate').value;
          const newTime = document.getElementById('rescheduleTime').value;

          if (!newDate || !newTime) { alert('Please select both a new date and time.'); return; }

          // FIX ③: final guard before submitting — blocks closed-day submissions even if UI check was bypassed
          if (!isShopOpenOnDate(newDate)) {
            const day = DAY_NAMES[new Date(newDate + 'T00:00:00').getDay()];
            alert(`This shop is closed on ${capDay(day)}s. Please pick another date.\n\nOpen days: ${shopOpenDays.map(capDay).join(', ')}`);
            return;
          }

          const btn = document.getElementById('rescheduleConfirmBtn');
          btn.disabled    = true;
          btn.textContent = 'Rescheduling...';

          try {
            const fd = new FormData();
            fd.append('booking_id', rescheduleBookingId);
            fd.append('new_date',   newDate);
            fd.append('new_time',   newTime);
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const res  = await fetch('reschedule_booking.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
              closeRescheduleModal();
              loadBookings();
              const toast = document.createElement('div');
              toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#10b981;color:white;padding:12px 24px;border-radius:12px;font-weight:700;font-size:.875rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);font-family:Outfit,sans-serif;';
              toast.textContent = data.email_sent ? '✓ Rescheduled! Shop has been notified via email.' : '✓ Booking rescheduled successfully!';
              document.body.appendChild(toast);
              setTimeout(() => toast.remove(), 3500);
            } else {
              alert('Error: ' + (data.error || 'Failed to reschedule.'));
            }
          } catch(e) {
            alert('Network error. Please try again.');
          } finally {
            btn.disabled    = false;
            btn.textContent = '✓ Confirm Reschedule';
          }
        }

        document.getElementById('rescheduleModal').addEventListener('click', function(e) {
          if (e.target === this) closeRescheduleModal();
        });

        // ── Helpers ──────────────────────────────────────────────
        function esc(s) {
          return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function fmtDate(d) {
          if (!d) return '—';
          return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
        }
        function fmtTime(t) {
          if (!t) return '—';
          const [h, m] = t.split(':');
          const hr = parseInt(h); const ampm = hr >= 12 ? 'PM' : 'AM';
          return `${hr % 12 || 12}:${m} ${ampm}`;
        }
        function fmtDatetime(dt) {
          if (!dt) return '—';
          return new Date(dt).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
        }

        // ── Init ─────────────────────────────────────────────────
        loadBookings();

        // ── Notifications ────────────────────────────────────────
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
            if (!data.notifications.length) {
              list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
              return;
            }
            const STATUS_MSG = {
              confirmed:    (shop)         => `<span>${shop}</span> confirmed your booking! 🎉`,
              completed:    (shop)         => `Your repair at <span>${shop}</span> is complete! ✅`,
              paid:         (shop)         => `Payment confirmed by <span>${shop}</span>. Ready for pickup! 💰`,
              claimed:      (shop)         => `You claimed your device from <span>${shop}</span>! 🎉`,
              no_show:      (shop)         => `<span>${shop}</span> marked your booking as no-show.`,
              cancelled:    (shop)         => `<span>${shop}</span> cancelled your booking.`,
              review_reply: (shop, reply)  => `<span style="font-weight:800;color:#d97706;">${shop}:</span> ${reply}`,
              message:      (shop)         => `<span style="font-weight:800;color:#d97706;">${shop}</span> sent you a message 💬`,
            };
            list.innerHTML = data.notifications.map(n => {
              const logo = n.shop_logo || `https://ui-avatars.com/api/?name=${encodeURIComponent(n.shop_name||'Shop')}&background=f59e0b&color=fff&size=80`;
              const msg  = STATUS_MSG[n.status]
                ? STATUS_MSG[n.status](n.shop_name || 'Shop', n.reply || '')
                : `<span>${n.shop_name || 'Shop'}:</span> ${n.reply || n.status}`;
              const time = n.time ? new Date(n.time).toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' }) : '';
              const dest = n.status === 'message' ? ('messages.php' + (n.other_id ? '?open=' + n.other_id : '')) : 'my-bookings.php';
              return `
                <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="window.location.href='${dest}'">
                  <img src="${logo}" class="notif-logo" alt=""
                    onerror="this.src='https://ui-avatars.com/api/?name=Shop&background=f59e0b&color=fff&size=80'" />
                  <div class="notif-content">
                    <div class="notif-message">${msg}</div>
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
          if (notifOpen) { loadNotifications(); markAllRead(); }
        }

        async function markAllRead() {
          await fetch('../api/get_notifications.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ mark_read: true })
          });
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
        
      </script>
       <script>
setTimeout(function () {
    window.location.href = "../login.php?timeout=1";
}, 1800000); // 30 minutes

// Live copy of the name so edits reflect without a reload
let CURRENT_NAME = <?php echo json_encode($userName); ?>;
function applyName(name) {
  CURRENT_NAME = name;
  document.querySelectorAll('[data-acct-name]').forEach(el => el.textContent = name);
  document.querySelectorAll('img.user-avatar').forEach(img => {
    img.alt = name;
    if (img.src.includes('ui-avatars.com'))
      img.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=2563eb&color=fff`;
  });
  const initials = document.getElementById('profileInitials');
  if (initials && !initials.querySelector('img'))
    initials.textContent = name.trim().slice(0, 2).toUpperCase();
  const bookNameInput = document.getElementById('customerName');
  if (bookNameInput) bookNameInput.value = name;
}

function openProfileModal() {
  const serverPic = <?php echo json_encode($userProfilePic); ?>;
  const saved = serverPic || localStorage.getItem('profilePic_<?php echo $userId; ?>');
  const avatarEl = document.getElementById('profileInitials');
  if (saved) {
    avatarEl.innerHTML = `<img src="${saved}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" />`;
  } else {
    avatarEl.textContent = CURRENT_NAME.trim().slice(0, 2).toUpperCase();
    avatarEl.style.background = 'linear-gradient(135deg,#ff6b35,#ef4444)';
  }
  document.getElementById('profileName').textContent = CURRENT_NAME;
  document.getElementById('profileModal').classList.add('visible');
}
function closeProfileModal() {
  document.getElementById('profileModal').classList.remove('visible');
}
document.getElementById('profileModal').addEventListener('click', function(e) {
  if (e.target === this) closeProfileModal();
});

// ── Account Settings (edit profile + change password) ──
(function(){
  const CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
  const INIT = { name: <?php echo json_encode($userName); ?>, email: <?php echo json_encode($userEmail); ?>, contact: <?php echo json_encode($userContact); ?> };
  function acctMsg(id, text, ok){ const el=document.getElementById(id); if(!text){el.style.display='none';return;} el.style.display='block'; el.textContent=text; el.style.background=ok?'#d1fae5':'#fee2e2'; el.style.color=ok?'#065f46':'#991b1b'; }
  window.acctSwitch = function(which){ const p=which==='profile'; document.getElementById('acctProfileForm').style.display=p?'block':'none'; document.getElementById('acctPassForm').style.display=p?'none':'block'; document.getElementById('acctTabProfile').classList.toggle('acct-tab-active',p); document.getElementById('acctTabPass').classList.toggle('acct-tab-active',!p); };
  window.openAccountModal = function(){ document.getElementById('acctName').value=INIT.name||''; document.getElementById('acctEmail').value=INIT.email||''; document.getElementById('acctContact').value=INIT.contact||''; document.getElementById('acctCurrent').value=''; document.getElementById('acctNew').value=''; document.getElementById('acctConfirm').value=''; acctMsg('acctProfileMsg',''); acctMsg('acctPassMsg',''); acctSwitch('profile'); document.getElementById('accountModal').classList.add('visible'); };
  window.closeAccountModal = function(){ document.getElementById('accountModal').classList.remove('visible'); };
  async function post(payload){ const res=await fetch('../api/update_account.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF},payload))}); return res.json(); }
  window.saveProfile = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'update_profile',name:document.getElementById('acctName').value.trim(),email:document.getElementById('acctEmail').value.trim(),contact_number:document.getElementById('acctContact').value.trim()}); acctMsg('acctProfileMsg',d.message||d.error,!!d.success); if(d.success){INIT.name=d.name;INIT.email=d.email;applyName(d.name);} }catch(err){ acctMsg('acctProfileMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
  window.savePassword = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'change_password',current_password:document.getElementById('acctCurrent').value,new_password:document.getElementById('acctNew').value,confirm_password:document.getElementById('acctConfirm').value}); acctMsg('acctPassMsg',d.message||d.error,!!d.success); if(d.success){document.getElementById('acctCurrent').value='';document.getElementById('acctNew').value='';document.getElementById('acctConfirm').value='';} }catch(err){ acctMsg('acctPassMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
  const ov=document.getElementById('accountModal'); if(ov) ov.addEventListener('click',function(e){ if(e.target===this) closeAccountModal(); });
})();
// Shrink the photo in the browser first — a raw 2MB base64 blob blows past
// MySQL's default 1MB max_allowed_packet and the save silently fails.
function compressImage(file, maxSize = 480, quality = 0.82) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('Could not read that file.'));
    reader.onload = (e) => {
      const img = new Image();
      img.onerror = () => reject(new Error('That file is not a valid image.'));
      img.onload = () => {
        const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
        const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        resolve(canvas.toDataURL('image/jpeg', quality));
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

async function handlePicUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (!/^image\//.test(file.type)) { showPicStatus('Please pick an image file.', false); return; }
  if (file.size > 8 * 1024 * 1024) { showPicStatus('Image too large. Max 8MB.', false); return; }

  showPicStatus('Uploading...', null);

  let base64;
  try { base64 = await compressImage(file); }
  catch (err) { showPicStatus('❌ ' + err.message, false); return; }

  document.getElementById('profileInitials').innerHTML =
    `<img src="${base64}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" />`;
  const topAvatar = document.querySelector('.user-avatar');
  if (topAvatar) topAvatar.src = base64;
  try { localStorage.setItem('profilePic_<?php echo $userId; ?>', base64); } catch (e) {}

  try {
    const res  = await fetch('../api/update_profile_picture.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ image: base64 })
    });
    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); }
    catch (e) { showPicStatus('❌ Server error: ' + raw.replace(/<[^>]*>/g,' ').trim().slice(0,120), false); return; }
    if (data.success) showPicStatus('✓ Profile picture updated!', true);
    else showPicStatus('❌ ' + (data.error || 'Upload failed.'), false);
  } catch (err) { showPicStatus('❌ Network error. Not saved on the server.', false); }
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

<?php $chatbotApiPath = '../api/chatbot.php'; include __DIR__ . '/../includes/chatbot-widget.php'; ?>
    </body>
  </html>