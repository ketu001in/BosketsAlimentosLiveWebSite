</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <strong style="display:flex;align-items:center;gap:10px">
        <svg viewBox="0 0 100 100" width="34" height="34" aria-hidden="true">
          <circle cx="50" cy="50" r="46" fill="none" stroke="#b7d2cc" stroke-width="3"/>
          <path d="M50 26 C39 40 39 58 50 74 C61 58 61 40 50 26 Z" fill="#3fa796"/>
        </svg>
        Bosket's Alimentos
      </strong>
      <p><?= e(SITE_TAGLINE) ?> — a 100% vegetarian community where fusion food lovers share recipes, stories and conversations.</p>
    </div>
    <div class="footer-links">
      <h4>Explore</h4>
      <a href="<?= e(url('recipes.php')) ?>">All Recipes</a>
      <a href="<?= e(url('forum.php')) ?>">Forum</a>
      <a href="<?= e(url('about.php')) ?>">About Us</a>
      <a href="<?= e(url('contact.php')) ?>">Contact Us</a>
      <a href="<?= e(url('register.php')) ?>">Become a Member</a>
    </div>
    <div class="footer-links">
      <h4>Community</h4>
      <a href="<?= e(url('post-recipe.php')) ?>">Post a New Recipe</a>
      <a href="<?= e(url('buddies.php')) ?>">Find Buddies</a>
      <a href="<?= e(url('messages.php')) ?>">Messages</a>
      <a href="<?= e(url('feed.php')) ?>">My Feed</a>
    </div>
    <?php if (function_exists('cms_footer_items_html')) { echo cms_footer_items_html(); } /* CMS: SuperUser footer links */ ?>
  </div>
  <div class="footer-base">© <?= date('Y') ?> <?= e(SITE_NAME) ?> · Made with 💚 for vegetarian fusion food &nbsp;·&nbsp; <a href="<?= e(url('privacy-policy.php')) ?>" style="color:inherit;opacity:.7">Privacy Policy</a></div>
</footer>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>
