<?php
// One program on the Programs & Partnerships page.
//
// Mirrors views/partials/program-section.ejs. Same image-beside-text row the
// board lead uses, alternating side so three of them do not read as one column
// of repeats. The copy is markdown so the two partner links stay editable in
// /admin rather than being baked into the template.
$isFlipped = !empty($flip);
?>
<article class="card program<?= $isFlipped ? ' program-flip' : '' ?>">
  <?php if (!empty($program['photoUrl'])): ?>
    <img class="program-photo" src="<?= e(link_url($program['photoUrl'], $basePath)) ?>" alt="<?= e($program['alt'] ?? $program['name'] ?? '') ?>" loading="lazy" width="800" height="800">
  <?php endif; ?>
  <h3 class="program-name" id="<?= e($program['slug'] ?? '') ?>"><?= e($program['name'] ?? '') ?></h3>
  <div class="program-body"><?= md($program['body'] ?? '') ?></div>
</article>
