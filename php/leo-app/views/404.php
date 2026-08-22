<?php $app->partial('head'); ?>
<section class="band">
  <div class="wrap narrow" style="text-align:center;padding:48px 0">
    <p class="eyebrow">404</p>
    <h1>We could not find that page</h1>
    <p class="muted">It may have moved when the site was rebuilt. Try the scholarships list or our recipients.</p>
    <p style="margin-top:28px">
      <a class="btn btn-ink" href="<?= e($basePath) ?>/scholarships">Available scholarships</a>
      <a class="btn btn-outline" href="<?= e($basePath) ?>/">Home</a>
    </p>
  </div>
</section>
<?php $app->partial('foot'); ?>
