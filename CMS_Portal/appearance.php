<?php
/** CMS: appearance — site-wide theme + font pairing (applies to the public site). */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();

$fonts = cms_font_pairings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $theme = $_POST['theme_default'] ?? 'light';
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'light';
    }
    $toggle = !empty($_POST['visitor_toggle']) ? '1' : '0';
    $font = $_POST['font_pairing'] ?? 'playfair_inter';
    if (!isset($fonts[$font])) {
        $font = 'playfair_inter';
    }
    cms_set_setting('theme_default', $theme);
    cms_set_setting('visitor_toggle', $toggle);
    cms_set_setting('font_pairing', $font);
    flash('success', 'Appearance updated — the live site now uses these settings.');
    cms_redirect('appearance.php');
}

$theme   = cms_setting('theme_default', 'light');
$toggle  = cms_setting('visitor_toggle', '1') === '1';
$fontKey = cms_setting('font_pairing', 'playfair_inter');

$cmsPageTitle = 'Appearance';
$cmsActive = 'appearance';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1>Appearance</h1>
    <p class="cms-sub">Control the <strong>public website's</strong> theme and fonts. Changes apply site-wide as soon as you save. (The CMS portal itself keeps its own fixed styling.)</p>
  </div>
  <a class="btn btn-outline btn-sm" href="<?= e(cms_site_url('index.php')) ?>" target="_blank" rel="noopener">Preview site ↗</a>
</div>

<form method="post">
  <?= csrf_field() ?>
  <div class="cms-form-2col">
    <div class="panel">
      <h3 style="margin-top:0">Theme</h3>
      <p class="cms-help" style="margin-top:0">Pick the site's default colour mode. <strong>System</strong> follows each visitor's own device setting.</p>
      <div class="theme-choices">
        <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $val => $lbl): ?>
          <label class="theme-choice <?= $val ?><?= $theme === $val ? ' sel' : '' ?>">
            <input type="radio" name="theme_default" value="<?= $val ?>" <?= $theme === $val ? 'checked' : '' ?>>
            <span class="swatch"></span><?= $lbl ?>
          </label>
        <?php endforeach; ?>
      </div>

      <hr style="border:0;border-top:1px solid var(--line);margin:20px 0">

      <h3>Fonts</h3>
      <p class="cms-help" style="margin-top:0">Choose a professional pairing (heading + body). Applied across the whole site.</p>
      <div class="form-grid" style="gap:10px">
        <?php foreach ($fonts as $key => [$label, $display, $body, $google]): ?>
          <label class="cms-switch" style="justify-content:flex-start">
            <input type="radio" name="font_pairing" value="<?= e($key) ?>" <?= $fontKey === $key ? 'checked' : '' ?> style="display:inline-block;width:auto">
            <span style="font-weight:600"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <h3 style="margin-top:0">Visitor options</h3>
      <label class="cms-switch">
        <input type="checkbox" name="visitor_toggle" value="1" <?= $toggle ? 'checked' : '' ?>>
        <span class="track"></span> Show a light/dark toggle to visitors
      </label>
      <p class="cms-help">When on, visitors see a small ☀️/🌙 button in the header and can flip the theme for themselves (their choice is remembered on their device). Your default above is what everyone sees first.</p>

      <div style="margin-top:20px">
        <button class="btn btn-primary" type="submit">Save appearance</button>
      </div>
      <p class="cms-help" style="margin-top:14px">Tip: after saving, open <em>Preview site</em> to see it live.</p>
    </div>
  </div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
