<?php $app->partial('head'); ?>

<?php // The homepage led with a three-slide carousel above the hero. It no
      // longer does -- the awarded student is the first thing on the site, and
      // a carousel above them was the one element competing for that. The
      // slides, their images and their /admin section are all still in place,
      // so this is a change of what the page renders, not a deletion of
      // anything the client can edit. ?>
<section class="hero<?= $heroStudent !== null ? ' hero-centred' : '' ?>">
  <?php if ($heroStudent !== null): ?>
    <div class="wrap hero-stage">
      <?php // The visible headline is gone -- the student is the headline now.
            // The h1 stays in the document, hidden, because a page with no
            // heading is a page screen readers and search engines cannot
            // outline. Same words as before; only the display changes. ?>
      <h1 class="visually-hidden">Every scholarship is a door someone walks through.</h1>
      <p class="eyebrow"><?= e($site['location'] ?? '') ?> · 501(c)(3) nonprofit</p>

      <?php // One of the awarded students, cut free of their own photograph and
            // stood at the centre of the page. The client asked for the awards
            // to be the focus, and this is the strongest reading of that: the
            // first thing on the site is a student who is plainly glad to be at
            // university. Everything shown is their own record -- the name, the
            // award, the school, and a line lifted verbatim out of the bio they
            // published. ?>
      <figure class="hero-student">
        <div class="hero-student-stage">
          <img class="hero-student-cut" src="<?= e(link_url($heroStudent['cutoutUrl'], $basePath)) ?>"
               alt="<?= e($heroStudent['name'] ?? '') ?>, holding up a Grand Canyon University pin"
               width="700" height="804">
        </div>
        <figcaption class="hero-student-note">
          <?php if (!empty($heroStudent['heroQuote'])): ?>
            <blockquote class="hero-student-quote">&ldquo;<?= e($heroStudent['heroQuote']) ?>&rdquo;</blockquote>
          <?php endif; ?>
          <p class="hero-student-who">
            <strong><?= e($heroStudent['name'] ?? '') ?></strong>
            <span><?= e($heroStudent['scholarship'] ?? '') ?><?= !empty($heroStudent['school']) ? ' · ' . e($heroStudent['school']) : '' ?></span>
          </p>
        </figcaption>
      </figure>

      <p class="hero-mission"><?= e($site['mission'] ?? '') ?></p>

      <div class="actions">
        <a class="btn btn-gold" href="<?= e($basePath) ?>/scholarships">
          <?= $enrollment['state'] === 'open' ? 'Apply for a scholarship' : 'See available scholarships' ?>
        </a>
        <a class="btn btn-outline" style="color:#fff" href="<?= e($basePath) ?>/recipients">Meet our recipients</a>
      </div>

      <?php // The deadline is still what applicants come here for, so it stays
            // in the hero. The ribbon above the page carries it on every route,
            // so this is the fuller statement of it rather than the only one. ?>
      <p class="hero-deadline">
        <?php if ($enrollment['state'] === 'open' && $enrollment['daysUntilClose'] !== null): ?>
          <span class="hero-deadline-count"><?= (int) $enrollment['daysUntilClose'] ?></span>
          <span>days left to apply<?php if (!empty($enrollment['closesOn'])): ?> · closes <?= e(fdate($enrollment['closesOn'])) ?><?php endif; ?></span>
        <?php elseif ($enrollment['daysUntilOpen'] !== null): ?>
          <span class="hero-deadline-count"><?= (int) $enrollment['daysUntilOpen'] ?></span>
          <span>days until applications open<?php if (!empty($enrollment['opensOn'])): ?> · opens <?= e(fdate($enrollment['opensOn'])) ?><?php endif; ?></span>
        <?php else: ?>
          <span class="hero-deadline-count">Open</span>
          <span>applications accepted year-round</span>
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="wrap hero-grid">
      <div class="hero-copy">
        <p class="eyebrow"><?= e($site['location'] ?? '') ?> · 501(c)(3) nonprofit</p>
        <h1>Every scholarship is a door someone walks through.</h1>
        <p><?= e($site['mission'] ?? '') ?></p>
        <div class="actions">
          <a class="btn btn-gold" href="<?= e($basePath) ?>/scholarships">
            <?= $enrollment['state'] === 'open' ? 'Apply for a scholarship' : 'See available scholarships' ?>
          </a>
          <a class="btn btn-outline" style="color:#fff" href="<?= e($basePath) ?>/recipients">Meet our recipients</a>
        </div>
      </div>

      <!-- The deadline is what applicants come here for, so it is never more than
           one glance away, in or out of season. -->
      <aside class="deadline">
        <div class="count">
          <?php if ($enrollment['state'] === 'open' && $enrollment['daysUntilClose'] !== null): ?>
            <?= (int) $enrollment['daysUntilClose'] ?><small>days left to apply</small>
          <?php elseif ($enrollment['daysUntilOpen'] !== null): ?>
            <?= (int) $enrollment['daysUntilOpen'] ?><small>days until applications open</small>
          <?php else: ?>
            Open<small>applications accepted year-round</small>
          <?php endif; ?>
        </div>
        <dl>
          <?php if (!empty($enrollment['opensOn'])): ?>
            <dt>Applications open</dt>
            <dd><?= e(fdate($enrollment['opensOn'])) ?></dd>
          <?php endif; ?>
          <?php if (!empty($enrollment['closesOn'])): ?>
            <dt>Applications close</dt>
            <dd><?= e(fdate($enrollment['closesOn'])) ?></dd>
          <?php endif; ?>
          <?php if (!empty($enrollmentSettings['awardedNote'])): ?>
            <dt>Awards</dt>
            <dd style="font-weight:400;color:#c6d5e2;font-size:.92rem"><?= e($enrollmentSettings['awardedNote']) ?></dd>
          <?php endif; ?>
        </dl>
      </aside>
    </div>
  <?php endif; ?>
</section>

<?php if (!empty($site['impact'])): ?>
<section class="impact">
  <div class="wrap">
    <div class="impact-head">
      <p class="eyebrow">Our impact</p>
      <h2><?= e($site['impactTitle'] ?? 'What the scholarships have added up to.') ?></h2>
    </div>
    <div class="impact-grid">
      <?php foreach ($site['impact'] as $item): ?>
        <div>
          <div class="value"><?= e($item['value'] ?? '') ?></div>
          <div class="label"><?= e($item['label'] ?? '') ?></div>
          <?php if (!empty($item['detail'])): ?>
            <p class="detail"><?= e($item['detail']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $app->partial('pillars'); ?>

<?php if ($announcements !== []): ?>
<section class="band" style="padding-bottom:0">
  <div class="wrap stack">
    <?php foreach ($announcements as $note): ?>
      <div class="notice">
        <div>
          <h3><?= e($note['title'] ?? '') ?></h3>
          <p><?= e($note['body'] ?? '') ?></p>
        </div>
        <?php if (!empty($note['ctaUrl']) && !empty($note['ctaLabel'])): ?>
          <a class="btn btn-sm btn-ink" href="<?= e(link_url($note['ctaUrl'], $basePath)) ?>"><?= e($note['ctaLabel']) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Recipients lead the page, ahead of the application funnel: the awards
     already made are the strongest case for both students and donors. -->
<section class="band awardee-scene" id="awardees">
  <div class="wrap">
    <div class="band-head">
      <div>
        <p class="eyebrow">Scholarships awarded</p>
        <h2>The students behind the numbers</h2>
        <p>Every award on this page went to a student in Arizona who applied, qualified, and got to keep going.</p>
      </div>
      <a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/recipients">All recipients</a>
    </div>

    <?php if ($awardees !== []): ?>
      <?php $app->partial('awardee-stage', ['awardees' => $awardees]); ?>
    <?php else: ?>
      <div class="empty">
        <p><strong>No recipients published yet.</strong></p>
        <p class="small">Add this year's awarded students in the admin area and they will appear here first.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="band tint">
  <div class="wrap">
    <div class="band-head">
      <div>
        <p class="eyebrow"><?= $enrollment['state'] === 'open' ? 'Now accepting applications' : 'Plan ahead' ?></p>
        <h2><?= $openScholarships !== [] ? 'Scholarships open right now' : 'Scholarships offered each year' ?></h2>
        <p>
          <?php if ($enrollment['state'] === 'open'): ?>
            The enrollment period closes <?= e(fdate($enrollment['closesOn'])) ?>. Review the criteria and apply to the award that best fits you.
          <?php else: ?>
            Applications reopen <?= e(fdate($enrollment['opensOn'])) ?>. Review the criteria now so you are ready.
          <?php endif; ?>
        </p>
      </div>
      <a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/scholarships">All scholarships</a>
    </div>

    <div class="grid grid-3">
      <?php foreach (array_slice($openScholarships !== [] ? $openScholarships : $scholarships, 0, 3) as $scholarship): ?>
        <?php $app->partial('scholarship-card', ['scholarship' => $scholarship]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $app->partial('foot'); ?>
