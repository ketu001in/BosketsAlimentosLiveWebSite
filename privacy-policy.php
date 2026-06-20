<?php
/** Privacy Policy — Bosket's Alimentos */
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy Policy';
$pageDesc  = "Privacy Policy for Bosket's Alimentos — how we collect, use and protect your personal data on our website and mobile app.";
$noIndex   = false;
include __DIR__ . '/includes/header.php';

$lastUpdated  = 'June 20, 2026';
$contactEmail = 'privacy@bosketsalimentos.com';
$siteUrl      = 'https://www.bosketsalimentos.com';
?>

<section class="hero" style="padding:52px 0 58px">
  <div class="container" style="text-align:center">
    <h1 style="font-size:clamp(28px,4vw,42px)">Privacy Policy</h1>
    <p style="color:#cfe5e0;font-size:15px;margin:10px 0 0">
      Bosket's Alimentos &nbsp;·&nbsp; Effective date: <?= $lastUpdated ?>
    </p>
  </div>
</section>

<div class="container section section-narrow" style="max-width:820px">

  <div class="panel" style="padding:38px 44px;line-height:1.85;font-size:15px">

    <p style="background:var(--green-50);border-left:4px solid var(--green-600);padding:14px 18px;border-radius:0 8px 8px 0;margin-bottom:28px">
      This Privacy Policy describes how <strong>Bosket's Alimentos</strong> ("<strong>we</strong>", "<strong>us</strong>",
      "<strong>our</strong>") collects, uses and protects your personal information when you use our website
      (<a href="<?= $siteUrl ?>"><?= $siteUrl ?></a>) and our mobile application
      ("<strong>the App</strong>"). By using our services you agree to the terms of this policy.
    </p>

    <!-- 1 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      1. Who We Are
    </h2>
    <p>
      Bosket's Alimentos is a 100% vegetarian fusion food community platform founded by
      <strong>Boskey and Ketul</strong>, based in Bangalore, India. We operate both a web platform and
      an Android mobile application dedicated to sharing vegetarian fusion recipes, food stories and
      culinary discussions.
    </p>
    <p>
      <strong>Data Controller:</strong> Bosket's Alimentos<br>
      <strong>Contact:</strong> <a href="mailto:<?= $contactEmail ?>"><?= $contactEmail ?></a><br>
      <strong>Website:</strong> <a href="<?= $siteUrl ?>"><?= $siteUrl ?></a>
    </p>

    <!-- 2 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      2. Information We Collect
    </h2>

    <h3 style="font-size:16px;margin-top:20px">2.1 Information You Provide Directly</h3>
    <ul>
      <li><strong>Account information</strong> — username, display name, email address and password (stored as a secure one-way hash)</li>
      <li><strong>Profile information</strong> — bio text and optional profile picture (avatar)</li>
      <li><strong>Content you post</strong> — recipes, ingredients, step-by-step instructions, photos, videos, story text and verdict notes</li>
      <li><strong>Community activity</strong> — comments, reactions (like, love, yum, wow), wall posts, forum topics and replies</li>
      <li><strong>Messages</strong> — private one-on-one messages exchanged with other members</li>
      <li><strong>Contact form submissions</strong> — name, email, subject and message when you contact us</li>
    </ul>

    <h3 style="font-size:16px;margin-top:20px">2.2 Information Collected Automatically</h3>
    <ul>
      <li><strong>Session data</strong> — we use server-side PHP sessions to keep you signed in; a session cookie is stored in your browser</li>
      <li><strong>Usage data</strong> — recipe view counts are tracked in aggregate (no individual browsing history is stored)</li>
      <li><strong>Device and browser information</strong> — basic browser type and operating system, collected via standard web server logs</li>
      <li><strong>IP address</strong> — collected by our web server for security and abuse prevention; not linked to your profile</li>
    </ul>

    <h3 style="font-size:16px;margin-top:20px">2.3 Information from Third Parties (Optional)</h3>
    <p>
      If you choose to register or sign in using <strong>Google</strong> or <strong>Facebook</strong>, we receive
      your name and email address from those services to create or link your account. We do not receive
      your social media passwords. Use of these sign-in methods is entirely optional.
    </p>

    <!-- 3 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      3. How We Use Your Information
    </h2>
    <ul>
      <li>To create and manage your account and authenticate your identity</li>
      <li>To display your profile, recipes and community contributions on the platform</li>
      <li>To send you in-app notifications (reactions, comments, new recipes from buddies, platform announcements)</li>
      <li>To send email notifications if you opt in to recipe updates (you can unsubscribe at any time)</li>
      <li>To moderate content and maintain a safe, respectful community</li>
      <li>To respond to contact form enquiries</li>
      <li>To improve our platform features and user experience</li>
      <li>To comply with our legal obligations</li>
    </ul>
    <p>We do <strong>not</strong> sell, rent or trade your personal data to any third party for marketing purposes.</p>

    <!-- 4 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      4. Cookies
    </h2>
    <p>We use only <strong>essential cookies</strong>:</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
      <tr style="background:var(--green-50)">
        <th style="padding:10px 14px;text-align:left;border:1px solid var(--line)">Cookie Name</th>
        <th style="padding:10px 14px;text-align:left;border:1px solid var(--line)">Purpose</th>
        <th style="padding:10px 14px;text-align:left;border:1px solid var(--line)">Duration</th>
      </tr>
      <tr>
        <td style="padding:9px 14px;border:1px solid var(--line)"><code>boskets_sid</code></td>
        <td style="padding:9px 14px;border:1px solid var(--line)">Keeps you signed in during your browser session</td>
        <td style="padding:9px 14px;border:1px solid var(--line)">Session (deleted when browser closes)</td>
      </tr>
      <tr>
        <td style="padding:9px 14px;border:1px solid var(--line)"><code>boskets-theme</code></td>
        <td style="padding:9px 14px;border:1px solid var(--line)">Remembers your light/dark theme preference</td>
        <td style="padding:9px 14px;border:1px solid var(--line)">Persistent (local storage)</td>
      </tr>
    </table>
    <p style="margin-top:12px">
      We do <strong>not</strong> use advertising cookies, analytics tracking cookies or any third-party
      marketing cookies. Web fonts (Google Fonts) are loaded from Google's CDN, which may set its own cookies
      subject to <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.
    </p>

    <!-- 5 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      5. Mobile Application (Android App)
    </h2>
    <p>
      The Bosket's Alimentos Android App is a mobile wrapper for our website. When you use the App:
    </p>
    <ul>
      <li>All website features are available through the App's built-in browser (WebView)</li>
      <li>The App does <strong>not</strong> access your device's camera, microphone, contacts, location or files independently</li>
      <li>Any photos you upload are submitted through the website's standard upload forms</li>
      <li>The App does <strong>not</strong> collect any data that is not already described in this policy</li>
      <li>The App does <strong>not</strong> display advertisements</li>
      <li>Internet access is required to use the App</li>
    </ul>

    <!-- 6 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      6. How We Share Your Information
    </h2>
    <p>We share your information only in the following limited circumstances:</p>
    <ul>
      <li><strong>With other members (publicly):</strong> your username, display name, avatar, recipes, comments, wall posts and forum topics are visible to all site visitors by default</li>
      <li><strong>With our hosting provider:</strong> Hostinger (hostinger.com) hosts our servers in accordance with their own Privacy Policy</li>
      <li><strong>With Google / Facebook:</strong> only if you choose to use social sign-in; governed by their respective privacy policies</li>
      <li><strong>If required by law:</strong> if we are legally required to disclose information to comply with applicable law or legal process</li>
    </ul>
    <p>We do <strong>not</strong> sell or share your personal data with advertisers or data brokers.</p>

    <!-- 7 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      7. Data Retention
    </h2>
    <p>
      We retain your personal data for as long as your account remains active. If you delete your account,
      your profile, recipes, comments and personal data are permanently deleted from our database.
      Contact form messages are retained for up to 12 months for correspondence purposes.
      Server access logs are retained for up to 30 days for security purposes.
    </p>

    <!-- 8 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      8. Your Rights
    </h2>
    <p>You have the right to:</p>
    <ul>
      <li><strong>Access</strong> — request a copy of the personal data we hold about you</li>
      <li><strong>Rectification</strong> — update or correct your data at any time via Account Settings</li>
      <li><strong>Deletion</strong> — delete your account and all associated data via Account Settings → Delete Account</li>
      <li><strong>Opt-out</strong> — unsubscribe from email notifications at any time via Account Settings or the unsubscribe link in any email</li>
      <li><strong>Portability</strong> — request an export of your data by contacting us</li>
    </ul>
    <p>
      To exercise any of these rights, contact us at
      <a href="mailto:<?= $contactEmail ?>"><?= $contactEmail ?></a>.
      We will respond within 30 days.
    </p>

    <!-- 9 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      9. Security
    </h2>
    <p>
      We take reasonable measures to protect your personal data including:
    </p>
    <ul>
      <li>All passwords are stored using secure one-way bcrypt hashing — we cannot read your password</li>
      <li>The website uses HTTPS (TLS encryption) for all data in transit</li>
      <li>Access to the admin panel is restricted to authorised administrators only</li>
      <li>Sensitive configuration files are protected from public web access via server rules</li>
    </ul>
    <p>
      No method of transmission over the internet is 100% secure. While we strive to protect your data,
      we cannot guarantee absolute security.
    </p>

    <!-- 10 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      10. Children's Privacy
    </h2>
    <p>
      Our platform is not directed at children under the age of <strong>13</strong>. We do not knowingly
      collect personal data from children under 13. If you believe a child under 13 has provided us with
      personal information, please contact us and we will delete it promptly.
    </p>

    <!-- 11 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      11. Third-Party Links
    </h2>
    <p>
      Our platform may contain links to external websites (e.g. YouTube videos, social sharing).
      We are not responsible for the privacy practices of those sites and encourage you to review
      their privacy policies.
    </p>

    <!-- 12 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      12. Changes to This Policy
    </h2>
    <p>
      We may update this Privacy Policy from time to time. When we do, we will revise the
      "Effective date" at the top of this page. We encourage you to review this policy periodically.
      Continued use of our services after changes are posted constitutes your acceptance of the updated policy.
    </p>

    <!-- 13 -->
    <h2 style="font-size:20px;margin-top:36px;border-bottom:2px solid var(--line);padding-bottom:8px">
      13. Contact Us
    </h2>
    <p>
      If you have any questions, concerns or requests regarding this Privacy Policy or your personal data,
      please contact us:
    </p>
    <div style="background:var(--green-50);padding:18px 22px;border-radius:10px;margin-top:8px">
      <strong>Bosket's Alimentos</strong><br>
      Bangalore, Karnataka, India<br>
      Email: <a href="mailto:<?= $contactEmail ?>"><?= $contactEmail ?></a><br>
      Website: <a href="<?= $siteUrl ?>/contact.php"><?= $siteUrl ?>/contact.php</a>
    </div>

    <p style="margin-top:32px;padding-top:20px;border-top:1px solid var(--line);color:var(--ink-soft);font-size:13px;text-align:center">
      &copy; <?= date('Y') ?> Bosket's Alimentos. This Privacy Policy was prepared exclusively by and for
      Bosket's Alimentos. All rights reserved.
    </p>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
