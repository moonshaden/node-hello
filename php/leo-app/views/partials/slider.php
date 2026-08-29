<?php
/**
 * Homepage slider. Mirrors views/partials/slider.ejs.
 *
 * The live site publishes a heading and a photograph per slide and nothing
 * else -- no body, no subheading, no button -- so the whole panel is the link
 * where there is somewhere to send you, and plain where there is not.
 *
 * Only the first image loads eagerly. The rest are lazy, so the largest
 * contentful paint is not waiting on three photographs the visitor cannot see.
 */
if (($slides ?? []) === []) {
    return;
}
$count = count($slides);
?>
<section class="slider" data-slider aria-roledescription="carousel" aria-label="Foundation highlights">
  <div class="slider-track">
    <?php foreach (array_values($slides) as $i => $slide): ?>
      <?php
      $url = trim((string) ($slide['ctaUrl'] ?? ''));
      $tag = $url !== '' ? 'a' : 'div';
      ?>
      <<?= $tag ?> class="slide<?= $i === 0 ? ' is-current' : '' ?>" data-slide
        aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
        aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= $count ?>"
        <?= $url !== '' ? 'href="' . e($basePath . $url) . '"' : '' ?>>
        <img class="slide-img" src="<?= e(link_url($slide['image'] ?? '', $basePath)) ?>"
             alt="<?= e($slide['alt'] ?? '') ?>"
             loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
             <?= $i === 0 ? 'fetchpriority="high"' : '' ?>>
        <div class="slide-body">
          <div class="wrap">
            <h2 class="slide-heading"><?= e($slide['heading'] ?? '') ?></h2>
            <?php if (!empty($slide['subheading'])): ?>
              <p class="slide-sub"><?= e($slide['subheading']) ?></p>
            <?php endif; ?>
            <?php if (!empty($slide['body'])): ?>
              <p class="slide-text"><?= e($slide['body']) ?></p>
            <?php endif; ?>
            <?php if ($url !== ''): ?>
              <span class="slide-cta"><?= e($slide['ctaLabel'] ?: 'Read more') ?></span>
            <?php endif; ?>
          </div>
        </div>
      </<?= $tag ?>>
    <?php endforeach; ?>
  </div>

  <?php if ($count > 1): ?>
    <button class="slider-arrow slider-prev" type="button" data-step="-1" aria-label="Previous slide">&#8249;</button>
    <button class="slider-arrow slider-next" type="button" data-step="1" aria-label="Next slide">&#8250;</button>
    <div class="slider-dots">
      <?php foreach (array_values($slides) as $i => $slide): ?>
        <button type="button" data-goto="<?= $i ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"
          aria-label="<?= e($slide['heading'] ?? ('Slide ' . ($i + 1))) ?>"></button>
      <?php endforeach; ?>
    </div>
    <p class="visually-hidden" data-slider-status aria-live="polite"></p>
  <?php endif; ?>
</section>
