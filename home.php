<?php
session_start();
require_once __DIR__ . '/includes/guard.php';

// ── Session timeout (30 mins) ──
$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'customer';

// ── Load the profile the customer set after login (DB name + picture) ──
$userProfilePic = null;
if ($isLoggedIn && !empty($_SESSION['user_id'])) {
    $conn = new mysqli("localhost", "root", "", "fixitdavao");
    if (!$conn->connect_error) {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $userName       = $row['name'] ?: $userName;
            $userProfilePic = $row['profile_picture'] ?? null;
        }
        $stmt->close();
        $conn->close();
    }
}
$avatarBg  = $userRole === 'repairshop' ? 'f59e0b' : '2563eb';
$avatarUrl = $userProfilePic ?: ('https://ui-avatars.com/api/?name=' . urlencode($userName) . "&background={$avatarBg}&color=fff");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Fix It Davao — Your Trusted Repair Portal</title>
<link rel="icon" type="image/png" href="assets/images/logo.png" />
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>

.hero-image{
    width:900%;
    max-width:500px;
    height:auto;
    display:block;
    margin-left:-60px;
}
.hero-image.slide{
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    object-fit:contain;
    opacity:0;
    transition:opacity 1.2s ease-in-out;
    pointer-events:none;
    margin-left:0;
}
.hero-image.slide.active{
    opacity:1;
    pointer-events:auto;
}

.hero-image.slide[data-slide="0"]{
    transform:scale(1.3) translateX(20px);
}

.hero-image.slide[data-slide="1"]{
    object-fit:cover;
    transform:scale(1.6) translateX(20px);
}

*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --primary:#0f172a;
  --primary-light:#1e293b;
  --primary-dark:#020617;
  --accent:#f59e0b;
  --accent-light:#fbbf24;
  --accent-dark:#d97706;
  --text-primary:#0f172a;
  --text-secondary:#64748b;
  --text-muted:#94a3b8;
  --border:#e2e8f0;
  --bg-primary:#f8fafc;
}
html{scroll-behavior:smooth;}
body{font-family:'Outfit',-apple-system,sans-serif;background:#fff;color:var(--text-primary);overflow-x:hidden;}

/* ── NAVBAR ── */
.navbar{
  position:fixed;top:0;left:0;right:0;z-index:999;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 5%;height:72px;
  background:rgba(2,6,23,0.92);
  backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(245,158,11,0.15);
  transition:all 0.3s;
}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo img{width:38px;height:38px;object-fit:contain;border-radius:8px;background:#fff;padding:3px;}
.nav-brand{font-size:18px;font-weight:800;letter-spacing:1px;color:#fff;}
.nav-brand span{color:var(--accent);}
.nav-links{display:flex;align-items:center;gap:32px;}
.nav-links a{color:rgba(255,255,255,0.75);text-decoration:none;font-size:14px;font-weight:500;transition:color 0.2s;}
.nav-links a:hover{color:var(--accent-light);}
.nav-cta{display:flex;align-items:center;gap:10px;}
.btn-login{padding:9px 20px;background:transparent;border:1.5px solid rgba(255,255,255,0.3);color:#fff;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:all 0.25s cubic-bezier(0.34,1.56,0.64,1);text-decoration:none;display:inline-block;}
.btn-login:hover{border-color:var(--accent);color:var(--accent);transform:rotate(-2deg);}

.btn-register{padding:9px 20px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:all 0.25s cubic-bezier(0.34,1.56,0.64,1);text-decoration:none;display:inline-block;}
.btn-register:hover{background:linear-gradient(135deg,var(--accent-dark),var(--accent-dark));transform:rotate(2deg);}

/* ── Logged-in profile chip ── */
.nav-profile{display:flex;align-items:center;gap:10px;padding:5px 14px 5px 5px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(245,158,11,0.25);border-radius:999px;text-decoration:none;transition:all 0.25s;}
.nav-profile:hover{border-color:var(--accent);background:rgba(245,158,11,0.1);}
.nav-profile-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#fff;flex-shrink:0;}
.nav-profile-name{color:#fff;font-size:14px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.nav-profile-caret{width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:6px solid rgba(255,255,255,0.7);margin-left:2px;transition:transform 0.25s;flex-shrink:0;}
.nav-profile-wrap{position:relative;}
.nav-profile-wrap.open .nav-profile-caret{transform:rotate(180deg);}
.nav-profile-menu{
  position:absolute;top:calc(100% + 10px);right:0;min-width:210px;
  background:#0f172a;
  border:1px solid rgba(245,158,11,0.2);
  border-radius:14px;
  box-shadow:0 16px 40px rgba(0,0,0,0.4);
  padding:6px;
  opacity:0;visibility:hidden;transform:translateY(-8px);
  transition:all 0.2s;z-index:200;
}
.nav-profile-wrap.open .nav-profile-menu{opacity:1;visibility:visible;transform:translateY(0);}
.nav-profile-item{
  display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;
  color:rgba(255,255,255,0.85);
  font-size:13.5px;font-weight:600;text-decoration:none;
  transition:background 0.15s;white-space:nowrap;
}
.nav-profile-item:hover{background:rgba(245,158,11,0.12);color:var(--accent-light);}
.nav-profile-item img{width:17px;height:17px;opacity:0.7;flex-shrink:0;filter:brightness(0) invert(1);}
.nav-profile-item.danger{color:#f87171;}
.nav-profile-item.danger:hover{background:rgba(220,38,38,0.15);color:#f87171;}
.nav-profile-divider{height:1px;background:rgba(255,255,255,0.08);margin:5px 4px;}
@media(max-width:768px){.nav-profile-name{display:none;}.nav-profile{padding:4px;}}

/* ── HERO ── */
.hero{
  min-height:100vh;
  background:var(--primary-dark);
  position:relative;overflow:hidden;
  display:flex;align-items:center;
  padding:100px 5% 60px;
}
.hero-bg-grid{
  position:absolute;inset:0;
  background-image:linear-gradient(rgba(245,158,11,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(245,158,11,0.04) 1px,transparent 1px);
  background-size:60px 60px;
}
.hero-glow{
  position:absolute;top:-200px;right:-200px;
  width:700px;height:700px;
  background:radial-gradient(circle,rgba(245,158,11,0.12) 0%,transparent 65%);
  pointer-events:none;
}
.hero-glow2{
  position:absolute;bottom:-100px;left:-100px;
  width:500px;height:500px;
  background:radial-gradient(circle,rgba(59,130,246,0.08) 0%,transparent 65%);
  pointer-events:none;
}
.hero-content{position:relative;z-index:2;max-width:640px;}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);
  color:var(--accent-light);padding:7px 16px;border-radius:999px;
  font-size:13px;font-weight:600;margin-bottom:28px;
  animation:fadeUp 0.6s ease both;
}
.badge-dot{width:7px;height:7px;background:var(--accent);border-radius:50%;animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(1.3);}}
.hero h1{
  font-size:clamp(40px,6vw,72px);font-weight:900;
  color:#fff;line-height:1.05;letter-spacing:-2px;
  margin-bottom:22px;
  animation:fadeUp 0.6s ease 0.1s both;
}
.hero h1 .highlight{
  color:var(--accent);
  position:relative;
}
.hero-sub{
  font-size:17px;color:rgba(255,255,255,0.6);line-height:1.7;
  margin-bottom:36px;max-width:520px;
  animation:fadeUp 0.6s ease 0.2s both;
}
.hero-actions{
  display:flex;gap:14px;flex-wrap:wrap;
  animation:fadeUp 0.6s ease 0.3s both;
}
.btn-hero-primary{
  padding:15px 32px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));
  color:#fff;border:none;border-radius:12px;
  font-size:15px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;
  transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1),box-shadow 0.3s ease;
  text-decoration:none;display:inline-flex;align-items:center;gap:8px;
  transform-origin:50% 100%;
}
.btn-hero-primary:hover{transform:translateY(-3px) rotate(-1.5deg);box-shadow:0 12px 30px rgba(245,158,11,0.4);}
.btn-hero-secondary{
  padding:15px 32px;background:rgba(255,255,255,0.07);
  color:#fff;border:1.5px solid rgba(255,255,255,0.2);border-radius:12px;
  font-size:15px;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;
  transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1),border-color 0.3s ease,background 0.3s ease;
  text-decoration:none;display:inline-flex;align-items:center;gap:8px;
  transform-origin:50% 100%;
}
.btn-hero-secondary:hover{border-color:var(--accent);background:rgba(245,158,11,0.08);transform:translateY(-3px) rotate(1.5deg);}
.hero-stats{
  display:flex;gap:36px;margin-top:52px;
  animation:fadeUp 0.6s ease 0.4s both;
}
.stat-item{display:flex;flex-direction:column;gap:4px;}
.stat-num{font-size:28px;font-weight:800;color:#fff;font-family:'Space Mono',monospace;}
.stat-num span{color:var(--accent);}
.stat-label{font-size:12px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:1px;font-family:'Space Mono',monospace;}
.hero-visual{
  position:absolute;right:5%;top:50%;transform:translateY(-50%);
  z-index:2;width:550px;height:550px;
  animation:fadeLeft 0.8s ease 0.3s both;
}
@media(max-width:1300px){.hero-visual{display:none;}}

/* FLOATING CARD */
.float-card{
  background:rgba(30,41,59,0.95);border:1px solid rgba(245,158,11,0.2);
  border-radius:16px;padding:20px;margin-bottom:14px;
  backdrop-filter:blur(10px);
}
.float-card-header{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.shop-avatar{width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:20px;}
.shop-meta h4{font-size:14px;font-weight:700;color:#fff;margin-bottom:2px;}
.shop-meta p{font-size:11px;color:rgba(255,255,255,0.45);}
.stars-row{color:var(--accent);font-size:12px;letter-spacing:1px;}
.badge-open{background:rgba(16,185,129,0.15);color:#10b981;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;letter-spacing:0.5px;font-family:'Space Mono',monospace;}
.tag-row{display:flex;gap:6px;flex-wrap:wrap;}
.tag{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);padding:4px 10px;border-radius:6px;font-size:11px;font-weight:500;font-family:'Space Mono',monospace;}
.book-btn-sm{width:100%;padding:10px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;margin-top:10px;font-family:'Outfit',sans-serif;transition:transform 0.25s cubic-bezier(0.34,1.56,0.64,1);}
.book-btn-sm:hover{transform:rotate(-1deg) scale(1.02);}

/* ── HOW IT WORKS ── */
.section{padding:90px 5%;}
.section-label{
  font-size:12px;font-weight:700;letter-spacing:2px;
  color:var(--accent);text-transform:uppercase;margin-bottom:12px;
  font-family:'Space Mono',monospace;
}
.section-title{font-size:clamp(28px,4vw,44px);font-weight:800;color:var(--text-primary);line-height:1.15;letter-spacing:-1px;}
.section-sub{font-size:16px;color:var(--text-secondary);margin-top:12px;max-width:540px;line-height:1.7;}
.section-header{margin-bottom:56px;}

.steps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;position:relative;}
.step-card{
  padding:32px 28px;
  background:#fff;border:1.5px solid var(--border);
  border-radius:16px;position:relative;
  transition:all 0.3s;
  /* screw-dot corners */
  background-image:
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px);
  background-position:10px 10px,calc(100% - 10px) 10px,10px calc(100% - 10px),calc(100% - 10px) calc(100% - 10px);
  background-repeat:no-repeat;
  background-size:3px 3px;
}
.step-card:hover{transform:translateY(-4px);border-color:var(--accent);box-shadow:0 12px 30px rgba(245,158,11,0.1);}
.step-card:hover{
  background-image:
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px);
  background-position:10px 10px,calc(100% - 10px) 10px,10px calc(100% - 10px),calc(100% - 10px) calc(100% - 10px);
  background-repeat:no-repeat;
  background-size:3px 3px;
}
/* dashed circuit trace connecting each step to the next (desktop only) */
.step-card:not(:last-child)::after{
  content:'';
  position:absolute;top:50%;right:-24px;
  width:24px;height:0;
  border-top:2px dashed rgba(245,158,11,0.35);
  z-index:1;
}
@media(max-width:900px){.step-card:not(:last-child)::after{display:none;}}
.step-num{
  font-size:11px;font-weight:700;letter-spacing:2px;
  color:var(--accent);text-transform:uppercase;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
  font-family:'Space Mono',monospace;
}
.step-num::before{content:'';width:28px;height:2px;background:var(--accent);}
.step-icon{font-size:36px;margin-bottom:16px;}
.step-card h3{font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:8px;}
.step-card p{font-size:14px;color:var(--text-secondary);line-height:1.6;}

/* ── FEATURES ── */
.features-section{background:var(--bg-primary);}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;}
.feature-card{
  background:#fff;padding:28px;border-radius:14px;
  border:1.5px solid var(--border);transition:all 0.3s;
  background-image:
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px),
    radial-gradient(circle,#cbd5e1 1.4px,transparent 1.6px);
  background-position:9px 9px,calc(100% - 9px) 9px,9px calc(100% - 9px),calc(100% - 9px) calc(100% - 9px);
  background-repeat:no-repeat;
  background-size:3px 3px;
}
.feature-card:hover{
  border-color:var(--accent);transform:translateY(-3px);box-shadow:0 8px 24px rgba(245,158,11,0.08);
  background-image:
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px),
    radial-gradient(circle,var(--accent) 1.4px,transparent 1.6px);
  background-position:9px 9px,calc(100% - 9px) 9px,9px calc(100% - 9px),calc(100% - 9px) calc(100% - 9px);
  background-repeat:no-repeat;
  background-size:3px 3px;
}
.feature-icon{
  width:48px;height:48px;border-radius:12px;
  background:rgba(245,158,11,0.1);
  display:flex;align-items:center;justify-content:center;
  font-size:22px;margin-bottom:16px;
}
.feature-card h3{font-size:16px;font-weight:700;margin-bottom:8px;}
.feature-card p{font-size:13px;color:var(--text-secondary);line-height:1.6;}

/* ── SHOPS PREVIEW ── */
.shops-preview{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
.shop-prev-card{
  background:#fff;border:1.5px solid var(--border);border-radius:14px;
  padding:22px;transition:all 0.3s;
}
.shop-prev-card:hover{transform:translateY(-4px);border-color:var(--accent);box-shadow:0 10px 28px rgba(245,158,11,0.1);}
.shop-prev-logo{
  width:60px;height:60px;border-radius:12px;
  background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.05));
  display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:12px;
}
.shop-prev-name{font-size:15px;font-weight:700;margin-bottom:4px;}
.shop-prev-loc{font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:4px;margin-bottom:10px;}
.shop-prev-stars{color:var(--accent);font-size:12px;}
.shop-prev-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;}
.shop-prev-tag{background:var(--bg-primary);border:1px solid var(--border);color:var(--text-secondary);padding:3px 9px;border-radius:5px;font-size:11px;font-weight:500;font-family:'Space Mono',monospace;}
.badge-featured{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;padding:3px 9px;border-radius:5px;font-size:10px;font-weight:700;letter-spacing:0.5px;font-family:'Space Mono',monospace;}

/* ── CTA SECTION ── */
.cta-section{
  background:var(--primary-dark);
  padding:90px 5%;text-align:center;position:relative;overflow:hidden;
}
.cta-glow{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:600px;height:600px;
  background:radial-gradient(circle,rgba(245,158,11,0.1) 0%,transparent 65%);
  pointer-events:none;
}
.cta-section h2{font-size:clamp(28px,4vw,48px);font-weight:900;color:#fff;letter-spacing:-1.5px;margin-bottom:14px;position:relative;}
.cta-section p{font-size:16px;color:rgba(255,255,255,0.55);margin-bottom:36px;position:relative;}
.cta-actions{display:flex;justify-content:center;gap:14px;flex-wrap:wrap;position:relative;}

/* ── FOOTER ── */
.footer{
  background:var(--primary);padding:48px 5% 28px;
  border-top:1px solid rgba(255,255,255,0.06);
}
.footer-top{display:grid;grid-template-columns:1fr auto;gap:40px;align-items:start;margin-bottom:32px;}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.footer-brand img{width:36px;height:36px;object-fit:contain;border-radius:8px;background:#fff;padding:3px;}
.footer-brand-name{font-size:16px;font-weight:800;color:#fff;letter-spacing:1px;}
.footer-brand-name span{color:var(--accent);}
.footer-desc{font-size:13px;color:rgba(255,255,255,0.4);max-width:300px;line-height:1.6;}
.footer-links{display:flex;gap:28px;}
.footer-links a{color:rgba(255,255,255,0.5);font-size:13px;text-decoration:none;transition:color 0.2s;}
.footer-links a:hover{color:var(--accent-light);}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.06);padding-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.footer-copy{font-size:12px;color:rgba(255,255,255,0.3);font-family:'Space Mono',monospace;}
.footer-davao{font-size:12px;color:rgba(255,255,255,0.25);}

/* ── ANIMATIONS ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
@keyframes fadeLeft{from{opacity:0;transform:translate(24px,-50%);}to{opacity:1;transform:translate(0,-50%);}}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .nav-links{display:none;}
  .hero{padding:90px 5% 50px;}
  .hero-stats{gap:24px;}
  .stat-num{font-size:22px;}
  .footer-top{grid-template-columns:1fr;}
  .footer-links{flex-wrap:wrap;gap:16px;}
  .footer-bottom{justify-content:center;}
  @media(max-width:768px){
  /* Navbar fix */
  .navbar{padding:0 4%;height:64px;}
  .nav-cta{gap:6px;}
  .btn-login{padding:7px 12px;font-size:12px;}
  .btn-register{padding:7px 12px;font-size:12px;}
}

  /* Hero buttons stack */
  .hero-actions{flex-direction:column;}
  .btn-hero-primary,.btn-hero-secondary{width:100%;justify-content:center;}

  /* Steps */
  .steps-grid{grid-template-columns:1fr;}

  /* Features */
  .features-grid{grid-template-columns:1fr;}

  /* Shops preview */
  .shops-preview{grid-template-columns:1fr;}

  /* CTA buttons stack */
  .cta-actions{flex-direction:column;align-items:center;}
  .cta-actions a{width:100%;max-width:320px;justify-content:center;}

  /* Footer */
  .footer-top{grid-template-columns:1fr;}
  .footer-links{flex-wrap:wrap;gap:16px;}
  .footer-bottom{justify-content:center;text-align:center;}
}

@media(max-width:480px){
  .hero h1{font-size:36px;letter-spacing:-1px;}
  .hero-sub{font-size:15px;}
  .hero-stats{flex-direction:column;gap:16px;margin-top:32px;}
  .section{padding:60px 5%;}
  .section-title{font-size:28px;}
  .float-card{display:none;}
}

.auth-modal-overlay{
  position:fixed;inset:0;z-index:2000;
  background:rgba(2,6,23,0.72);backdrop-filter:blur(4px);
  display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity 0.22s ease;
  padding:20px;overflow:hidden;
}
.auth-modal-overlay.visible{opacity:1;pointer-events:all;}
.auth-modal-box{
  position:relative;width:100%;max-width:460px;max-height:90vh;
  background:transparent;border:none;border-radius:0;box-shadow:none;
  transform:translateY(14px);transition:transform 0.22s ease;
  overflow:hidden;
}
.auth-modal-overlay.visible .auth-modal-box{transform:translateY(0);}
.auth-modal-box iframe{width:100%;height:560px;max-height:90vh;border:none;display:block;background:transparent;transition:height 0.2s ease;overflow:hidden;}
.auth-modal-close{
  position:absolute;top:70px;right:52px;z-index:5;
  width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,0.15);
  background:rgba(15,23,42,0.95);color:rgba(255,255,255,0.7);
  font-size:18px;line-height:1;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all 0.2s ease;
  box-shadow:0 6px 18px rgba(0,0,0,0.3);
}
.auth-modal-close:hover{background:rgba(245,158,11,0.15);border-color:rgba(245,158,11,0.4);color:#f59e0b;}
@media(max-width:480px){
  .auth-modal-box iframe{height:100vh;max-height:100vh;}
  .auth-modal-close{top:58px;right:38px;}
}

html{
  scroll-behavior:smooth;
  scrollbar-gutter: stable;
}

/* ── Scroll zoom reveal ── */
.step-card, .feature-card, .shop-prev-card {
  transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1), border-color 0.3s, box-shadow 0.3s;
}

/* ── Hero image zoom on scroll ── */
.hero-visual {
  transition: transform 0.1s linear;
  will-change: transform;
}

/* ── Section title zoom-in reveal ── */
.section-header {
  opacity: 0;
  transform: scale(0.92) translateY(20px);
  transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
}
.section-header.revealed {
  opacity: 1;
  transform: scale(1) translateY(0);
}

/* ── PAGE LOADER (Themed: Wrench & Gear) ── */
#pageLoader{
  position:fixed;inset:0;z-index:99999;
  background:var(--primary-dark);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:26px;overflow:hidden;
  transition:opacity 0.5s ease, visibility 0.5s ease;
}
#pageLoader.loaded{
  opacity:0;visibility:hidden;pointer-events:none;
}
#pageLoader .loader-bg-grid{
  position:absolute;inset:0;
  background-image:linear-gradient(rgba(245,158,11,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(245,158,11,0.04) 1px,transparent 1px);
  background-size:50px 50px;
}
#pageLoader .loader-glow{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:420px;height:420px;
  background:radial-gradient(circle,rgba(245,158,11,0.16) 0%,transparent 70%);
  pointer-events:none;
}

/* Gear + Wrench rig */
.loader-rig{
  position:relative;width:120px;height:120px;
  display:flex;align-items:center;justify-content:center;
}
.loader-gear{
  position:absolute;width:100px;height:100px;
  animation:gearSpin 3s linear infinite;
}
.loader-gear svg{width:100%;height:100%;}
.loader-gear path,.loader-gear circle{fill:none;stroke:rgba(245,158,11,0.35);stroke-width:2.5;}
@keyframes gearSpin{to{transform:rotate(360deg);}}

.loader-wrench{
  position:relative;width:46px;height:46px;z-index:2;
  animation:wrenchTurn 1.6s ease-in-out infinite;
  transform-origin:70% 70%;
  filter:drop-shadow(0 4px 12px rgba(245,158,11,0.5));
}
.loader-wrench svg{width:100%;height:100%;}
@keyframes wrenchTurn{
  0%,100%{transform:rotate(-18deg);}
  50%{transform:rotate(18deg);}
}

.loader-sparks{
  position:absolute;width:8px;height:8px;border-radius:50%;
  background:var(--accent-light);opacity:0;
}
.loader-sparks:nth-child(1){top:20%;left:75%;animation:sparkPop 1.6s ease-in-out infinite 0.1s;}
.loader-sparks:nth-child(2){top:70%;left:80%;animation:sparkPop 1.6s ease-in-out infinite 0.5s;}
.loader-sparks:nth-child(3){top:75%;left:20%;animation:sparkPop 1.6s ease-in-out infinite 0.9s;}
@keyframes sparkPop{
  0%,100%{opacity:0;transform:scale(0.4);}
  50%{opacity:1;transform:scale(1.2);}
}

.loader-brand{
  font-size:17px;font-weight:800;letter-spacing:1px;color:#fff;
  display:flex;align-items:center;gap:6px;
}
.loader-brand span{color:var(--accent);}

.loader-bar-track{
  width:180px;height:4px;border-radius:99px;
  background:rgba(255,255,255,0.08);overflow:hidden;
}
.loader-bar-fill{
  width:40%;height:100%;border-radius:99px;
  background:linear-gradient(90deg,var(--accent),var(--accent-light));
  animation:barSlide 1.3s ease-in-out infinite;
}
@keyframes barSlide{
  0%{transform:translateX(-100%);}
  100%{transform:translateX(280%);}
}

.loader-text{
  font-size:12px;font-weight:600;letter-spacing:1.5px;
  color:rgba(255,255,255,0.4);text-transform:uppercase;
  font-family:'Space Mono',monospace;
}
</style>
</head>
<body>

<!-- PAGE LOADER -->
<div id="pageLoader">
  <div class="loader-bg-grid"></div>
  <div class="loader-glow"></div>

  <div class="loader-rig">
    <div class="loader-gear">
      <svg viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="34"/>
        <path d="M50 8 L54 20 L46 20 Z M50 92 L54 80 L46 80 Z
                 M92 50 L80 54 L80 46 Z M8 50 L20 54 L20 46 Z
                 M78.5 21.5 L69.5 27.5 L74 20 Z
                 M21.5 78.5 L30.5 72.5 L26 80 Z
                 M78.5 78.5 L72.5 69.5 L80 74 Z
                 M21.5 21.5 L27.5 30.5 L20 26 Z"/>
      </svg>
    </div>
    <div class="loader-wrench">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
      </svg>
    </div>
    <div class="loader-sparks"></div>
    <div class="loader-sparks"></div>
    <div class="loader-sparks"></div>
  </div>

  <div class="loader-brand">Fix It <span>Davao</span></div>
  <div class="loader-bar-track"><div class="loader-bar-fill"></div></div>
  <div class="loader-text">Getting things ready...</div>
</div>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="#" class="nav-logo">
    <img src="assets/images/logo.png" alt="Fix It Davao Logo">
    <span class="nav-brand">Fix It <span>Davao</span></span>
  </a>
  <div class="nav-links">
    <a href="#how">How It Works</a>
    <a href="#features">Features</a>
    <a href="#shops">Shops</a>
  </div>
  <div class="nav-cta">
<?php if($isLoggedIn): ?>
    <?php
  if ($userRole === 'admin') {
      $dashboardUrl = 'admin/admin-dashboard.php';
  } elseif ($userRole === 'repairshop') {
      $dashboardUrl = 'shop-owner/shop-information.php';
  } else {
      $dashboardUrl = 'shop-owner/dashboard.php';
  }
?>
    <div class="nav-profile-wrap" id="navProfileWrap">
      <button type="button" class="nav-profile" title="Account" onclick="toggleProfileMenu(event)">
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="nav-profile-avatar"
             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=<?php echo $avatarBg; ?>&color=fff'" />
        <span class="nav-profile-name"><?php echo htmlspecialchars($userName); ?></span>
        <span class="nav-profile-caret"></span>
      </button>
      <div class="nav-profile-menu">
        <a href="<?php echo $dashboardUrl; ?>" class="nav-profile-item"><img src="assets/icons/find.svg" alt="" /> Dashboard</a>
<?php if($userRole !== 'admin' && $userRole !== 'repairshop'): ?>
        <a href="customer/my-bookings.php" class="nav-profile-item"><img src="assets/icons/book.svg" alt="" /> My Bookings</a>
        <a href="customer/favorites.php" class="nav-profile-item"><img src="assets/icons/favorite.svg" alt="" /> Favorites</a>
        <a href="customer/history.php" class="nav-profile-item"><img src="assets/icons/history.svg" alt="" /> History</a>
        <a href="shop-owner/dashboard.php?settings=1" class="nav-profile-item"><img src="assets/icons/users.svg" alt="" /> Account Settings</a>
<?php endif; ?>
        <div class="nav-profile-divider"></div>
        <a href="logout.php" class="nav-profile-item danger"><img src="assets/icons/logout.svg" alt="" /> Logout</a>
      </div>
    </div>
<?php else: ?>
    <a href="login.php" class="btn-login" onclick="return openAuthModal('login.php', event)">Log In</a>
    <a href="signup.php" class="btn-register" onclick="return openAuthModal('signup.php', event)">Register Here</a>
<?php endif; ?>
</div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg-grid"></div>
  <div class="hero-glow"></div>
  <div class="hero-glow2"></div>
  <div class="hero-content">
    <div class="hero-badge">
      <span class="badge-dot"></span>
      Davao City's #1 Repair Portal
    </div>
    <h1>Find Trusted <span class="highlight">Repair Shops</span> in Davao</h1>
    <p class="hero-sub">Book phone, laptop, appliance, and gadget repairs in minutes. Connect with verified local repair shops — fast, reliable, and hassle-free.</p>
<div class="hero-actions">
<?php if($isLoggedIn): ?>
    <a href="<?php echo $dashboardUrl; ?>" class="btn-hero-primary">
      <img src="assets/icons/tools.svg" style="width:18px;height:18px;filter:brightness(0) invert(1);"> Go to Dashboard
    </a>
<?php else: ?>
    <a href="signup.php" class="btn-hero-primary" onclick="return openAuthModal('signup.php', event)">
      <img src="assets/icons/tools.svg" style="width:18px;height:18px;filter:brightness(0) invert(1);"> Find a Repair Shop
    </a>
    <a href="signup.php?role=shop" class="btn-hero-secondary" onclick="return openAuthModal('signup.php', event)">
      <img src="assets/icons/store.svg" style="width:18px;height:18px;filter:brightness(0) invert(1);"> Register Your Shop
    </a>
<?php endif; ?>
</div>
    <div class="hero-stats">
      <div class="stat-item">
        <span class="stat-num">50<span>+</span></span>
        <span class="stat-label">Repair Shops</span>
      </div>
      <div class="stat-item">
        <span class="stat-num">500<span>+</span></span>
        <span class="stat-label">Bookings Made</span>
      </div>
      <div class="stat-item">
        <span class="stat-num">4.8<span>★</span></span>
        <span class="stat-label">Avg. Rating</span>
      </div>
    </div>
  </div>

<!-- Floating Card Preview -->
  <div class="hero-visual">
    <img src="assets/images/man.png" alt="Repair Illustration" class="hero-image slide active" data-slide="0">
    <img src="assets/images/picture2.png" alt="Fix It Davao Logo" class="hero-image slide" data-slide="1">
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section" id="how">
  <div class="section-header">
    <div class="section-label">How It Works</div>
    <h2 class="section-title">3 Simple Steps to Get<br>Your Device Fixed</h2>
    <p class="section-sub">No more walking around Davao looking for a repair shop. Find, book, and get it fixed — all in one place.</p>
  </div>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-num">Step 01</div>
      <div class="step-icon"><img src="assets/icons/ngita.svg" style="width:36px;height:36px;"></div>
      <h3>Search a Shop</h3>
      <p>Browse verified repair shops in Davao City. Filter by service type, location, or rating.</p>
    </div>
    <div class="step-card">
      <div class="step-num">Step 02</div>
      <div class="step-icon"><img src="assets/icons/libro.svg" style="width:36px;height:36px;"></div>
      <h3>Book an Appointment</h3>
      <p>Pick a schedule that works for you. Get instant email confirmation after booking.</p>
    </div>
    <div class="step-card">
      <div class="step-num">Step 03</div>
      <div class="step-icon"><img src="assets/icons/korek.svg" style="width:36px;height:36px;"></div>
      <h3>Get It Fixed</h3>
      <p>Visit the shop, get your device repaired. Track booking status anytime in your dashboard.</p>
    </div>
    <div class="step-card">
      <div class="step-num">Step 04</div>
      <div class="step-icon"><img src="assets/icons/shine.svg" style="width:36px;height:36px;"></div>
      <h3>Leave a Review</h3>
      <p>Help other customers find the best shops by leaving an honest review after your repair.</p>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section features-section" id="features">
  <div class="section-header">
    <div class="section-label">Why Fix It Davao</div>
    <h2 class="section-title">Everything You Need,<br>All in One Portal</h2>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/shield.svg" style="width:22px;height:22px;"></div>
      <h3>Verified Shops Only</h3>
      <p>Every repair shop goes through admin verification before being listed on the portal.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/silpon.svg" style="width:22px;height:22px;"></div>
      <h3>Real-Time Booking</h3>
      <p>Book anytime, anywhere. Get instant notifications via email when your booking is confirmed.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/lokasyon.svg" style="width:22px;height:22px;"></div>
      <h3>Davao-Focused</h3>
      <p>Built specifically for Davao City customers and local repair shops — no generic listings.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/robot.svg" style="width:22px;height:22px;"></div>
      <h3>AI Service Suggestions</h3>
      <p>Not sure what's wrong? Our AI can help suggest the right repair service for your device.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/riport.svg" style="width:22px;height:22px;"></div>
      <h3>Shop Dashboard</h3>
      <p>Repair shop owners get a full dashboard to manage bookings, services, and schedules.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><img src="assets/icons/shine.svg" style="width:22px;height:22px;"></div>
      <h3>Ratings & Reviews</h3>
      <p>Read honest reviews from real customers to pick the best repair shop for your needs.</p>
    </div>
  </div>
</section>

<!-- SHOPS PREVIEW -->
<section class="section" id="shops">
  <div class="section-header">
    <div class="section-label">Featured Shops</div>
    <h2 class="section-title">Top-Rated Repair Shops<br>in Davao City</h2>
    <p class="section-sub">Boosted listings — verified shops trusted by hundreds of Davao customers.</p>
  </div>
  <div class="shops-preview">
    <div class="shop-prev-card">
      <div class="shop-prev-logo"><img src="assets/icons/mobile.svg" style="width:26px;height:26px;"></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="shop-prev-name">Blingue Tech Solutions</span>
        <span class="badge-featured"><img src="assets/icons/shine.svg" style="width:11px;height:11px;filter:brightness(0) invert(1);vertical-align:middle;margin-right:3px;"> FEATURED</span>
      </div>
      <div class="shop-prev-loc"><img src="assets/icons/location.svg" style="width:12px;height:12px;opacity:0.6;vertical-align:middle;margin-right:3px;"> Davao City</div>
      <div class="shop-prev-stars">★★★★★ 4.9</div>
      <div class="shop-prev-tags">
        <span class="shop-prev-tag">Phone Repair</span>
        <span class="shop-prev-tag">Screen Replace</span>
        <span class="shop-prev-tag">Unlocking</span>
      </div>
    </div>
    <div class="shop-prev-card">
      <div class="shop-prev-logo"><img src="assets/icons/laptop.svg" style="width:26px;height:26px;"></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="shop-prev-name">TechFix Davao</span>
        <span class="badge-featured"><img src="assets/icons/shine.svg" style="width:11px;height:11px;filter:brightness(0) invert(1);vertical-align:middle;margin-right:3px;"> FEATURED</span>
      </div>
      <div class="shop-prev-loc"><img src="assets/icons/location.svg" style="width:12px;height:12px;opacity:0.6;vertical-align:middle;margin-right:3px;"> Bajada</div>
      <div class="shop-prev-stars">★★★★☆ 4.6</div>
      <div class="shop-prev-tags">
        <span class="shop-prev-tag">Laptop</span>
        <span class="shop-prev-tag">Data Recovery</span>
        <span class="shop-prev-tag">OS Reinstall</span>
      </div>
    </div>
    <div class="shop-prev-card">
      <div class="shop-prev-logo"><img src="assets/icons/laptop.svg" style="width:26px;height:26px;"></div>
      <span class="shop-prev-name">Gadget Hub Davao</span>
      <div class="shop-prev-loc"><img src="assets/icons/location.svg" style="width:12px;height:12px;opacity:0.6;vertical-align:middle;margin-right:3px;"> Buhangin</div>
      <div class="shop-prev-stars">★★★★☆ 4.5</div>
      <div class="shop-prev-tags">
        <span class="shop-prev-tag">Appliance</span>
        <span class="shop-prev-tag">TV Repair</span>
        <span class="shop-prev-tag">Aircon</span>
      </div>
    </div>
  </div>
</section>

<!-- CTA SECTION -->
<section class="cta-section">
  <div class="cta-glow"></div>
  <h2>Ready to Get Your Device Fixed?</h2>
  <p>Join hundreds of Davao customers already using Fix It Davao.</p>
  <div class="cta-actions">
    <a href="login.php" class="btn-hero-primary" style="font-size:15px;" onclick="return openAuthModal('login.php', event)">
  <img src="assets/icons/tools.svg" style="width:16px;height:16px;filter:brightness(0) invert(1);vertical-align:middle;margin-right:6px;"> Find a Shop Now
</a>
    <a href="signup.php?role=shop" class="btn-hero-secondary" style="font-size:15px;" onclick="return openAuthModal('signup.php?role=shop', event)">
  <img src="assets/icons/store.svg" style="width:16px;height:16px;filter:brightness(0) invert(1);vertical-align:middle;margin-right:6px;"> List Your Shop
</a>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-top">
    <div>
      <div class="footer-brand">
        <img src="assets/images/logo.png" alt="Fix It Davao Logo">
        <span class="footer-brand-name">Fix It <span>Davao</span></span>
      </div>
      <p class="footer-desc">Connecting Davao customers with trusted local repair shops — fast, verified, and reliable.</p>
    </div>
    <div class="footer-links">
      <a href="#how">How It Works</a>
      <a href="#features">Features</a>
      <a href="#shops">Shops</a>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
      <a href="privacy-policy.php">Privacy Policy</a>
      <a href="terms-of-service.php">Terms of Service</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span class="footer-copy">© 2026 Fix It Davao. All rights reserved.</span>
  </div>
</footer>

<!-- AUTH MODAL -->
<div class="auth-modal-overlay" id="authModalOverlay">
  <div class="auth-modal-box">
    <button class="auth-modal-close" id="authModalClose">&times;</button>
    <iframe id="authModalFrame" src="" frameborder="0" scrolling="no"></iframe>
  </div>
</div>

<script>
// ── Scroll zoom reveal (REPLACE sa naa nang observer) ──
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if(e.isIntersecting){
      e.target.style.opacity = '1';
      e.target.style.transform = 'scale(1) translateY(0)';
    }
  });
}, {threshold: 0.1});

document.querySelectorAll('.step-card, .feature-card, .shop-prev-card').forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'scale(0.88) translateY(24px)';
  el.style.transitionDelay = `${(i % 3) * 0.08}s`;
  observer.observe(el);
});

// ── Section headers zoom-in ──
const headerObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) e.target.classList.add('revealed');
  });
}, { threshold: 0.2 });
document.querySelectorAll('.section-header').forEach(el => headerObserver.observe(el));

// ── Background grid parallax on scroll ──
window.addEventListener('scroll', () => {
  const scrolled = window.scrollY;
  const grid = document.querySelector('.hero-bg-grid');
  if (grid) grid.style.transform = `translateY(${scrolled * 0.15}px)`;
});

// ── Animated stat counters ──
function animateCounter(el, target, decimals = 0) {
  let start = 0;
  const duration = 1400;
  const startTime = performance.now();
  function tick(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const value = start + (target - start) * eased;
    el.textContent = decimals > 0 ? value.toFixed(decimals) : Math.floor(value);
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

const statObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const nums = entry.target.querySelectorAll('.stat-num');
      const targets = [50, 500, 4.8];
      const decimals = [0, 0, 1];
      nums.forEach((el, i) => {
        const suffix = el.querySelector('span');
        const suffixHtml = suffix ? suffix.outerHTML : '';
        animateCounter(el, targets[i], decimals[i]);
        setTimeout(() => {
          el.innerHTML = (decimals[i] > 0 ? targets[i].toFixed(decimals[i]) : targets[i]) + suffixHtml;
        }, 1450);
      });
      statObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });

const heroStats = document.querySelector('.hero-stats');
if (heroStats) statObserver.observe(heroStats);

// ── Hero image slideshow (fade crossfade) ──
const heroSlides = document.querySelectorAll('.hero-visual .hero-image.slide');
let currentSlide = 0;

if (heroSlides.length > 1) {
  setInterval(() => {
    heroSlides[currentSlide].classList.remove('active');
    currentSlide = (currentSlide + 1) % heroSlides.length;
    heroSlides[currentSlide].classList.add('active');
  }, 3500); // 3.5 seconds per slide
}
</script>
<script>
setTimeout(function () {
    window.location.href = "login.php?timeout=1";
}, 1800000); // 30 minutes

const authOverlay = document.getElementById('authModalOverlay');
const authFrame    = document.getElementById('authModalFrame');
const authClose    = document.getElementById('authModalClose');
let pollInterval = null;

function resizeAuthFrame() {
  try {
    const doc = authFrame.contentWindow.document;
    const card = doc.querySelector('.auth-card');
    const contentHeight = card ? card.scrollHeight : doc.body.scrollHeight;
    // Padding sa taas (para sa X button gap) + gamay nga buffer sa ubos
    const newHeight = Math.min(contentHeight + 90, window.innerHeight * 0.9);
    authFrame.style.height = newHeight + 'px';
  } catch (err) {
    // cross-origin guard, safe to ignore (dili ni mahitabo sa localhost)
  }
}

function openAuthModal(page, e) {
  if (e) e.preventDefault();
  authFrame.src = page;
  authOverlay.classList.add('visible');
  document.body.style.overflow = 'hidden';

  authFrame.onload = resizeAuthFrame;

  // Poll para ma-detect kung na-redirect na ang iframe (successful login/signup)
  clearInterval(pollInterval);
  pollInterval = setInterval(() => {
    try {
      const frameUrl = authFrame.contentWindow.location.href;
      const isStillOnAuthPage = frameUrl.includes('login.php') || frameUrl.includes('signup.php');
      if (!isStillOnAuthPage) {
        // Na-redirect na sa dashboard/admin — break out sa iframe, i-navigate ang tibuok tab
        window.location.href = frameUrl;
      }
    } catch (err) {
      // cross-origin guard, safe to ignore (dili ni mahitabo sa localhost)
    }
  }, 500);

  return false;
}

function closeAuthModal() {
  authOverlay.classList.remove('visible');
  document.body.style.overflow = '';
  clearInterval(pollInterval);

  // Delay clearing sa iframe src hangtod mahuman ang fade-out (0.25s sa CSS)
  setTimeout(() => {
    authFrame.src = '';
  }, 250); // dapat mo-match sa transition time sa .auth-modal-overlay (0.25s)
}

authClose.addEventListener('click', closeAuthModal);
authOverlay.addEventListener('click', (e) => { if (e.target === authOverlay) closeAuthModal(); });

// ── Switch between login/signup/forgot-password inside modal ──
      window.addEventListener('message', (e) => {
        if (e.data === 'switch-to-forgot') { authFrame.src = 'forgot-password.php'; authFrame.onload = resizeAuthFrame; }
        if (e.data === 'switch-to-login') { authFrame.src = 'login.php'; authFrame.onload = resizeAuthFrame; }
        if (e.data === 'resize-auth') resizeAuthFrame();
        if (e.data === 'close-modal') closeAuthModal();   // ← bag-o
      });
  </script>
<script>
  window.addEventListener('load', function () {
    const loader = document.getElementById('pageLoader');
    if (loader) {
      setTimeout(() => loader.classList.add('loaded'), 300);
    }
  });
</script>
<script>
  function toggleProfileMenu(e) {
    e.stopPropagation();
    document.getElementById('navProfileWrap').classList.toggle('open');
  }
  document.addEventListener('click', function (e) {
    const wrap = document.getElementById('navProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
  });
</script>

<?php
  $chatbotApiPath  = 'api/chatbot.php';
  $chatbotLogoPath = 'assets/images/logo.png';
  include __DIR__ . '/includes/chatbot-widget.php';
?>
  <script src="assets/js/ui-modals.js"></script>
</body>
</html>