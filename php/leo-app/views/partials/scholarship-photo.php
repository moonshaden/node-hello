<?php
// The memorial photograph at the top of a scholarship's copy column.
//
// The five published images are not one shape and the live site does not give
// them one treatment. Three carry Avada's `alignleft` (the copy wraps beside
// them) and two carry `img-responsive` (they fill the width). The split is by
// the image's own width, not by its shape:
//
//   463, 543 and 250px wide  -> alignleft
//   924 and 980px wide       -> img-responsive
//
// So that is the rule here: under INSET_MAX the figure sits inside the copy and
// the text wraps; at or above it, it fills the column. Reproducing the live
// split rather than inventing one, and it happens to be the rule that avoids
// the two things that actually go wrong -- a picture upscaled past its own
// pixels, and a narrow picture leaving half a column blank.
//
// `photoWidth` and `photoHeight` are the stored pixel size. They decide the
// branch and they become the `width`/`height` attributes, so the column
// reserves the space before the image loads. Without them the figure falls back
// to the full-width treatment, which is the safe default: it never floats, so
// it cannot drag a boxed element underneath itself.
//
// Mirrored in scholarship-photo.ejs.
const INSET_MAX = 560;
$pw = (int) ($scholarship['photoWidth'] ?? 0);
$ph = (int) ($scholarship['photoHeight'] ?? 0);
$inset = $pw > 0 && $pw < INSET_MAX;
?>
<figure class="scholarship-photo<?= $inset ? ' is-inset' : '' ?>">
  <img src="<?= e(link_url($scholarship['photoUrl'] ?? '', $basePath)) ?>" alt="<?= e($scholarship['photoAlt'] ?? '') ?>"
       <?= $pw && $ph ? 'width="' . $pw . '" height="' . $ph . '"' : '' ?> loading="lazy">
</figure>
