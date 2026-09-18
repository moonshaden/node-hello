<?php
// A quotation over a photograph, under the copy on an editable page.
//
// The picture and the words are stacked in one grid cell rather than the
// photograph being a CSS `background-image`, for two reasons. The src then goes
// through `asset_url()` and carries a content hash like every other image on the
// site -- a background in the stylesheet would not, and a picture replaced under
// the same name would sit in browser caches for a year. And the quotation stays
// real text: selectable, readable to a screen reader, and resizable, rather than
// baked into a raster.
//
// `quote` is an object on the page record -- `{ src, alt, width, height, text,
// cite }` -- the same shape the programs list and the board roster use, so it
// survives an admin save (`applyFields()` spreads the existing record first)
// without the page form editing it.
//
// Mirrored in page-quote.ejs.
?>
<figure class="page-quote">
  <img src="<?= e(asset_url($quote['src'] ?? '', $basePath)) ?>" alt="<?= e($quote['alt'] ?? '') ?>"
       <?= !empty($quote['width']) && !empty($quote['height']) ? 'width="' . (int) $quote['width'] . '" height="' . (int) $quote['height'] . '"' : '' ?> loading="lazy">
  <figcaption>
    <blockquote><p><?= e($quote['text'] ?? '') ?></p></blockquote>
    <?php if (!empty($quote['cite'])): ?><cite><?= e($quote['cite']) ?></cite><?php endif; ?>
  </figcaption>
</figure>
