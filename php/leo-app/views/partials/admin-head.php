<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= e($site['name'] ?? '') ?> admin</title>
<link rel="stylesheet" href="<?= e($basePath) ?>/css/site.css">
<link rel="stylesheet" href="<?= e($basePath) ?>/css/admin.css">
</head>
<body class="admin">

<div class="admin-bar">
  <div class="wrap">
    <a class="brand" href="<?= e($basePath) ?>/admin"><?= e($site['name'] ?? '') ?></a>
    <a href="<?= e($basePath) ?>/admin/scholarships" class="<?= str_starts_with($currentPath, '/admin/scholarships') ? 'is-current' : '' ?>">Scholarships</a>
    <a href="<?= e($basePath) ?>/admin/recipients" class="<?= str_starts_with($currentPath, '/admin/recipients') ? 'is-current' : '' ?>">Recipients</a>
    <a href="<?= e($basePath) ?>/admin/pages" class="<?= str_starts_with($currentPath, '/admin/pages') ? 'is-current' : '' ?>">Pages</a>
    <a href="<?= e($basePath) ?>/admin/announcements" class="<?= str_starts_with($currentPath, '/admin/announcements') ? 'is-current' : '' ?>">Announcements</a>
    <a href="<?= e($basePath) ?>/admin/settings" class="<?= str_starts_with($currentPath, '/admin/settings') ? 'is-current' : '' ?>">Settings</a>
    <span class="right">
      <a href="<?= e($basePath) ?>/?preview=1">View site</a>
      <form method="post" action="<?= e($basePath) ?>/admin/logout"><button type="submit">Sign out</button></form>
    </span>
  </div>
</div>

<main><div class="wrap">
