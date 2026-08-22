<?php $app->partial('head'); ?>

<div class="page-head">
  <div class="wrap">
    <p class="eyebrow">Available scholarships</p>
    <h1><?= count($openScholarships) ?> of <?= count($scholarships) ?> scholarships accepting applications</h1>
    <p class="lede">
      <?php if ($enrollment['state'] === 'open'): ?>
        The enrollment period runs <?= e(fdate($enrollment['opensOn'])) ?> through <?= e(fdate($enrollment['closesOn'])) ?>.
        Read the criteria for each award and apply for every one you qualify for.
      <?php else: ?>
        Applications open <?= e(fdate($enrollment['opensOn'])) ?> and close <?= e(fdate($enrollment['closesOn'])) ?>.
        The awards below stay listed year-round so you can prepare.
      <?php endif; ?>
    </p>
  </div>
</div>

<section class="band">
  <div class="wrap">
    <?php if (!empty($enrollmentSettings['instructions'])): ?>
      <div class="notice" style="margin-bottom:32px">
        <div>
          <h3>Before you apply</h3>
          <p><?= e($enrollmentSettings['instructions']) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($scholarships !== []): ?>
      <div class="grid grid-3">
        <?php foreach ($scholarships as $scholarship): ?>
          <?php $app->partial('scholarship-card', ['scholarship' => $scholarship]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><p><strong>No scholarships are listed right now.</strong></p></div>
    <?php endif; ?>
  </div>
</section>

<?php $app->partial('foot'); ?>
