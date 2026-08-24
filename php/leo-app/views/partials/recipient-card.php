<article class="card recipient">
  <?php if (!empty($recipient['photoUrl'])): ?>
    <img class="portrait" src="<?= e($recipient['photoUrl']) ?>" alt="<?= e($recipient['name'] ?? '') ?>">
  <?php else: ?>
    <div class="portrait portrait-fallback"><?= e(mb_strtoupper(mb_substr(trim((string) ($recipient['name'] ?? '?')), 0, 1))) ?></div>
  <?php endif; ?>
  <h3 class="name"><?= e($recipient['name'] ?? '') ?><?php if ($preview && !empty($recipient['draft'])): ?> <span class="pill pill-closed">Draft</span><?php endif; ?></h3>
  <div class="award"><?= e($recipient['scholarship'] ?? '') ?><?php if (!empty($recipient['amount'])): ?> · <?= e(money($recipient['amount'])) ?><?php endif; ?></div>
  <div class="meta">
    <?= e(implode(' · ', array_filter([$recipient['major'] ?? '', $recipient['school'] ?? '']))) ?>
    <?php if (!empty($recipient['year'])): ?><br>Class of <?= e($recipient['year']) ?><?php endif; ?>
  </div>
  <?php if (!empty($recipient['quote'])): ?><blockquote><?= e($recipient['quote']) ?></blockquote><?php endif; ?>
</article>
