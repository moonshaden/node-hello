<?php $app->partial('head'); ?>
<section class="band">
  <div class="wrap narrow" style="text-align:center;padding:48px 0">
    <h1>Something went wrong</h1>
    <p class="muted"><?= e($message) ?></p>
    <p style="margin-top:28px"><a class="btn btn-ink" href="<?= e($basePath) ?>/">Back to the homepage</a></p>
  </div>
</section>
<?php $app->partial('foot'); ?>
