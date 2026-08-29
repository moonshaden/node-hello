<?php
/**
 * Closing call to action, shared by every content page.
 *
 * Driven by the enrollment window rather than written per page, so it stays
 * right in November without anybody editing it. The giving page gets the
 * donor-facing variant; everything else points at the scholarships.
 */
$isDonate = ($page['slug'] ?? '') === 'donate';
$isOpen = ($enrollment['state'] ?? '') === 'open';
?>
<section class="band tint">
  <div class="wrap">
    <div class="cta-panel">
      <div>
        <?php if ($isDonate): ?>
          <p class="eyebrow">Every gift is a door</p>
          <h2>Ready to support a student?</h2>
          <p>Gifts are tax deductible, and we will help you set up a named or memorial award.</p>
        <?php else: ?>
          <p class="eyebrow"><?= $isOpen ? 'Applications are open' : 'Plan ahead' ?></p>
          <h2><?= $isOpen ? 'Apply for every award you qualify for.' : 'Be ready when applications reopen.' ?></h2>
          <p>
            <?php if ($isOpen): ?>
              The enrollment period closes <?= e(fdate($enrollment['closesOn'] ?? '')) ?>.
            <?php else: ?>
              Applications reopen <?= e(fdate($enrollment['opensOn'] ?? '')) ?>. Review the criteria now so nothing is a surprise.
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="actions">
        <?php if ($isDonate && !empty($site['donateUrl'])): ?>
          <a class="btn btn-gold" href="<?= e($site['donateUrl']) ?>">Donate now</a>
          <a class="btn btn-outline" style="color:#fff" href="<?= e($basePath) ?>/contact">Talk to us first</a>
        <?php else: ?>
          <a class="btn btn-gold" href="<?= e($basePath) ?>/scholarships"><?= $isOpen ? 'Apply for a scholarship' : 'See available scholarships' ?></a>
          <a class="btn btn-outline" style="color:#fff" href="<?= e($basePath) ?>/recipients">Meet our recipients</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
