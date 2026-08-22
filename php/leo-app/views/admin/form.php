<?php $app->partial('admin-head'); ?>
<?php
  $win = $record['window'] ?? ['type' => 'inherit'];
  $value = static fn (string $key) => Leo\Admin::get($record, $key) ?? '';
?>

<div class="admin-head">
  <div>
    <p class="small muted" style="margin:0"><a href="/admin/<?= e($name) ?>">← <?= e($config['plural']) ?></a></p>
    <h1><?= $isNew ? 'New ' . e(strtolower($config['label'])) : e($record[$config['titleKey']] ?? $config['label']) ?></h1>
  </div>
</div>

<?php if ($saved): ?><div class="saved">Saved. The public site is already showing this.</div><?php endif; ?>

<form method="post" action="/admin/<?= e($name) ?>/<?= $isNew ? 'new' : e($record['id']) ?>">
<div class="formgrid">
  <div class="panel">
    <?php foreach ($config['fields'] as $field): ?>
      <?php
        $key = $field['key'];
        $type = $field['type'] ?? 'text';
        $fname = Leo\Admin::fieldName($key);
        $fid = Leo\Admin::fieldId($key);
      ?>

      <?php if ($type === 'window'): ?>
        <div class="field windowfield">
          <label class="title" for="window-type"><?= e($field['label']) ?></label>
          <select id="window-type" name="window[type]" data-window-type>
            <option value="inherit"<?= selected($win['type'] ?? 'inherit', 'inherit') ?>>Use the site-wide enrollment period</option>
            <option value="annual"<?= selected($win['type'] ?? '', 'annual') ?>>Its own period, repeating every year</option>
            <option value="fixed"<?= selected($win['type'] ?? '', 'fixed') ?>>Its own one-off dates</option>
            <option value="always"<?= selected($win['type'] ?? '', 'always') ?>>Always accepting applications</option>
          </select>
          <?php if (!empty($field['help'])): ?><div class="help"><?= e($field['help']) ?></div><?php endif; ?>

          <div class="row2" data-window-block="annual" style="margin-top:14px">
            <div>
              <label class="title small" for="window-annualOpensOn">Opens (MM-DD)</label>
              <input type="text" id="window-annualOpensOn" name="window[annualOpensOn]"
                     pattern="\d{2}-\d{2}" placeholder="11-01"
                     value="<?= e(($win['type'] ?? '') === 'annual' ? ($win['opensOn'] ?? '') : '') ?>">
            </div>
            <div>
              <label class="title small" for="window-annualClosesOn">Closes (MM-DD)</label>
              <input type="text" id="window-annualClosesOn" name="window[annualClosesOn]"
                     pattern="\d{2}-\d{2}" placeholder="03-31"
                     value="<?= e(($win['type'] ?? '') === 'annual' ? ($win['closesOn'] ?? '') : '') ?>">
            </div>
            <p class="help" style="grid-column:1/-1;margin:0">
              A period that ends before it starts simply wraps the year — 11-01 to 03-31 is one cycle spanning two calendar years.
            </p>
          </div>

          <div class="row2" data-window-block="fixed" style="margin-top:14px">
            <div>
              <label class="title small" for="window-opensOn">Opens</label>
              <input type="date" id="window-opensOn" name="window[opensOn]"
                     value="<?= e(($win['type'] ?? '') === 'fixed' ? ($win['opensOn'] ?? '') : '') ?>">
            </div>
            <div>
              <label class="title small" for="window-closesOn">Closes</label>
              <input type="date" id="window-closesOn" name="window[closesOn]"
                     value="<?= e(($win['type'] ?? '') === 'fixed' ? ($win['closesOn'] ?? '') : '') ?>">
            </div>
          </div>
        </div>

      <?php elseif ($type === 'checkbox'): ?>
        <div class="field check">
          <input type="checkbox" id="<?= e($fid) ?>" name="<?= e($fname) ?>"<?= checked($value($key)) ?>>
          <label class="title" for="<?= e($fid) ?>"><?= e($field['label']) ?></label>
        </div>

      <?php elseif ($type === 'textarea' || $type === 'markdown' || $type === 'lines'): ?>
        <div class="field">
          <label class="title" for="<?= e($fid) ?>"><?= e($field['label']) ?></label>
          <textarea id="<?= e($fid) ?>" name="<?= e($fname) ?>"
                    rows="<?= (int) ($field['rows'] ?? ($type === 'markdown' ? 10 : 4)) ?>"
                    class="<?= $type === 'markdown' ? 'mono' : '' ?>"><?= e($type === 'lines' ? implode("\n", (array) $value($key)) : $value($key)) ?></textarea>
          <?php if (!empty($field['help'])): ?><div class="help"><?= e($field['help']) ?></div><?php endif; ?>
        </div>

      <?php elseif ($type === 'select'): ?>
        <div class="field">
          <label class="title" for="<?= e($fid) ?>"><?= e($field['label']) ?></label>
          <select id="<?= e($fid) ?>" name="<?= e($fname) ?>">
            <?php foreach ($field['options'] as $option): ?>
              <option value="<?= e($option['value']) ?>"<?= selected($value($key), $option['value']) ?>><?= e($option['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($field['help'])): ?><div class="help"><?= e($field['help']) ?></div><?php endif; ?>
        </div>

      <?php elseif ($type === 'scholarship'): ?>
        <div class="field">
          <label class="title" for="<?= e($fid) ?>"><?= e($field['label']) ?></label>
          <input type="text" id="<?= e($fid) ?>" name="<?= e($fname) ?>" list="scholarship-names" value="<?= e($value($key)) ?>">
          <datalist id="scholarship-names">
            <?php foreach ($scholarshipNames as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
          </datalist>
          <div class="help">Match the scholarship name exactly and this recipient appears on that scholarship's page.</div>
        </div>

      <?php else: ?>
        <div class="field">
          <label class="title" for="<?= e($fid) ?>"><?= e($field['label']) ?></label>
          <input type="<?= $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text') ?>"
                 id="<?= e($fid) ?>" name="<?= e($fname) ?>"
                 value="<?= e($value($key)) ?>"
                 placeholder="<?= e($field['placeholder'] ?? '') ?>"
                 <?= !empty($field['required']) ? 'required' : '' ?>>
          <?php if (!empty($field['help'])): ?><div class="help"><?= e($field['help']) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endforeach; ?>
  </div>

  <aside class="sticky">
    <div class="panel">
      <button class="btn btn-ink" type="submit" style="width:100%">Save <?= e(strtolower($config['label'])) ?></button>
      <?php if (!$isNew && $name === 'scholarships' && !empty($record['slug'])): ?>
        <a class="btn btn-outline btn-sm" style="width:100%;text-align:center;margin-top:10px" href="/scholarships/<?= e($record['slug']) ?>?preview=1">Preview page</a>
      <?php endif; ?>
      <?php if (!$isNew && $name === 'pages' && !empty($record['slug'])): ?>
        <a class="btn btn-outline btn-sm" style="width:100%;text-align:center;margin-top:10px" href="/<?= e($record['slug']) ?>?preview=1">Preview page</a>
      <?php endif; ?>
    </div>

    <?php if ($name === 'scholarships'): ?>
      <div class="panel">
        <h2>Right now</h2>
        <p class="small muted">Based on today, <?= e(fdate($today)) ?>.</p>
        <?php
          $spec = (($win['type'] ?? 'inherit') === 'inherit') ? $enrollmentSettings : $win;
          $resolved = Leo\Schedule::resolveWindow($spec, $today);
        ?>
        <p style="margin-bottom:.4em">
          <span class="tag <?= $resolved['state'] === 'open' ? 'tag-open' : 'tag-shut' ?>"><?= e($resolved['state']) ?></span>
        </p>
        <p class="small" style="margin:0"><?= e(Leo\Schedule::describeWindow($resolved)) ?>.</p>
      </div>
    <?php endif; ?>

    <?php if (!$isNew): ?>
      <div class="panel">
        <h2>Delete</h2>
        <p class="small muted">This cannot be undone.</p>
        <form method="post" action="/admin/<?= e($name) ?>/<?= e($record['id']) ?>/delete"
              onsubmit="return confirm('Delete this <?= e(strtolower($config['label'])) ?>?')">
          <button class="btn btn-sm btn-outline" type="submit" style="width:100%">Delete <?= e(strtolower($config['label'])) ?></button>
        </form>
      </div>
    <?php endif; ?>
  </aside>
</div>
</form>

<script src="/js/admin.js"></script>
<?php $app->partial('admin-foot'); ?>
