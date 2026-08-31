</main>

<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <img class="foot-mark" src="<?= e(link_url('/img/brand/leo-mark-lion.png', $basePath)) ?>"
             alt="<?= e($site['name'] ?? '') ?>" width="520" height="380" loading="lazy">
        <p class="small"><?= e($site['mission'] ?? '') ?></p>
      </div>
      <div>
        <h4>Scholarships</h4>
        <ul>
          <li><a href="<?= e($basePath) ?>/scholarships">Available scholarships</a></li>
          <li><a href="<?= e($basePath) ?>/recipients">Scholarship recipients</a></li>
          <?php // Flattened, so a page nested under a dropdown still appears here. ?>
          <?php foreach ($navFlat as $navPage): ?>
            <li><a href="<?= e($basePath) ?>/<?= e($navPage['slug'] ?? '') ?>"><?= e($navPage['navLabel'] ?? $navPage['title'] ?? '') ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul>
          <?php if (!empty($site['email'])): ?><li><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></li><?php endif; ?>
          <?php if (!empty($site['phone'])): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $site['phone'])) ?>"><?= e($site['phone']) ?></a></li><?php endif; ?>
          <?php if (!empty($site['location'])): ?><li><?= e($site['location']) ?></li><?php endif; ?>
          <?php if (!empty($site['facebookUrl'])): ?><li><a href="<?= e(link_url($site['facebookUrl'], $basePath)) ?>">Facebook</a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="foot-legal">
      <?= e($site['legalName'] ?? $site['name'] ?? '') ?> is a 501(c)(3) nonprofit organization<?php if (!empty($site['ein'])): ?>, EIN <?= e($site['ein']) ?><?php endif; ?>.
      Contributions are tax deductible to the extent allowed by law.
      <a href="<?= e($basePath) ?>/admin" style="float:right">Staff sign in</a>
    </div>
  </div>
</footer>

<script src="<?= e($basePath) ?>/js/site.js" defer></script>

</body>
</html>
