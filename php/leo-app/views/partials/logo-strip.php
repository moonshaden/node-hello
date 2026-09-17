<?php
// The donor and partner marquee. Shared by the homepage and by any page whose
// record sets `logoStrip: true`, so the two never drift.
//
// The track holds the set twice: the animation travels exactly half its width
// and restarts, which is what makes the loop seamless rather than snapping
// back. The second copy is aria-hidden so a screen reader is not read the same
// logos again.
//
// No names. The live site publishes no alt text on any of these and the files
// are called things like "13-1.png", so nothing published says who they are.
// Each logo is decorative and the strip itself carries the label.
//
// `large` is the variant: fewer, bigger marks, for a page with room for them.
// It is a class, not a second copy of this markup.
$large = ($stripSize ?? '') === 'large';
?>
<section class="band logo-strip<?= $large ? ' is-large' : '' ?>" aria-label="Our donors and partners">
  <div class="logo-track">
    <ul class="logo-run">
      <?php foreach ($logos as $logo): ?>
        <li><img src="<?= e(link_url($logo['src'] ?? '', $basePath)) ?>" alt="<?= e($logo['alt'] ?? '') ?>" width="200" height="200" loading="lazy"></li>
      <?php endforeach; ?>
    </ul>
    <ul class="logo-run" aria-hidden="true">
      <?php foreach ($logos as $logo): ?>
        <li><img src="<?= e(link_url($logo['src'] ?? '', $basePath)) ?>" alt="" width="200" height="200" loading="lazy"></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
