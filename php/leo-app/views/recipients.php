<?php $app->partial('head'); ?>

<div class="page-head">
  <div class="wrap">
    <p class="eyebrow">Scholarship recipients</p>
    <h1>Who we have supported</h1>
    <p class="lede">
      <?php if ($stats['recipientCount'] > 0): ?>
        <?= (int) $stats['recipientCount'] ?> awarded students<?php if ($stats['yearCount'] > 0): ?> across <?= (int) $stats['yearCount'] ?> <?= $stats['yearCount'] === 1 ? 'year' : 'years' ?><?php endif; ?><?php if ($stats['totalAwarded'] > 0): ?>, totaling <?= e(money($stats['totalAwarded'])) ?> in published awards<?php endif; ?>.
      <?php else: ?>
        Awarded students are published here after each spring's decisions.
      <?php endif; ?>
    </p>
  </div>
</div>

<section class="band">
  <div class="wrap">
    <?php if (count($years) > 1): ?>
      <div class="filters">
        <a href="<?= e($basePath) ?>/recipients" class="<?= $selectedYear === null ? 'is-current' : '' ?>">All years</a>
        <?php foreach ($years as $year): ?>
          <a href="<?= e($basePath) ?>/recipients?year=<?= e($year) ?>" class="<?= $selectedYear === $year ? 'is-current' : '' ?>"><?= e($year) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($groups === []): ?>
      <div class="empty">
        <p><strong>No recipients published yet.</strong></p>
        <p class="small">Once awards are announced, add each student in the admin area to publish them here.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($groups as $group): ?>
      <?php if ($group['year'] !== ''): ?>
        <h2 class="year-head"><?= e($group['year']) ?> <span class="count"><?= count($group['items']) ?> awarded</span></h2>
      <?php endif; ?>
      <div class="grid grid-3">
        <?php foreach ($group['items'] as $recipient): ?>
          <?php $app->partial('recipient-card', ['recipient' => $recipient]); ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php $app->partial('foot'); ?>
