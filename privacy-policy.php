<?php
// privacy-policy.php
// Public page — no auth required.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy - Fix It Davao</title>
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

  table {
    width:100%; border-collapse:collapse; margin-top:14px; font-size:13.5px;
    border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;
  }
  th, td { text-align:left; padding:10px 14px; border-bottom:1px solid #e2e8f0; }
  th { background:#f1f5f9; color:var(--navy); font-weight:700; }
  tr:last-child td { border-bottom:none; }

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
  <h1 class="page-title">Privacy Policy</h1>
  <p class="page-updated">Last updated: August 2026</p>

  <div class="intro-box">
    Fix It Davao is committed to protecting your personal data in accordance with the
    <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> of the Philippines.
    This policy explains what information we collect, why we collect it, and how we keep it safe.
  </div>

  <section>
    <h2><span class="num">1</span> Information We Collect</h2>
    <p>When you use Fix It Davao, we collect the following categories of personal data:</p>
    <table>
      <tr><th>Category</th><th>Examples</th></tr>
      <tr><td>Account information</td><td>Full name, email address, contact number, password (encrypted)</td></tr>
      <tr><td>Booking details</td><td>Device type/brand, problem description, preferred date and time</td></tr>
      <tr><td>Location data</td><td>Shop location (for shop owners) shown on the map</td></tr>
      <tr><td>Payment records</td><td>GCash/bank transfer reference numbers and payment proof screenshots</td></tr>
      <tr><td>Communication</td><td>Messages exchanged between customers and shop owners</td></tr>
      <tr><td>Usage data</td><td>Reviews, ratings, favorites, and chatbot conversation history</td></tr>
    </table>
  </section>

  <section>
    <h2><span class="num">2</span> Why We Collect It</h2>
    <ul>
      <li>To create and manage your account (customer, shop owner, or admin)</li>
      <li>To process and coordinate repair bookings between customers and shops</li>
      <li>To verify identity through OTP-based two-factor authentication</li>
      <li>To process shop subscription payments and verify proof of payment</li>
      <li>To send booking status updates and notifications</li>
      <li>To improve the platform through the support chatbot</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">3</span> How We Protect Your Data</h2>
    <ul>
      <li>Passwords are hashed using industry-standard encryption (bcrypt) — we never store plain-text passwords</li>
      <li>Sensitive credentials (API keys, mail credentials) are stored in environment files, not in source code</li>
      <li>File uploads (payment screenshots, profile pictures) are validated for type and content before storage</li>
      <li>Accounts are protected with OTP-based two-factor authentication and brute-force lockout protection</li>
      <li>Access to admin and shop-owner data is restricted by role-based authentication</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">4</span> Who Can See Your Data</h2>
    <ul>
      <li><strong>Customers</strong> can see the public profile of shops they interact with (name, location, services, reviews)</li>
      <li><strong>Shop owners</strong> can see the booking details of customers who book with their shop</li>
      <li><strong>Admins</strong> can view account and subscription data for platform moderation and approval purposes</li>
      <li>We do <strong>not</strong> sell, rent, or share your personal data with third parties for marketing purposes</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">5</span> Data Retention</h2>
    <p>We retain your account and booking data for as long as your account remains active. Notification records
    are automatically cleared after 30 days. You may request account deletion by contacting us using the details below.</p>
  </section>

  <section>
    <h2><span class="num">6</span> Your Rights</h2>
    <p>Under the Data Privacy Act of 2012, you have the right to:</p>
    <ul>
      <li>Be informed of how your data is collected and used</li>
      <li>Access the personal data we hold about you</li>
      <li>Request correction of inaccurate data</li>
      <li>Request deletion of your data, subject to legal and legitimate business retention requirements</li>
      <li>Object to the processing of your data</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">7</span> Cookies &amp; Sessions</h2>
    <p>Fix It Davao uses session cookies to keep you logged in and to remember trusted devices for two-factor
    authentication. These are strictly functional and are not used for advertising or third-party tracking.</p>
  </section>

  <section>
    <h2><span class="num">8</span> Changes to This Policy</h2>
    <p>We may update this Privacy Policy from time to time. Material changes will be reflected by updating
    the "Last updated" date above.</p>
  </section>

  <div class="contact-box">
    <h3>Questions or Concerns?</h3>
    <p>If you have questions about this Privacy Policy or how your data is handled, please reach out through the
    <a href="developers.php" style="color:var(--accent-light);">Developers page</a> or contact the Fix It Davao team directly.</p>
  </div>
</div>

<footer class="site-footer">
  © 2026 Fix It Davao. All rights reserved. &nbsp;|&nbsp; <a href="home.php">Home</a>
</footer>

</body>
</html>