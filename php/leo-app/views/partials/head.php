<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?><?= $title === ($site['name'] ?? '') ? '' : ' — ' . e($site['name'] ?? '') ?></title>
<meta name="description" content="<?= e($description ?? ($site['mission'] ?? $site['tagline'] ?? '')) ?>">
<link rel="stylesheet" href="<?= e($basePath) ?>/css/site.css">
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
    <a class="wordmark" href="<?= e($basePath) ?>/">
      <span class="mark">LEO</span>
      <span>
        <span class="name"><?= e($site['name'] ?? '') ?></span><br>
        <span class="tag"><?= e($site['tagline'] ?? '') ?></span>
      </span>
    </a>
    <nav class="nav">
      <a href="<?= e($basePath) ?>/scholarships" class="<?= str_starts_with($currentPath, '/scholarships') ? 'is-current' : '' ?>">Scholarships</a>
      <a href="<?= e($basePath) ?>/recipients" class="<?= $currentPath === '/recipients' ? 'is-current' : '' ?>">Recipients</a>
      <?php foreach ($navPages as $navPage): ?>
        <a href="<?= e($basePath) ?>/<?= e($navPage['slug'] ?? '') ?>" class="<?= $currentPath === '/' . ($navPage['slug'] ?? '') ? 'is-current' : '' ?>"><?= e($navPage['navLabel'] ?? $navPage['title'] ?? '') ?></a>
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
