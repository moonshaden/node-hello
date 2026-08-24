<article class="card recipient">
  <?php if (!empty($recipient['photoUrl'])): ?>
    <img class="portrait" src="<?= e(link_url($recipient['photoUrl'], $basePath)) ?>" alt="<?= e($recipient['name'] ?? '') ?>" loading="lazy" width="800" height="1000">
  <?php else: ?>
    <div class="portrait portrait-fallback"><?= e(mb_strtoupper(mb_substr(trim((string) ($recipient['name'] ?? '?')), 0, 1))) ?></div>
  <?php endif; ?>
  <h3 class="name"><?= e($recipient['name'] ?? '') ?><?php if ($preview && !empty($recipient['draft'])): ?> <span class="pill pill-closed">Draft</span><?php endif; ?></h3>
  <div class="award"><?= e($recipient['scholarship'] ?? '') ?><?php if (!empty($recipient['amount'])): ?> · <?= e(money($recipient['amount'])) ?><?php endif; ?></div>
  <div class="meta">
    <?= e(implode(' · ', array_filter([$recipient['major'] ?? '', $recipient['school'] ?? '']))) ?>
    <?php if (!empty($recipient['year'])): ?><br>Class of <?= e($recipient['year']) ?><?php endif; ?>
  </div>
  <?php if (!empty($recipient['quote'])): $story = excerpt($recipient['quote']); ?>
    <?php if ($story['truncated']): ?>
      <details class="story">
        <summary>
          <blockquote class="story-excerpt"><?= e($story['text']) ?></blockquote>
          <span class="story-toggle"><span class="when-shut">Read the full story</span><span class="when-open">Show less</span></span>
        </summary>
        <blockquote class="story-full"><?= e($story['full']) ?></blockquote>
      </details>
    <?php else: ?>
      <blockquote><?= e($story['text']) ?></blockquote>
    <?php endif; ?>
  <?php endif; ?>
</article>
