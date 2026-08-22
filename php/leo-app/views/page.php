<?php $app->partial('head', ['description' => $page['summary'] ?? '']); ?>

<div class="page-head">
  <div class="wrap narrow">
    <h1><?= e($page['title'] ?? '') ?></h1>
    <?php if (!empty($page['summary'])): ?><p class="lede"><?= e($page['summary']) ?></p><?php endif; ?>
  </div>
</div>

<section class="band">
  <div class="wrap narrow prose"><?= md($page['body'] ?? '') ?></div>
</section>

<?php $app->partial('foot'); ?>
