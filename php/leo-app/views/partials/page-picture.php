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
// It briefly carried a quotation over the photograph, transcribed from the live
// site's own testimonial band. The client asked for the picture without the
// words; the text is in this file's history if it is ever wanted back.
//
// Mirrored in page-picture.ejs.
?>
<figure class="page-picture">
  <img src="<?= e(asset_url($picture['src'] ?? '', $basePath)) ?>" alt="<?= e($picture['alt'] ?? '') ?>"
       <?= !empty($picture['width']) && !empty($picture['height']) ? 'width="' . (int) $picture['width'] . '" height="' . (int) $picture['height'] . '"' : '' ?> loading="lazy">
</figure>
