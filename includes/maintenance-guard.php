<?php
/**
 * Maintenance mode.
 *
 * Two levels, both driven from admin/admin-settings.php:
 *   maintenance_mode = 1   → hard block. Non-admins get a maintenance page
 *                            (or a 503 JSON body on API endpoints).
 *   maintenance_until set  → soft notice. Everything works, but a dismissible
 *                            banner warns about the upcoming window.
 *
 * Admins always pass through so the switch can be turned back off.
 */

require_once __DIR__ . '/db.php';

function fixitMaintenanceGuard($conn, $wantsJson = false) {
    if (!$conn) return;
    if (($_SESSION['role'] ?? '') === 'admin') return;

    if (fixit_setting($conn, 'maintenance_mode', '0') === '1') {
        fixit_render_maintenance($conn, $wantsJson);
        exit();
    }

    // Soft notice — injected into the page body without touching any page file.
    $until = trim((string)fixit_setting($conn, 'maintenance_until', ''));
    if ($until !== '' && strtotime($until) > time() && !$wantsJson) {
        $GLOBALS['fixit_maintenance_until'] = $until;
        ob_start('fixit_inject_maintenance_banner');
    }
}

function fixit_render_maintenance($conn, $wantsJson) {
    $message = (string)fixit_setting(
        $conn,
        'maintenance_message',
        'We are performing scheduled maintenance. Please check back shortly.'
    );

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 1800');
    }

    if ($wantsJson) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['error' => $message, 'maintenance' => true]);
        return;
    }

    $safe = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $base = rtrim(fixit_maintenance_base(), '/') . '/';
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Under Maintenance - Fix It Davao</title>
  <link rel="icon" type="image/png" href="{$base}assets/images/logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
         font-family:'Outfit',system-ui,sans-serif;background:linear-gradient(160deg,#fffbeb,#f8fafc)}
    .card{max-width:520px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:20px;
          padding:40px 32px;text-align:center;box-shadow:0 24px 60px rgba(15,23,42,.10)}
    .logo{width:76px;height:76px;margin:0 auto 20px;border-radius:20px;display:flex;align-items:center;
          justify-content:center;background:linear-gradient(135deg,#f59e0b,#d97706);font-size:36px}
    h1{font-size:1.5rem;font-weight:800;color:#0f172a;margin-bottom:12px}
    p{font-size:.95rem;line-height:1.65;color:#475569}
    .foot{margin-top:26px;padding-top:18px;border-top:1px solid #f1f5f9;font-size:.78rem;color:#94a3b8}
    .retry{margin-top:22px;display:inline-block;background:linear-gradient(135deg,#f59e0b,#d97706);
           color:#fff;text-decoration:none;padding:11px 24px;border-radius:10px;font-weight:700;font-size:.88rem}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">🔧</div>
    <h1>We&rsquo;ll be right back</h1>
    <p>{$safe}</p>
    <a class="retry" href="javascript:location.reload()">Try again</a>
    <div class="foot">FIX IT DAVAO &middot; Thanks for your patience.</div>
  </div>
</body>
</html>
HTML;
}

/**
 * ob_start callback: drops the scheduled-maintenance banner in right after the
 * opening <body> tag. Leaves non-HTML responses untouched.
 */
function fixit_inject_maintenance_banner($buffer) {
    if (stripos($buffer, '<body') === false) return $buffer;

    $until = $GLOBALS['fixit_maintenance_until'] ?? '';
    $when  = date('M j, Y \a\t g:i A', strtotime($until));
    $when  = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');

    $banner = <<<HTML
<div id="fixitMaintBanner" style="position:sticky;top:0;z-index:2000;display:flex;align-items:center;
     gap:10px;padding:10px 16px;background:linear-gradient(135deg,#fef3c7,#fde68a);
     border-bottom:1px solid #fbbf24;font-family:'Outfit',system-ui,sans-serif;
     font-size:.82rem;font-weight:600;color:#92400e;">
  <span>&#9888;&#65039;</span>
  <span style="flex:1;">Scheduled maintenance on <strong>{$when}</strong>. The site may be briefly unavailable.</span>
  <button onclick="this.parentNode.remove()" style="background:none;border:none;cursor:pointer;
          font-size:1.1rem;line-height:1;color:#92400e;padding:0 4px;" aria-label="Dismiss">&times;</button>
</div>
HTML;

    return preg_replace('/(<body\b[^>]*>)/i', '$1' . $banner, $buffer, 1);
}

/** Project root URL, used for the logo on the standalone maintenance page. */
function fixit_maintenance_base() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    foreach (['/admin', '/customer', '/shop-owner', '/api', '/includes'] as $sub) {
        if (substr($dir, -strlen($sub)) === $sub) {
            $dir = substr($dir, 0, -strlen($sub));
            break;
        }
    }
    return $dir === '' ? '/' : $dir;
}
