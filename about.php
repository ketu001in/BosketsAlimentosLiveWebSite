<?php
/** About Us — brand story + photo gallery. */
require_once __DIR__ . '/includes/bootstrap.php';
ensure_gallery_table();

$galleryImages = db()->query("SELECT image, caption FROM gallery ORDER BY sort_order, id LIMIT 200")->fetchAll();

$pageTitle = 'About Us';
$pageDesc  = "The story of Bosket's Alimentos — curated fusion vegetarian cuisine and a professional platform for home chefs to shine.";
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

  <div class="panel" style="padding:34px 38px">
    <h2>Our Story</h2>
    <p>Welcome to Bosket's Alimentos, where passion meets the plate and tradition finds a new voice.</p>
    <p>Our journey began with a simple but profound love for food. Founded by <strong>Boskey ("Bos")</strong> and
    <strong>Ketul ("Ket")</strong>, a creative and food-enthusiast couple, our story is one of exploration, heritage,
    and culinary innovation.</p>
    <p>Moving from the vibrant, flavor-rich state of Gujarat to the bustling gastronomic hub of Bangalore,
    we brought with us a deep appreciation for authentic regional cuisines. Our relentless passion for
    cooking and experimenting soon caught the attention of Bangalore's culinary community, leading us
    to win several prestigious culinary titles. These accolades were more than just awards; they were
    the spark that fueled our desire to explore a bold, new idea and share it with the world.</p>
  </div>

  <div class="panel" style="padding:34px 38px;margin-top:24px">
    <h2>Our Culinary Canvas</h2>
    <p>At the heart of Bosket's Alimentos is our unique USP:
    <strong style="color:var(--green-700)">Curated Fusion Vegetarian Cuisine</strong>.
    We draw deep inspiration from traditional Gujarati recipes and other regional Indian delicacies,
    carefully deconstructing and reimagining them. By adding meaningful, contemporary twists, we create
    entirely unique and original fusion dishes. We don't just mix ingredients; we weave stories from
    different culinary cultures to plate something beautifully familiar yet thrillingly new.</p>
  </div>

  <div class="panel" style="padding:38px;margin-top:24px;text-align:center;background:linear-gradient(135deg,#1b4b43 0%,#23756a 100%);border:0">
    <h2 style="color:#8ccdc1;font-size:18px;letter-spacing:.14em;text-transform:uppercase;font-family:var(--font-body);font-weight:600">The Big Idea</h2>
    <p style="font-family:var(--font-display);font-style:italic;font-size:clamp(20px,3vw,26px);color:#fff;line-height:1.5;max-width:620px;margin:14px auto">
      &ldquo;Empowering culinary creativity by providing a professional platform for
      <em style="color:#f2bca8;font-style:italic">Home Chefs</em> to shine.&rdquo;
    </p>
    <p style="color:#cfe5e0;max-width:640px;margin:18px auto 0">Bosket's Alimentos is more than just our own
    culinary venture — it is a movement. We recognized that millions of homes across India harbor
    incredible, untapped culinary talent. Our grand vision is to connect as many passionate Home Chefs
    and Cooks as possible. We provide them with a professional stage to showcase their creativity,
    share their heritage, and elevate vegetarian food to a global standard.</p>
  </div>

  <div class="grid" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
    <div class="panel" style="padding:30px 32px;border-top:4px solid var(--green-600);border-radius:0 0 var(--radius) var(--radius)">
      <h2 style="font-size:24px">Our Vision</h2>
      <p style="margin-bottom:0">To revolutionize the vegetarian culinary landscape by making innovative
      fusion food an everyday delight, while establishing India's premier community-driven platform
      that celebrates and elevates undiscovered home culinary talent.</p>
    </div>
    <div class="panel" style="padding:30px 32px;border-top:4px solid var(--orange-500);border-radius:0 0 var(--radius) var(--radius)">
      <h2 style="font-size:24px">Our Mission</h2>
      <p style="margin-bottom:0">To curate extraordinary, heritage-inspired vegetarian fusion experiences,
      and to empower passionate home chefs by providing them with the professional tools, platform,
      and visibility needed to turn their culinary art into a celebrated profession.</p>
    </div>
  </div>

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
        <div class="gallery-item" onclick="glbOpen('<?= e(url($img['image'])) ?>','<?= e(addslashes($img['caption'] ?? '')) ?>')">
          <img src="<?= e(url($img['image'])) ?>" alt="<?= e($img['caption'] ?? '') ?>" loading="lazy">
          <?php if ($img['caption']): ?><div class="gallery-caption"><?= e($img['caption']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<div class="glb" id="glb" onclick="if(event.target===this)glbClose()">
  <button class="glb-close" onclick="glbClose()">&times;</button>
  <img src="" id="glb-img" alt="">
</div>
<script>
function glbOpen(src,alt){var lb=document.getElementById('glb');document.getElementById('glb-img').src=src;document.getElementById('glb-img').alt=alt;lb.classList.add('open');document.body.style.overflow='hidden';}
function glbClose(){document.getElementById('glb').classList.remove('open');document.body.style.overflow='';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')glbClose();});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
