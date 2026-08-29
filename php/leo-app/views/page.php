<?php $app->partial('head', ['description' => $page['summary'] ?? '']); ?>

<div class="page-head">
  <div class="wrap">
    <h1><?= e($page['title'] ?? '') ?></h1>
    <?php if (!empty($page['summary'])): ?><p class="lede"><?= e($page['summary']) ?></p><?php endif; ?>
  </div>
</div>

<section class="band">
  <div class="wrap split">
    <div class="prose"><?= md($page['body'] ?? '') ?></div>
    <?php $app->partial('page-aside'); ?>
  </div>
</section>

<?php if (($page['slug'] ?? '') === 'contact'): ?>
  <?php
  // Rendered from the site settings rather than the page body, so the address
  // block cannot drift from the footer and the admin only edits it once.
  $details = array_filter([
      'Email' => $site['email'] ?? '',
      'Phone' => $site['phone'] ?? '',
      'Location' => $site['location'] ?? '',
  ], static fn (string $value): bool => trim($value) !== '');
  ?>
  <?php if ($details !== []): ?>
  <section class="band" style="padding-top:0">
    <div class="wrap">
      <dl class="contact-grid">
        <?php foreach ($details as $label => $value): ?>
          <div>
            <dt><?= e($label) ?></dt>
            <dd>
              <?php if ($label === 'Email'): ?><a href="mailto:<?= e($value) ?>"><?= e($value) ?></a>
              <?php elseif ($label === 'Phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $value)) ?>"><?= e($value) ?></a>
              <?php else: ?><?= e($value) ?><?php endif; ?>
            </dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>

<?php $app->partial('page-cta'); ?>
<?php $app->partial('foot'); ?>
