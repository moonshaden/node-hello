<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= e($site['name'] ?? '') ?> admin</title>
<link rel="stylesheet" href="/css/site.css">
<link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin">

<div class="admin-bar">
  <div class="wrap">
    <a class="brand" href="/admin"><?= e($site['name'] ?? '') ?></a>
    <a href="/admin/scholarships" class="<?= str_starts_with($currentPath, '/admin/scholarships') ? 'is-current' : '' ?>">Scholarships</a>
    <a href="/admin/recipients" class="<?= str_starts_with($currentPath, '/admin/recipients') ? 'is-current' : '' ?>">Recipients</a>
    <a href="/admin/pages" class="<?= str_starts_with($currentPath, '/admin/pages') ? 'is-current' : '' ?>">Pages</a>
    <a href="/admin/announcements" class="<?= str_starts_with($currentPath, '/admin/announcements') ? 'is-current' : '' ?>">Announcements</a>
    <a href="/admin/settings" class="<?= str_starts_with($currentPath, '/admin/settings') ? 'is-current' : '' ?>">Settings</a>
    <span class="right">
      <a href="/?preview=1">View site</a>
      <form method="post" action="/admin/logout"><button type="submit">Sign out</button></form>
    </span>
  </div>
</div>

<main><div class="wrap">
