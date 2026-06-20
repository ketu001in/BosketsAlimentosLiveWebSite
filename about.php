<?php
/** About Us — brand story + photo gallery. */
require_once __DIR__ . '/includes/bootstrap.php';
ensure_gallery_table();

$galleryImages = db()->query("SELECT image, caption FROM gallery ORDER BY sort_order, id LIMIT 200")->fetchAll();
$pageTitle   = 'About Us';
$pageDesc    = "The story of Bosket's Alimentos — curated fusion vegetarian cuisine and a professional platform for home chefs to shine.";
$cmsAbout    = cms_get_page_body('about-us'); // CMS override if admin has edited it
include __DIR__ . '/includes/header.php';
?>
<section class="hero" style="padding:64px 0 70px">
  <div class="container" style="text-align:center">
    <svg viewBox="0 0 100 100" width="84" height="84" aria-hidden="true" style="margin-bottom:18px">
      <circle cx="50" cy="50" r="46.5" fill="none" stroke="#fff" stroke-width="2.2" opacity=".85"/>
      <circle cx="50" cy="50" r="39" fill="none" stroke="#fff" stroke-width=".8" stroke-dasharray="2 4.2" opacity=".6"/>
      <text x="37.5" y="61.5" font-family="'Playfair Display', Georgia, serif" font-size="34" fill="#fff" text-anchor="middle">B</text>
      <text x="61.5" y="61.5" font-family="'Playfair Display', Georgia, serif" font-size="34" fill="#f2bca8" text-anchor="middle">A</text>
      <path d="M50 67.5 C47 71 47 75 50 78.5 C53 75 53 71 50 67.5 Z" fill="#8ccdc1"/>
    </svg>
    <h1 style="max-width:100%;margin-inline:auto">Bosket's Alimentos</h1>
    <p style="margin-inline:auto;font-family:var(--font-display);font-style:italic;font-size:19px">A World of Truly Fusion Food</p>
  </div>
</section>

<div class="container section section-narrow">

<?php if ($cmsAbout): ?>
  <!-- CMS-managed content (editable from Admin → Pages → About Us) -->
  <div class="panel" style="padding:34px 38px">
    <?= $cmsAbout ?>
  </div>
<?php else: ?>
  <!-- Fallback hardcoded content -->
  <div class="panel" style="padding:34px 38px">
    <h2>Our Story</h2>
    <p>Welcome to Bosket's Alimentos, where passion meets the plate and tradition finds a new voice.</p>
    <p>Our journey began with a simple but profound love for food. Founded by <strong>Boskey ("Bos")</strong> and
    <strong>Ketul ("Ket")</strong>, a creative and food-enthusiast couple, our story is one of exploration, heritage,
    and culinary innovation.</p>
    <p>Moving from the vibrant, flavor-rich state of Gujarat to the bustling gastronomic hub of Bangalore,
    we brought with us a deep appreciation for authentic regional cuisines. Our relentless passion for
    cooking and experimenting soon caught the attention of Bangalore's culinary community, leading us
    to win several prestigious culinary titles.</p>
  </div>
  <div class="panel" style="padding:34px 38px;margin-top:24px">
    <h2>Our Culinary Canvas</h2>
    <p>At the heart of Bosket's Alimentos is our unique USP:
    <strong style="color:var(--green-700)">Curated Fusion Vegetarian Cuisine</strong>.
    We draw deep inspiration from traditional Gujarati recipes and other regional Indian delicacies,
    carefully deconstructing and reimagining them.</p>
  </div>
  <div class="grid" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
    <div class="panel" style="padding:30px 32px;border-top:4px solid var(--green-600)">
      <h2 style="font-size:24px">Our Vision</h2>
      <p style="margin-bottom:0">To revolutionize the vegetarian culinary landscape by making innovative fusion food an everyday delight.</p>
    </div>
    <div class="panel" style="padding:30px 32px;border-top:4px solid var(--orange-500)">
      <h2 style="font-size:24px">Our Mission</h2>
      <p style="margin-bottom:0">To curate extraordinary, heritage-inspired vegetarian fusion experiences and empower passionate home chefs.</p>
    </div>
  </div>
<?php endif; ?>

  <p style="text-align:center;margin-top:34px;font-family:var(--font-display);font-style:italic;font-size:17px;color:var(--ink-soft)">
    &copy; Bosket's Alimentos. Bringing tradition and innovation to the table.
  </p>

</div>

<?php if ($galleryImages): ?>
<section class="section" style="background:var(--green-50)">
  <div class="container">
    <div class="section-head"><h2>Our Gallery</h2></div>
    <div class="gallery-masonry">
      <?php foreach ($galleryImages as $img): ?>
        <div class="gallery-item" onclick="glbOpen('<?= e(url($img['image'])) ?>', '<?= e(addslashes($img['caption'] ?? '')) ?>')">
          <img src="<?= e(url($img['image'])) ?>" alt="<?= e($img['caption'] ?? '') ?>" loading="lazy">
          <?php if ($img['caption']): ?><div class="gallery-caption"><?= e($img['caption']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Gallery lightbox -->
<div class="glb" id="glb" onclick="if(event.target===this)glbClose()">
  <button class="glb-close" onclick="glbClose()">&times;</button>
  <img src="" id="glb-img" alt="">
</div>
<script>
function glbOpen(src, alt) {
  var lb = document.getElementById('glb');
  document.getElementById('glb-img').src = src;
  document.getElementById('glb-img').alt = alt;
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function glbClose() {
  document.getElementById('glb').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') glbClose(); });
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
