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
          <?php // The mark links home, the way the masthead's wordmark does.
                // The image itself is decorative (the wordmark under it is the
                // group's accessible name), so a link wrapping it alone would
                // have NO accessible name -- hence the aria-label, matching the
                // masthead's wording. ?>
          <a class="foot-mark-link" href="<?= e($basePath) ?>/" aria-label="<?= e($site['name'] ?? '') ?> home">
            <img class="foot-mark" src="<?= e(asset_url('/img/brand/leo-mark-lion.png', $basePath)) ?>"
                 alt="" aria-hidden="true" width="520" height="380" loading="lazy">
          </a>
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

<?php // Back to top. It ships hidden and the script un-hides it only on a
      // page that runs past one full screen, so a page that does not scroll
      // never carries one -- and with JavaScript off nothing appears at all,
      // rather than a control that cannot know where you are. A real link to
      // the #top fragment rather than a button, so it still works if the
      // handler never binds. Last in the document on purpose: a keyboard
      // user reaches it after the page, which is when it is useful. The name
      // is text, not an aria-label, so it survives translation; the arrow
      // itself is decorative. ?>
<?php // A bare fragment, NOT basePath-prefixed: "#top" resolves against the
      // current URL, so it is the top of whatever page you are on. Prefixing
      // it would make it the HOMEPAGE plus a fragment under the
      // /~leofoundationusa temporary URL -- a back-to-top control that
      // navigates away. ?>
<a class="to-top" href="#top" hidden>
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
      <path d="M12 19V7M6 13l6-6 6 6" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
    <span class="visually-hidden">Back to top</span>
</a>

<script src="<?= e(asset_url('/js/site.js', $basePath)) ?>" defer></script>

</body>
</html>
