<?php $app->partial('admin-head'); ?>

<div class="admin-head">
  <div>
    <h1><?= e($config['plural']) ?></h1>
    <p class="muted" style="margin:0"><?= count($items) ?> <?= count($items) === 1 ? 'record' : 'records' ?>. Order here is the order shown on the site.</p>
  </div>
  <a class="btn btn-ink btn-sm" href="<?= e($basePath) ?>/admin/<?= e($name) ?>/new">Add <?= e(strtolower($config['label'])) ?></a>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="rows">
    <tr>
      <th><?= e($config['label']) ?></th>
      <th>Visibility</th>
      <?php if ($name === 'scholarships'): ?><th>Applications</th><?php endif; ?>
      <th></th>
    </tr>
    <?php foreach ($items as $item): ?>
      <tr>
        <td>
          <div class="rowtitle"><a href="<?= e($basePath) ?>/admin/<?= e($name) ?>/<?= e($item['id'] ?? '') ?>"><?= e($item[$config['titleKey']] ?? 'Untitled') ?></a></div>
          <div class="rowsub">
            <?php if (!empty($item['slug'])): ?>/<?= e($item['slug']) ?><?php endif; ?>
            <?php if (!empty($item['year'])): ?>Class of <?= e($item['year']) ?><?php endif; ?>
            <?php if (!empty($item['scholarship'])): ?> · <?= e($item['scholarship']) ?><?php endif; ?>
            <?php if (!empty($item['showWhen'])): ?>Shown while applications are <?= e($item['showWhen']) ?><?php endif; ?>
          </div>
        </td>
        <td>
          <span class="tag <?= $item['published'] ? 'tag-live' : 'tag-hidden' ?>"><?= $item['published'] ? 'Live' : (!empty($item['draft']) ? 'Draft' : 'Hidden') ?></span>
        </td>
        <?php if ($name === 'scholarships'): ?>
          <td class="rowsub"><?= e($item['resolved']['statusLabel'] ?? '') ?></td>
        <?php endif; ?>
        <td class="actions">
          <form method="post" action="<?= e($basePath) ?>/admin/<?= e($name) ?>/<?= e($item['id'] ?? '') ?>/move">
            <input type="hidden" name="direction" value="up">
            <button class="btn btn-sm btn-outline" type="submit" title="Move up">↑</button>
          </form>
          <form method="post" action="<?= e($basePath) ?>/admin/<?= e($name) ?>/<?= e($item['id'] ?? '') ?>/move">
            <input type="hidden" name="direction" value="down">
            <button class="btn btn-sm btn-outline" type="submit" title="Move down">↓</button>
          </form>
          <a class="btn btn-sm btn-ink" href="<?= e($basePath) ?>/admin/<?= e($name) ?>/<?= e($item['id'] ?? '') ?>">Edit</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
      <tr><td colspan="4" class="muted" style="padding:32px;text-align:center">Nothing here yet.</td></tr>
    <?php endif; ?>
  </table>
</div>

<?php $app->partial('admin-foot'); ?>
