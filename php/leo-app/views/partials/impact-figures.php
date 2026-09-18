<?php
// The impact figures -- the four panels the homepage carries in its navy band.
//
// Extracted so a page can carry the same figures under its copy without a second
// copy of the markup drifting from the first. The homepage includes it inside
// its own `section.impact`, under the heading; a page record that sets
// `impactFigures: true` gets it inside the copy column, wrapped in
// `.impact.impact-inline` -- the `.impact` ancestor is what the gold numerals and
// the white labels are scoped to, and the inline variant drops the band's
// padding, its border and its watermark.
//
// The numbers are the counter targets transcribed from the live site; they live
// in `site.impact` and are editable in `/admin` under settings.
//
// Mirrored in impact-figures.ejs.
?>
<div class="impact-grid">
  <?php foreach ($impact as $item): ?>
    <div>
      <div class="value"><?= e($item['value'] ?? '') ?></div>
      <div class="label"><?= e($item['label'] ?? '') ?></div>
      <?php if (!empty($item['detail'])): ?>
        <p class="detail"><?= e($item['detail']) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
