
<?php
session_start();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fix It Davao — Your Trusted Repair Portal</title>
<link rel="icon" type="image/png" href="assets/images/logo.png" />
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
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
.btn-login{padding:9px 20px;background:transparent;border:1.5px solid rgba(255,255,255,0.3);color:#fff;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:all 0.2s;text-decoration:none;}
.btn-login:hover{border-color:var(--accent);color:var(--accent);}
.btn-register{padding:9px 20px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:all 0.2s;text-decoration:none;}
.btn-register:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(245,158,11,0.4);}

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
  transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;
}
.btn-hero-primary:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(245,158,11,0.4);}
.btn-hero-secondary{
  padding:15px 32px;background:rgba(255,255,255,0.07);
  color:#fff;border:1.5px solid rgba(255,255,255,0.2);border-radius:12px;
  font-size:15px;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;
  transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;
}
.btn-hero-secondary:hover{border-color:var(--accent);background:rgba(245,158,11,0.08);}
.hero-stats{
  display:flex;gap:36px;margin-top:52px;
  animation:fadeUp 0.6s ease 0.4s both;
}
.stat-item{display:flex;flex-direction:column;gap:4px;}
.stat-num{font-size:28px;font-weight:800;color:#fff;font-family:'Space Mono',monospace;}
.stat-num span{color:var(--accent);}
.stat-label{font-size:12px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:1px;}
.hero-visual{
  position:absolute;right:5%;top:50%;transform:translateY(-50%);
  z-index:2;width:420px;
  animation:fadeLeft 0.8s ease 0.3s both;
}
@media(max-width:1100px){.hero-visual{display:none;}}

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
.badge-open{background:rgba(16,185,129,0.15);color:#10b981;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;letter-spacing:0.5px;}
.tag-row{display:flex;gap:6px;flex-wrap:wrap;}
.tag{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);padding:4px 10px;border-radius:6px;font-size:11px;font-weight:500;}
.book-btn-sm{width:100%;padding:10px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;margin-top:10px;font-family:'Outfit',sans-serif;}

/* ── HOW IT WORKS ── */
.section{padding:90px 5%;}
.section-label{
  font-size:12px;font-weight:700;letter-spacing:2px;
  color:var(--accent);text-transform:uppercase;margin-bottom:12px;
}
.section-title{font-size:clamp(28px,4vw,44px);font-weight:800;color:var(--text-primary);line-height:1.15;letter-spacing:-1px;}
.section-sub{font-size:16px;color:var(--text-secondary);margin-top:12px;max-width:540px;line-height:1.7;}
.section-header{margin-bottom:56px;}

.steps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;}
.step-card{
  padding:32px 28px;
  background:#fff;border:1.5px solid var(--border);
  border-radius:16px;position:relative;
  transition:all 0.3s;
}
.step-card:hover{transform:translateY(-4px);border-color:var(--accent);box-shadow:0 12px 30px rgba(245,158,11,0.1);}
.step-num{
  font-size:11px;font-weight:700;letter-spacing:2px;
  color:var(--accent);text-transform:uppercase;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
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
}
.feature-card:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 8px 24px rgba(245,158,11,0.08);}
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
.shop-prev-tag{background:var(--bg-primary);border:1px solid var(--border);color:var(--text-secondary);padding:3px 9px;border-radius:5px;font-size:11px;font-weight:500;}
.badge-featured{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;padding:3px 9px;border-radius:5px;font-size:10px;font-weight:700;letter-spacing:0.5px;}

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
</style>
</head>
<body>

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
    <a href="shop-owner/dashboard.php" class="btn-login">Dashboard</a>
    <a href="logout.php" class="btn-register">Logout</a>
  <?php else: ?>
    <a href="login.php" class="btn-login">Log In</a>
    <a href="signup.php" class="btn-register">Register Here</a>
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
    <a href="shop-owner/dashboard.php" class="btn-hero-primary">
      <img src="assets/icons/tools.svg" style="width:18px;height:18px;filter:brightness(0) invert(1);"> Go to Dashboard
    </a>
  <?php else: ?>
    <a href="signup.php" class="btn-hero-primary">
      <img src="assets/icons/tools.svg" style="width:18px;height:18px;filter:brightness(0) invert(1);"> Find a Repair Shop
    </a>
    <a href="signup.php?role=shop" class="btn-hero-secondary">
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
    <div class="float-card" style="transform:rotate(-2deg);margin-right:20px;">
      <div class="float-card-header">
        <div class="shop-avatar">📱</div>
        <div class="shop-meta">
          <h4>Ken's Techshop Since 2026</h4>
          <p>📍 Davao City</p>
        </div>
        <span class="badge-open">OPEN</span>
      </div>
      <div class="stars-row">★★★★★ <span style="color:rgba(255,255,255,0.4);font-size:11px;"> 4.9 (128 reviews)</span></div>
      <div class="tag-row" style="margin-top:10px;">
        <span class="tag">Phone Repair</span>
        <span class="tag">Screen Replace</span>
        <span class="tag">Battery</span>
      </div>
      <button class="book-btn-sm">Book Now →</button>
    </div>
    <div class="float-card" style="transform:rotate(1.5deg);margin-left:20px;">
      <div class="float-card-header">
        <div class="shop-avatar">💻</div>
        <div class="shop-meta">
          <h4>H & HW Repairshop</h4>
          <p>📍 Bajada, Davao</p>
        </div>
        <span class="badge-open">OPEN</span>
      </div>
      <div class="stars-row">★★★★☆ <span style="color:rgba(255,255,255,0.4);font-size:11px;"> 4.6 (87 reviews)</span></div>
      <div class="tag-row" style="margin-top:10px;">
        <span class="tag">Laptop Repair</span>
        <span class="tag">Data Recovery</span>
      </div>
      <button class="book-btn-sm">Book Now →</button>
    </div>
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
      <div class="shop-prev-logo">📱</div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="shop-prev-name">Blingue Tech Solutions</span>
        <span class="badge-featured">⭐ FEATURED</span>
      </div>
      <div class="shop-prev-loc">📍 Davao City</div>
      <div class="shop-prev-stars">★★★★★ 4.9</div>
      <div class="shop-prev-tags">
        <span class="shop-prev-tag">Phone Repair</span>
        <span class="shop-prev-tag">Screen Replace</span>
        <span class="shop-prev-tag">Unlocking</span>
      </div>
    </div>
    <div class="shop-prev-card">
      <div class="shop-prev-logo">💻</div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="shop-prev-name">TechFix Davao</span>
        <span class="badge-featured">⭐ FEATURED</span>
      </div>
      <div class="shop-prev-loc">📍 Bajada</div>
      <div class="shop-prev-stars">★★★★☆ 4.6</div>
      <div class="shop-prev-tags">
        <span class="shop-prev-tag">Laptop</span>
        <span class="shop-prev-tag">Data Recovery</span>
        <span class="shop-prev-tag">OS Reinstall</span>
      </div>
    </div>
    <div class="shop-prev-card">
      <div class="shop-prev-logo">🔧</div>
      <span class="shop-prev-name">Gadget Hub Davao</span>
      <div class="shop-prev-loc">📍 Buhangin</div>
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
    <a href="register.php" class="btn-hero-primary" style="font-size:15px;">🔧 Find a Shop Now</a>
    <a href="register.php?role=shop" class="btn-hero-secondary" style="font-size:15px;">🏪 List Your Shop</a>
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
    </div>
  </div>
  <div class="footer-bottom">
    <span class="footer-copy">© 2025 Fix It Davao. All rights reserved.</span>
    <span class="footer-davao">Made with 🧡 in Davao City, Philippines</span>
  </div>
</footer>

<script>
// Smooth reveal on scroll
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if(e.isIntersecting){
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
    }
  });
}, {threshold: 0.1});

document.querySelectorAll('.step-card, .feature-card, .shop-prev-card').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease, border-color 0.3s, box-shadow 0.3s';
  observer.observe(el);
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
  const nav = document.querySelector('.navbar');
  if(window.scrollY > 50){
    nav.style.background = 'rgba(2,6,23,0.97)';
    nav.style.boxShadow = '0 4px 20px rgba(0,0,0,0.3)';
  } else {
    nav.style.background = 'rgba(2,6,23,0.92)';
    nav.style.boxShadow = 'none';
  }
});
</script>
 <script>
setTimeout(function () {
    window.location.href = "login.php?timeout=1";
}, 1800000); // 30 minutes
</script>
</body>
</html>