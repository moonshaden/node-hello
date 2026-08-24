<?php $app->partial('head', ['description' => $scholarship['summary'] ?? '']); ?>

<div class="page-head">
  <div class="wrap">
    <p class="eyebrow"><a href="<?= e($basePath) ?>/scholarships">Available scholarships</a></p>
    <h1><?= e($scholarship['name'] ?? '') ?></h1>
    <p class="lede"><?= e($scholarship['summary'] ?? '') ?></p>
  </div>
</div>

<section class="band">
  <div class="wrap split">
    <div>
      <?php if (!empty($scholarship['criteria'])): ?>
        <div class="criteria">
          <h3>Who can apply</h3>
          <div class="prose"><?= md($scholarship['criteria']) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($scholarship['essayPrompts'])): ?>
        <h2 style="margin-top:2em">Essay prompts</h2>
        <ol class="prose">
          <?php foreach ($scholarship['essayPrompts'] as $prompt): ?><li><?= e($prompt) ?></li><?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <?php if (!empty($scholarship['details'])): ?>
        <div class="prose" style="margin-top:2em"><?= md($scholarship['details']) ?></div>
      <?php endif; ?>

      <?php if ($recipients !== []): ?>
        <h2 style="margin-top:2.4em">Past recipients of this award</h2>
        <div class="grid grid-2">
          <?php foreach (array_slice($recipients, 0, 4) as $recipient): ?>
            <?php $app->partial('recipient-card', ['recipient' => $recipient]); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="sidebar-card">
      <span class="pill pill-<?= e($scholarship['window']['state']) ?>"><?= $scholarship['isOpen'] ? 'Accepting applications' : 'Not accepting applications' ?></span>
      <dl style="margin-top:20px">
        <?php if (!empty($scholarship['amount'])): ?><dt>Award</dt><dd><?= e($scholarship['amount']) ?></dd><?php endif; ?>
        <?php if (!empty($scholarship['window']['opensOn'])): ?><dt>Opens</dt><dd><?= e(fdate($scholarship['window']['opensOn'])) ?></dd><?php endif; ?>
        <?php if (!empty($scholarship['window']['closesOn'])): ?><dt>Closes</dt><dd><?= e(fdate($scholarship['window']['closesOn'])) ?></dd><?php endif; ?>
      </dl>

      <?php if ($scholarship['isOpen']): ?>
        <?php if (!empty($scholarship['applyUrl'])): ?>
          <a class="btn btn-gold" style="width:100%;text-align:center" href="<?= e(link_url($scholarship['applyUrl'], $basePath)) ?>">Start your application</a>
        <?php else: ?>
          <p class="small muted">Application link coming soon. Email <a href="mailto:<?= e($site['email'] ?? '') ?>"><?= e($site['email'] ?? '') ?></a> to be notified.</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="small muted" style="margin-bottom:0">
          <?= e($scholarship['statusLabel']) ?>.
          <?php if (!empty($scholarship['window']['daysUntilOpen'])): ?>That is <?= (int) $scholarship['window']['daysUntilOpen'] ?> days from today.<?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if (!empty($enrollmentSettings['awardedNote'])): ?>
        <p class="small muted" style="margin-top:16px;margin-bottom:0"><?= e($enrollmentSettings['awardedNote']) ?></p>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php $app->partial('foot'); ?>
