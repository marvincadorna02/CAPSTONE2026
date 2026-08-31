<?php
// terms-of-service.php
// Public page — no auth required.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms of Service - Fix It Davao</title>
<link rel="icon" href="assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  :root {
    --accent:#f59e0b;
    --accent-light:#fbbf24;
    --accent-dark:#d97706;
    --navy:#0f172a;
    --navy-light:#1e293b;
  }
  body {
    font-family:'Outfit', sans-serif;
    background:#f8fafc;
    color:#1e293b;
    line-height:1.7;
  }
  header {
    background:linear-gradient(135deg, var(--navy), var(--navy-light));
    padding:18px 5%;
    display:flex;
    align-items:center;
    justify-content:space-between;
  }
  .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .nav-brand img { width:34px; height:34px; border-radius:8px; background:#fff; padding:3px; object-fit:contain; }
  .nav-brand-name { font-size:16px; font-weight:800; color:#fff; letter-spacing:.5px; }
  .nav-brand-name span { color:var(--accent); }
  .back-link {
    color:rgba(255,255,255,0.75); text-decoration:none; font-size:13px; font-weight:600;
    display:flex; align-items:center; gap:6px; transition:color .2s ease;
  }
  .back-link:hover { color:var(--accent-light); }

  .wrap { max-width:820px; margin:0 auto; padding:48px 24px 80px; }
  .page-title { font-size:clamp(28px,4vw,38px); font-weight:800; color:var(--navy); margin-bottom:8px; }
  .page-updated { font-size:13px; color:#64748b; margin-bottom:36px; }

  .intro-box {
    background:#fff7e6; border:1px solid #fde68a; border-radius:14px;
    padding:18px 20px; margin-bottom:36px; font-size:14px; color:#78350f;
  }

  section { margin-bottom:32px; }
  h2 {
    font-size:19px; font-weight:800; color:var(--navy);
    margin-bottom:12px; display:flex; align-items:center; gap:10px;
  }
  h2 .num {
    width:28px; height:28px; border-radius:8px;
    background:linear-gradient(135deg,var(--accent),var(--accent-dark));
    color:#fff; font-size:13px; display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  p, li { font-size:14.5px; color:#334155; }
  ul { padding-left:20px; margin-top:8px; }
  li { margin-bottom:6px; }
  strong { color:var(--navy); }

  .contact-box {
    background:var(--navy); color:#fff; border-radius:16px;
    padding:24px; margin-top:40px;
  }
  .contact-box h3 { font-size:16px; font-weight:800; margin-bottom:8px; color:var(--accent-light); }
  .contact-box p { color:rgba(255,255,255,0.75); font-size:13.5px; }

  footer.site-footer {
    background:var(--navy); color:rgba(255,255,255,0.5);
    text-align:center; padding:24px; font-size:12.5px;
  }
  footer.site-footer a { color:var(--accent-light); text-decoration:none; }
</style>
</head>
<body>

<header>
  <a href="home.php" class="nav-brand">
    <img src="assets/images/logo.png" alt="Fix It Davao Logo">
    <span class="nav-brand-name">Fix It <span>Davao</span></span>
  </a>
  <a href="home.php" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to Home
  </a>
</header>

<div class="wrap">
  <h1 class="page-title">Terms of Service</h1>
  <p class="page-updated">Last updated: August 2026</p>

  <div class="intro-box">
    By creating an account or using Fix It Davao, you agree to these Terms of Service. Please read them
    carefully. If you do not agree, please do not use the platform.
  </div>

  <section>
    <h2><span class="num">1</span> What Fix It Davao Is</h2>
    <p>Fix It Davao is a booking platform that connects customers in Davao City with independent, local
    repair shops for computer and gadget repair services. We facilitate the booking, communication, and
    coordination between customers and shops — we are <strong>not</strong> a repair shop ourselves and do
    not perform repairs directly.</p>
  </section>

  <section>
    <h2><span class="num">2</span> Accounts &amp; Eligibility</h2>
    <ul>
      <li>You must provide accurate and complete information when creating an account</li>
      <li>You are responsible for keeping your login credentials and OTP-linked device secure</li>
      <li>One account per person; shop owners must register using their actual business/shop details</li>
      <li>We reserve the right to suspend accounts that violate these Terms or provide false information</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">3</span> Bookings</h2>
    <ul>
      <li>Bookings made through the platform are a request to a shop, subject to that shop's confirmation</li>
      <li>Shops may cancel or reschedule bookings when necessary (e.g., unavailability, parts issue)</li>
      <li>Customers may cancel or reschedule bookings that are still pending or confirmed</li>
      <li>Repeated no-shows or unclaimed devices may result in booking restrictions</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">4</span> Payments</h2>
    <ul>
      <li>Payment for repair services is settled directly between the customer and the shop</li>
      <li>Shop owners pay a subscription fee to the platform via GCash or bank transfer, verified through
        submitted proof of payment</li>
      <li>Fix It Davao is not responsible for disputes over the quality, pricing, or outcome of a repair —
        these are between the customer and the shop</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">5</span> Reviews &amp; Conduct</h2>
    <ul>
      <li>Reviews must reflect genuine experiences with a shop's service</li>
      <li>Harassment, false reviews, spam, or abusive messages toward other users are not allowed</li>
      <li>We may remove content or suspend accounts that violate this policy</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">6</span> Limitation of Liability</h2>
    <p>Fix It Davao provides the platform "as is." We do our best to keep the service reliable and secure,
    but we do not guarantee uninterrupted availability and are not liable for losses arising from repair
    outcomes, third-party shop actions, or matters outside our direct control.</p>
  </section>

  <section>
    <h2><span class="num">7</span> Changes to These Terms</h2>
    <p>We may update these Terms from time to time. Continued use of the platform after changes are posted
    means you accept the updated Terms.</p>
  </section>

  <section>
    <h2><span class="num">8</span> Related Policy</h2>
    <p>For details on how we collect, use, and protect your personal data, please see our
    <a href="privacy-policy.php" style="color:var(--accent-dark);font-weight:600;">Privacy Policy</a>.</p>
  </section>

  <div class="contact-box">
    <h3>Questions?</h3>
    <p>If you have questions about these Terms, please reach out through the
    <a href="developers.php" style="color:var(--accent-light);">Developers page</a>.</p>
  </div>
</div>

<footer class="site-footer">
  © 2026 Fix It Davao. All rights reserved. &nbsp;|&nbsp; <a href="home.php">Home</a>
</footer>

</body>
</html>