<?php /** CMS portal page shell (bottom). */ ?>
  </main>
<?php if (current_superuser() && empty($cmsBare)): ?>
</div><!-- /.cms-shell -->
<?php endif; ?>
<footer class="cms-foot">
  <span>Bosket's Alimentos — CMS Portal</span>
  <span>Signed-in changes here affect the live website.</span>
</footer>
<script src="<?= e(cms_url('assets/cms.js')) ?>"></script>
</body>
</html>
