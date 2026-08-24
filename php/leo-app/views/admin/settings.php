<?php $app->partial('admin-head'); ?>

<div class="admin-head">
  <div>
    <h1>Site settings</h1>
    <p class="muted" style="margin:0">Organisation details, the impact numbers on the homepage, and the enrollment period.</p>
  </div>
</div>

<?php if ($saved): ?><div class="saved">Settings saved.</div><?php endif; ?>

<form method="post" action="<?= e($basePath) ?>/admin/settings">
<div class="formgrid">
  <div>
    <div class="panel">
      <h2>The enrollment period</h2>
      <p class="small muted">
        Every scholarship follows this period unless it has been given its own.
        A repeating period rolls over by itself each year, so nothing needs updating in November.
      </p>

      <div class="field">
        <label class="title" for="enrollmentType">Period type</label>
        <select id="enrollmentType" name="enrollmentType">
          <option value="annual"<?= ($enrollmentSettings['type'] ?? '') !== 'fixed' ? ' selected' : '' ?>>Repeats every year</option>
          <option value="fixed"<?= ($enrollmentSettings['type'] ?? '') === 'fixed' ? ' selected' : '' ?>>One-off dates</option>
        </select>
      </div>

      <div class="row2">
        <div class="field">
          <label class="title" for="enrollmentOpensOn">Opens</label>
          <input type="text" id="enrollmentOpensOn" name="enrollmentOpensOn" value="<?= e($enrollmentSettings['opensOn'] ?? '') ?>">
          <div class="help">MM-DD for a repeating period (11-01), YYYY-MM-DD for one-off dates.</div>
        </div>
        <div class="field">
          <label class="title" for="enrollmentClosesOn">Closes</label>
          <input type="text" id="enrollmentClosesOn" name="enrollmentClosesOn" value="<?= e($enrollmentSettings['closesOn'] ?? '') ?>">
          <div class="help">A close date earlier than the open date wraps the year.</div>
        </div>
      </div>

      <div class="field">
        <label class="title" for="instructions">Instructions shown above the scholarship list</label>
        <textarea id="instructions" name="instructions"><?= e($enrollmentSettings['instructions'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="title" for="awardedNote">Note shown on each scholarship page</label>
        <textarea id="awardedNote" name="awardedNote" rows="2"><?= e($enrollmentSettings['awardedNote'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="panel">
      <h2>Organisation</h2>
      <div class="row2">
        <div class="field">
          <label class="title" for="name">Short name</label>
          <input type="text" id="name" name="name" value="<?= e($site['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="title" for="legalName">Legal name</label>
          <input type="text" id="legalName" name="legalName" value="<?= e($site['legalName'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label class="title" for="tagline">Tagline</label>
        <input type="text" id="tagline" name="tagline" value="<?= e($site['tagline'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="title" for="mission">Mission statement</label>
        <textarea id="mission" name="mission"><?= e($site['mission'] ?? '') ?></textarea>
      </div>
      <div class="row2">
        <div class="field">
          <label class="title" for="email">Email</label>
          <input type="text" id="email" name="email" value="<?= e($site['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="title" for="phone">Phone</label>
          <input type="text" id="phone" name="phone" value="<?= e($site['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="row2">
        <div class="field">
          <label class="title" for="location">Location</label>
          <input type="text" id="location" name="location" value="<?= e($site['location'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="title" for="ein">EIN</label>
          <input type="text" id="ein" name="ein" value="<?= e($site['ein'] ?? '') ?>">
        </div>
      </div>
      <div class="row2">
        <div class="field">
          <label class="title" for="donateUrl">Donate link</label>
          <input type="text" id="donateUrl" name="donateUrl" value="<?= e($site['donateUrl'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="title" for="facebookUrl">Facebook</label>
          <input type="text" id="facebookUrl" name="facebookUrl" value="<?= e($site['facebookUrl'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label class="title" for="timezone">Timezone</label>
        <input type="text" id="timezone" name="timezone" value="<?= e($site['timezone'] ?? '') ?>">
        <div class="help">Decides when a deadline actually ends. Phoenix does not observe daylight saving.</div>
      </div>
    </div>

    <div class="panel">
      <h2>Impact numbers</h2>
      <p class="small muted">The heading and three figures in the navy band on the homepage.</p>

      <div class="field">
        <label class="title" for="impactTitle">Band heading</label>
        <input type="text" id="impactTitle" name="impactTitle" value="<?= e($site['impactTitle'] ?? '') ?>">
        <div class="help">The claim the figures back up. Leave blank to fall back to a default.</div>
      </div>

      <?php foreach ([0, 1, 2] as $index): ?>
        <div class="row2">
          <div class="field">
            <label class="title" for="impact<?= $index ?>value">Figure <?= $index + 1 ?></label>
            <input type="text" id="impact<?= $index ?>value" name="impact<?= $index ?>value" value="<?= e($site['impact'][$index]['value'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="title" for="impact<?= $index ?>label">Caption <?= $index + 1 ?></label>
            <input type="text" id="impact<?= $index ?>label" name="impact<?= $index ?>label" value="<?= e($site['impact'][$index]['label'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label class="title" for="impact<?= $index ?>detail">Supporting line <?= $index + 1 ?></label>
          <input type="text" id="impact<?= $index ?>detail" name="impact<?= $index ?>detail" value="<?= e($site['impact'][$index]['detail'] ?? '') ?>">
          <div class="help">Optional. One sentence of context under the caption.</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <aside class="sticky">
    <div class="panel">
      <button class="btn btn-ink" type="submit" style="width:100%">Save settings</button>
    </div>
    <div class="panel">
      <h2>Right now</h2>
      <p style="margin-bottom:.4em"><span class="tag <?= $enrollment['state'] === 'open' ? 'tag-open' : 'tag-shut' ?>"><?= e($enrollment['state']) ?></span></p>
      <p class="small" style="margin:0"><?= e($enrollmentLabel) ?>, as of <?= e(fdate($today)) ?>.</p>
    </div>
  </aside>
</div>
</form>

<?php $app->partial('admin-foot'); ?>
