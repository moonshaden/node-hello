<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff sign in — <?= e($site['name'] ?? '') ?></title>
<link rel="stylesheet" href="/css/site.css">
<link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin">
<div class="wrap login">
  <div class="panel">
    <h1 style="font-size:1.4rem">Staff sign in</h1>
    <?php if (!$configured): ?>
      <div class="warn">
        <strong>The admin area is switched off.</strong>
        Set <code>admin_password</code> in <code>leo-app/config.php</code> to enable it.
      </div>
    <?php else: ?>
      <?php if ($error !== null): ?><div class="warn"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="/admin/login">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label class="title" for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
        </div>
        <button class="btn btn-ink" type="submit" style="width:100%">Sign in</button>
      </form>
    <?php endif; ?>
    <p class="small muted" style="margin-top:22px;margin-bottom:0"><a href="/">Back to the site</a></p>
  </div>
</div>
</body>
</html>
