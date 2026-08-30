<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?><?= $title === ($site['name'] ?? '') ? '' : ' — ' . e($site['name'] ?? '') ?></title>
<meta name="description" content="<?= e($description ?? ($site['mission'] ?? $site['tagline'] ?? '')) ?>">
<link rel="stylesheet" href="<?= e($basePath) ?>/css/site.css">
<link rel="icon" href="<?= e($basePath) ?>/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e($basePath) ?>/img/brand/favicon-32.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?= e($basePath) ?>/img/brand/favicon-512.png">
<link rel="apple-touch-icon" href="<?= e($basePath) ?>/img/brand/favicon-180.png">
<meta name="theme-color" content="#10263d">
</head>
<body>

<?php if ($preview): ?>
<div class="previewbar">
  <div class="wrap">
    <strong>Preview</strong>
    <span>Showing the site as it looks on <?= e(fdate($today)) ?>, drafts included.</span>
    <form method="get" style="display:flex;gap:8px;align-items:center;margin-left:auto">
      <label for="asOf">View date</label>
      <input type="date" id="asOf" name="asOf" value="<?= e($today) ?>">
      <button type="submit">Go</button>
    </form>
    <a href="<?= e($currentPath) ?>">Exit preview</a>
  </div>
</div>
<?php endif; ?>

<header class="masthead">
  <div class="wrap masthead-inner">
    <a class="wordmark" href="<?= e($basePath) ?>/" aria-label="<?= e($site['name'] ?? '') ?> home">
      <!-- The lion is the mark; the name is set in type rather than shipped as a
           raster, so it stays sharp at any size and can be restyled in CSS. -->
      <img class="wordmark-lion" src="<?= e(link_url('/img/brand/leo-lion-gold-solid.png', $basePath)) ?>"
           alt="" aria-hidden="true" width="368" height="433">
      <span class="wordmark-type">
        <span class="wordmark-name"><?= e($site['name'] ?? '') ?></span>
        <span class="wordmark-strap">Leadership &middot; Education &middot; Opportunity</span>
      </span>
    </a>
    <nav class="nav">
      <a href="<?= e($basePath) ?>/scholarships" class="<?= str_starts_with($currentPath, '/scholarships') ? 'is-current' : '' ?>">Scholarships</a>
      <a href="<?= e($basePath) ?>/recipients" class="<?= $currentPath === '/recipients' ? 'is-current' : '' ?>">Recipients</a>
      <?php // A page with children becomes a dropdown. The parent stays a real link,
            // so the menu is usable without hover and on touch. Mirrored in head.ejs. ?>
      <?php foreach ($navPages as $navPage): ?>
        <?php if ($navPage['children'] !== []): ?>
          <?php
          $openHere = $currentPath === '/' . ($navPage['slug'] ?? '');
          foreach ($navPage['children'] as $child) {
              if ($currentPath === '/' . ($child['slug'] ?? '')) {
                  $openHere = true;
              }
          }
          ?>
          <div class="nav-group">
            <a href="<?= e($basePath) ?>/<?= e($navPage['slug'] ?? '') ?>" class="nav-group-top <?= $openHere ? 'is-current' : '' ?>"><?= e($navPage['navLabel'] ?? $navPage['title'] ?? '') ?><span class="nav-caret" aria-hidden="true"></span></a>
            <div class="nav-menu">
              <?php foreach ($navPage['children'] as $child): ?>
                <a href="<?= e($basePath) ?>/<?= e($child['slug'] ?? '') ?>" class="<?= $currentPath === '/' . ($child['slug'] ?? '') ? 'is-current' : '' ?>"><?= e($child['navLabel'] ?? $child['title'] ?? '') ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= e($basePath) ?>/<?= e($navPage['slug'] ?? '') ?>" class="<?= $currentPath === '/' . ($navPage['slug'] ?? '') ? 'is-current' : '' ?>"><?= e($navPage['navLabel'] ?? $navPage['title'] ?? '') ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!empty($site['donateUrl'])): ?>
        <a class="btn btn-gold btn-sm" href="<?= e(link_url($site['donateUrl'], $basePath)) ?>">Donate</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="ribbon">
  <div class="wrap ribbon-inner">
    <span class="pill pill-<?= e($enrollment['state']) ?>">
      <?= $enrollment['state'] === 'open' ? 'Applications open' : 'Applications closed' ?>
    </span>
    <span><strong><?= e($enrollmentLabel) ?>.</strong>
      <?php if ($enrollment['state'] === 'open' && $enrollment['daysUntilClose'] !== null): ?>
        <span class="muted"><?= (int) $enrollment['daysUntilClose'] ?> days left to apply.</span>
      <?php elseif ($enrollment['state'] !== 'open' && $enrollment['daysUntilOpen'] !== null): ?>
        <span class="muted">Opens in <?= (int) $enrollment['daysUntilOpen'] ?> days.</span>
      <?php endif; ?>
    </span>
    <a class="btn btn-ink btn-sm" href="<?= e($basePath) ?>/scholarships">
      <?= $enrollment['state'] === 'open' ? 'Apply now' : 'Browse scholarships' ?>
    </a>
  </div>
</div>

<main>
