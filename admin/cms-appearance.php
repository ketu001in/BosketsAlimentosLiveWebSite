<?php
/** Admin: site appearance — fonts, colour scheme, dark-mode toggle. */
require_once __DIR__ . '/_admin.php';
ensure_cms_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $set = function(string $k, string $v) use ($pdo) {
        $pdo->prepare("INSERT INTO cms_settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?")->execute([$k,$v,$v]);
    };
    $set('theme_default',  in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light');
    $set('visitor_toggle', !empty($_POST['visitor_toggle']) ? '1' : '0');
    $set('font_pairing',   $_POST['font_pairing'] ?? 'playfair_inter');
    // Custom colours
    $set('color_green_700', ltrim($_POST['color_green_700'] ?? '', '#') ? '#' . ltrim($_POST['color_green_700'], '#') : '');
    $set('color_orange_500', ltrim($_POST['color_orange_500'] ?? '', '#') ? '#' . ltrim($_POST['color_orange_500'], '#') : '');
    flash('success', 'Appearance settings saved.');
    redirect('admin/cms-appearance.php');
}

$s = function(string $k, string $d = '') {
    $st = db()->prepare("SELECT value FROM cms_settings WHERE name=?");
    $st->execute([$k]);
    return $st->fetchColumn() ?: $d;
};

$pairings = [
    'playfair_inter'  => 'Playfair Display + Inter (default)',
    'lato_lato'       => 'Lato (clean, modern)',
    'merriweather_open' => 'Merriweather + Open Sans (editorial)',
    'roboto_roboto'   => 'Roboto (minimal)',
    'montserrat_roboto' => 'Montserrat + Roboto (bold & clean)',
];

$pageTitle = 'Admin · Appearance';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section" style="max-width:700px">
  <h1>🎨 Appearance</h1>
  <?php admin_nav('cms-appearance'); ?>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>

    <div class="panel">
      <h3>Theme &amp; Dark Mode</h3>
      <label class="field">Default theme
        <select name="theme">
          <option value="light" <?= $s('theme_default','light') === 'light' ? 'selected' : '' ?>>Light</option>
          <option value="dark"  <?= $s('theme_default','light') === 'dark'  ? 'selected' : '' ?>>Dark</option>
        </select>
      </label>
      <label style="display:flex;align-items:center;gap:10px;margin-top:14px;cursor:pointer">
        <input type="checkbox" name="visitor_toggle" value="1" <?= $s('visitor_toggle','1') === '1' ? 'checked' : '' ?>>
        <span>Show dark/light toggle button to visitors</span>
      </label>
    </div>

    <div class="panel">
      <h3>Font pairing</h3>
      <label class="field">
        <select name="font_pairing">
          <?php foreach ($pairings as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $s('font_pairing','playfair_inter') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="panel">
      <h3>Brand colours <small style="font-weight:400">(leave blank to use defaults)</small></h3>
      <div class="form-row">
        <label class="field">Primary green
          <input type="color" name="color_green_700" value="<?= e($s('color_green_700','#1f6e43')) ?>">
        </label>
        <label class="field">Accent orange
          <input type="color" name="color_orange_500" value="<?= e($s('color_orange_500','#e26a1f')) ?>">
        </label>
      </div>
    </div>

    <div><button class="btn btn-primary" type="submit">💾 Save appearance</button></div>
  </form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
