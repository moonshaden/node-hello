<?php $app->partial('head'); ?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <p class="eyebrow"><?= e($site['location'] ?? '') ?> · 501(c)(3) nonprofit</p>
      <h1>Every scholarship is a door someone walks through.</h1>
      <p><?= e($site['mission'] ?? '') ?></p>
      <div class="actions">
        <a class="btn btn-gold" href="<?= e($basePath) ?>/scholarships">
          <?= $enrollment['state'] === 'open' ? 'Apply for a scholarship' : 'See available scholarships' ?>
        </a>
        <a class="btn btn-outline" style="color:#fff" href="<?= e($basePath) ?>/recipients">Meet our recipients</a>
      </div>
    </div>

    <!-- The deadline is what applicants come here for, so it is never more than
         one glance away, in or out of season. -->
    <aside class="deadline">
      <div class="count">
        <?php if ($enrollment['state'] === 'open' && $enrollment['daysUntilClose'] !== null): ?>
          <?= (int) $enrollment['daysUntilClose'] ?><small>days left to apply</small>
        <?php elseif ($enrollment['daysUntilOpen'] !== null): ?>
          <?= (int) $enrollment['daysUntilOpen'] ?><small>days until applications open</small>
        <?php else: ?>
          Open<small>applications accepted year-round</small>
        <?php endif; ?>
      </div>
      <dl>
        <?php if (!empty($enrollment['opensOn'])): ?>
          <dt>Applications open</dt>
          <dd><?= e(fdate($enrollment['opensOn'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($enrollment['closesOn'])): ?>
          <dt>Applications close</dt>
          <dd><?= e(fdate($enrollment['closesOn'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($enrollmentSettings['awardedNote'])): ?>
          <dt>Awards</dt>
          <dd style="font-weight:400;color:#c6d5e2;font-size:.92rem"><?= e($enrollmentSettings['awardedNote']) ?></dd>
        <?php endif; ?>
      </dl>
    </aside>
  </div>
</section>

<?php if (!empty($site['impact'])): ?>
<div class="stats">
  <?php foreach ($site['impact'] as $item): ?>
    <div>
      <div class="value"><?= e($item['value'] ?? '') ?></div>
      <div class="label"><?= e($item['label'] ?? '') ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($announcements !== []): ?>
<section class="band" style="padding-bottom:0">
  <div class="wrap stack">
    <?php foreach ($announcements as $note): ?>
      <div class="notice">
        <div>
          <h3><?= e($note['title'] ?? '') ?></h3>
          <p><?= e($note['body'] ?? '') ?></p>
        </div>
        <?php if (!empty($note['ctaUrl']) && !empty($note['ctaLabel'])): ?>
          <a class="btn btn-sm btn-ink" href="<?= e(link_url($note['ctaUrl'], $basePath)) ?>"><?= e($note['ctaLabel']) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Recipients lead the page, ahead of the application funnel: the awards
     already made are the strongest case for both students and donors. -->
<section class="band">
  <div class="wrap">
    <div class="band-head">
      <div>
        <p class="eyebrow">Scholarships awarded</p>
        <h2>The students behind the numbers</h2>
        <p>Every award on this page went to a student in Arizona who applied, qualified, and got to keep going.</p>
      </div>
      <a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/recipients">All recipients</a>
    </div>

    <?php if ($featuredRecipients !== []): ?>
      <div class="grid grid-3">
        <?php foreach ($featuredRecipients as $recipient): ?>
          <?php $app->partial('recipient-card', ['recipient' => $recipient]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <p><strong>No recipients published yet.</strong></p>
        <p class="small">Add this year's awarded students in the admin area and they will appear here first.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="band tint">
  <div class="wrap">
    <div class="band-head">
      <div>
        <p class="eyebrow"><?= $enrollment['state'] === 'open' ? 'Now accepting applications' : 'Plan ahead' ?></p>
        <h2><?= $openScholarships !== [] ? 'Scholarships open right now' : 'Scholarships offered each year' ?></h2>
        <p>
          <?php if ($enrollment['state'] === 'open'): ?>
            The enrollment period closes <?= e(fdate($enrollment['closesOn'])) ?>. Apply for every award you qualify for.
          <?php else: ?>
            Applications reopen <?= e(fdate($enrollment['opensOn'])) ?>. Review the criteria now so you are ready.
          <?php endif; ?>
        </p>
      </div>
      <a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/scholarships">All scholarships</a>
    </div>

    <div class="grid grid-3">
      <?php foreach (array_slice($openScholarships !== [] ? $openScholarships : $scholarships, 0, 3) as $scholarship): ?>
        <?php $app->partial('scholarship-card', ['scholarship' => $scholarship]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $app->partial('foot'); ?>
