<?php
// The awarded students, given the landing page's centre.
//
// The client's fifth ask was to highlight scholarships awarded and keep that as
// the focus. Three small cards with the story folded behind a disclosure was the
// weakest reading of that, so this gives each student the width of the page: a
// large portrait on its own plane, the award they won, and their story in full.
//
// Mirrors views/partials/awardee-stage.ejs.
//
// Without JavaScript this is a plain, readable list of every awarded student --
// the deck only becomes a stage when the script adds `.is-live`. Nothing here is
// hidden by the stylesheet, because a visitor with no JS must still be able to
// read every one of them.
?>
<div class="awardee-deck" data-awardees>
  <?php foreach ($awardees as $i => $person): ?>
    <article class="awardee" data-awardee data-index="<?= (int) $i ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
      <div class="awardee-frame">
        <?php if (!empty($person['photoUrl'])): ?>
          <img class="awardee-portrait" src="<?= e(link_url($person['photoUrl'], $basePath)) ?>" alt="<?= e($person['name'] ?? '') ?>"
               width="800" height="1000" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>">
        <?php else: ?>
          <div class="awardee-portrait portrait-fallback"><?= e(mb_strtoupper(mb_substr(trim((string) ($person['name'] ?? '?')), 0, 1))) ?></div>
        <?php endif; ?>
      </div>

      <div class="awardee-body">
        <p class="awardee-award"><?= e($person['scholarship'] ?? '') ?></p>
        <h3 class="awardee-name"><?= e($person['name'] ?? '') ?></h3>
        <?php $facts = implode(' · ', array_filter([$person['major'] ?? '', $person['school'] ?? ''])); ?>
        <?php if ($facts !== ''): ?><p class="awardee-meta"><?= e($facts) ?></p><?php endif; ?>
        <?php if (!empty($person['quote'])): ?>
          <?php // In full. The stage has room, so nothing is excerpted away here. ?>
          <blockquote class="awardee-story"><?= e($person['quote']) ?></blockquote>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php // Controls do nothing without the script, so the stylesheet only shows
      // them once it is live. They are content-free, so hiding them costs no one. ?>
<div class="awardee-controls">
  <button class="awardee-step" type="button" data-awardee-step="-1" aria-label="Previous awarded student">&#8249;</button>
  <p class="awardee-count" data-awardee-count aria-live="polite">1 of <?= count($awardees) ?></p>
  <button class="awardee-step" type="button" data-awardee-step="1" aria-label="Next awarded student">&#8250;</button>
</div>
