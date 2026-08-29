<?php
/**
 * Leadership, Education, Opportunity. Mirrors views/partials/pillars.ejs.
 *
 * The foundation's name is the acronym, so the three words are set as the
 * headings and the initial letter is picked out in gold -- the structure is
 * the content here, not decoration.
 */
if (($pillars ?? []) === []) {
    return;
}
?>
<section class="band">
  <div class="wrap">
    <div class="band-head">
      <div>
        <p class="eyebrow">What LEO stands for</p>
        <h2>Leadership. Education. Opportunity.</h2>
      </div>
    </div>
    <div class="pillars">
      <?php foreach ($pillars as $pillar): ?>
        <?php $word = (string) ($pillar['word'] ?? ''); ?>
        <article class="pillar">
          <h3 class="pillar-word">
            <span class="pillar-initial"><?= e(mb_substr($word, 0, 1)) ?></span><?= e(mb_substr($word, 1)) ?>
          </h3>
          <?php if (!empty($pillar['tagline'])): ?>
            <p class="pillar-tagline"><?= e($pillar['tagline']) ?></p>
          <?php endif; ?>
          <p class="pillar-body"><?= e($pillar['body'] ?? '') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
