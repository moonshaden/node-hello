<?php $app->partial('admin-head'); ?>

<div class="admin-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted" style="margin:0">Today is <?= e(fdate($today)) ?> in <?= e($site['timezone'] ?? '') ?>.</p>
  </div>
  <a class="btn btn-ink btn-sm" href="<?= e($basePath) ?>/?preview=1">Preview the site</a>
</div>

<div class="panel" style="border-left:4px solid <?= $enrollment['state'] === 'open' ? 'var(--open)' : 'var(--gold-bright)' ?>">
  <h2 style="margin-bottom:.3em">Enrollment is <?= $enrollment['state'] === 'open' ? 'open' : 'closed' ?></h2>
  <p style="margin-bottom:.6em">
    <?= e($enrollmentLabel) ?>.
    <?php if ($enrollment['state'] === 'open' && $enrollment['daysUntilClose'] !== null): ?>
      <strong><?= (int) $enrollment['daysUntilClose'] ?> days remain</strong> in this cycle.
    <?php elseif ($enrollment['daysUntilOpen'] !== null): ?>
      The next cycle opens in <strong><?= (int) $enrollment['daysUntilOpen'] ?> days</strong>
      and runs to <?= e(fdate($enrollment['closesOn'])) ?>.
    <?php endif; ?>
  </p>
  <p class="small muted" style="margin:0">
    This rolls over on its own each year — nothing to update in November.
    Change the season in <a href="<?= e($basePath) ?>/admin/settings">settings</a>.
  </p>
</div>

<div class="tiles">
  <div class="tile accent">
    <div class="n"><?= (int) $openCount ?></div>
    <div class="l">scholarships accepting applications today</div>
  </div>
  <div class="tile">
    <div class="n"><?= count($scholarships) ?></div>
    <div class="l">scholarships in total</div>
  </div>
  <div class="tile">
    <div class="n"><?= count($publishedRecipients) ?></div>
    <div class="l">recipients published<?php if (count($recipients) !== count($publishedRecipients)): ?> of <?= count($recipients) ?><?php endif; ?></div>
  </div>
  <div class="tile">
    <div class="n"><?= e(money($stats['totalAwarded'])) ?: '—' ?></div>
    <div class="l">published award total</div>
  </div>
</div>

<div class="panel">
  <h2>Scholarships and their windows</h2>
  <table class="rows">
    <tr><th>Scholarship</th><th>Status</th><th>Window</th><th></th></tr>
    <?php foreach ($scholarships as $item): ?>
      <tr>
        <td class="rowtitle"><a href="<?= e($basePath) ?>/admin/scholarships/<?= e($item['id'] ?? '') ?>"><?= e($item['name'] ?? '') ?></a></td>
        <td><span class="tag <?= $item['isOpen'] ? 'tag-open' : 'tag-shut' ?>"><?= $item['isOpen'] ? 'Open' : 'Closed' ?></span></td>
        <td class="rowsub">
          <?= e($item['statusLabel']) ?><?php if ($item['inheritsWindow']): ?> <span class="muted">(site period)</span><?php endif; ?>
        </td>
        <td class="actions"><a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/admin/scholarships/<?= e($item['id'] ?? '') ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2>Everything else</h2>
  <p class="muted small">
    <a href="<?= e($basePath) ?>/admin/recipients"><?= count($recipients) ?> recipients</a> ·
    <a href="<?= e($basePath) ?>/admin/pages"><?= (int) $pageCount ?> pages</a> ·
    <a href="<?= e($basePath) ?>/admin/announcements"><?= (int) $announcementCount ?> announcements</a>
  </p>
  <?php if (count($recipients) !== count($publishedRecipients)): ?>
    <div class="warn" style="margin-bottom:0">
      <strong><?= count($recipients) - count($publishedRecipients) ?> recipient records are drafts</strong>
      and are not visible to the public. The seeded placeholders are drafts on purpose —
      replace them with real students, then untick Draft.
    </div>
  <?php endif; ?>
</div>

<?php $app->partial('admin-foot'); ?>
