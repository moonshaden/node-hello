<?php
// A photograph closing the copy on an editable page.
//
// It is an `<img>` rather than a CSS `background-image`, so the src goes through
// `asset_url()` and carries a content hash like every other image on the site. A
// `url()` in the stylesheet would not, and images are served with a year's cache
// -- that is exactly the hole that let a deleted plate sit in browser caches for
// a week.
//
// `picture` is an object on the page record -- `{ src, alt, width, height }` --
// the same shape the programs list and the board roster use, so it survives an
// admin save (`applyFields()` spreads the existing record first) without the
// page form editing it.
//
// `text` and `cite` are optional. When they are set the picture carries them as
// REAL TEXT over it -- selectable, resizable, readable to a screen reader --
// rather than as words baked into the raster. They are stacked in one grid cell
// with a scrim between, because the photograph is not uniformly dark and the
// crop moves with the width.
//
// Mirrored in page-picture.ejs.
?>
<figure class="page-picture<?= !empty($picture['text']) ? ' has-caption' : '' ?>">
  <img src="<?= e(asset_url($picture['src'] ?? '', $basePath)) ?>" alt="<?= e($picture['alt'] ?? '') ?>"
       <?= !empty($picture['width']) && !empty($picture['height']) ? 'width="' . (int) $picture['width'] . '" height="' . (int) $picture['height'] . '"' : '' ?> loading="lazy">
  <?php if (!empty($picture['text'])): ?>
    <figcaption>
      <blockquote><p><?= e($picture['text']) ?></p></blockquote>
      <?php if (!empty($picture['cite'])): ?><cite><?= e($picture['cite']) ?></cite><?php endif; ?>
    </figcaption>
  <?php endif; ?>
</figure>
