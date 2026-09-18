</main>

<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <?php // The mark is centred over the wordmark rather than ranged left
              // with it, so the pair reads as one stacked sign-off. Centring
              // needs a box the width of the wordmark to centre inside -- the
              // footer column is a 1fr track and is wider -- which is what this
              // wrapper is for. Sizing the mark then stays one height value. ?>
        <div class="foot-sign">
          <img class="foot-mark" src="<?= e(asset_url('/img/brand/leo-mark-lion.png', $basePath)) ?>"
               alt="" aria-hidden="true" width="520" height="380" loading="lazy">
          <?php // The wordmark below is the accessible name of the group, so
                // the mark above it is decorative -- otherwise both announce the
                // same thing. The artwork is cropped to the name and its two
                // rules; the strapline it used to carry is set in type
                // underneath, the way the masthead does it, so it stays sharp
                // and can be restyled in CSS. ?>
          <img class="foot-lockup" src="<?= e(asset_url('/img/brand/leo-wordmark-footer.png', $basePath)) ?>"
               alt="<?= e($site['name'] ?? '') ?>"
               width="679" height="101" loading="lazy">
          <span class="foot-strap">Leadership &middot; Education &middot; Opportunity</span>
        </div>
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

        <?php // Legal sits under Contact rather than in its own column: it is
              // one or two links and a fourth column would leave the row
              // lopsided. The list is whatever pages carry `legal: true`, so a
              // terms of service appears here the moment one is written, with
              // no change to this file. ?>
        <?php if ($legalPages !== []): ?>
          <h4 class="foot-legal-head">Legal</h4>
          <ul>
            <?php foreach ($legalPages as $legalPage): ?>
              <li><a href="<?= e($basePath) ?>/<?= e($legalPage['slug'] ?? '') ?>"><?= e($legalPage['navLabel'] ?? $legalPage['title'] ?? '') ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="foot-legal">
      <?= e($site['legalName'] ?? $site['name'] ?? '') ?> is a 501(c)(3) nonprofit organization<?php if (!empty($site['ein'])): ?>, EIN <?= e($site['ein']) ?><?php endif; ?>.
      Contributions are tax deductible to the extent allowed by law.
      <a href="<?= e($basePath) ?>/admin" style="float:right">Staff sign in</a>
    </div>
  </div>
</footer>

<script src="<?= e(asset_url('/js/site.js', $basePath)) ?>" defer></script>

</body>
</html>
