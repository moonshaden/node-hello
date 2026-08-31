<?php $app->partial('head', ['description' => $page['summary'] ?? '']); ?>

<div class="page-head">
  <div class="wrap">
    <h1><?= e($page['title'] ?? '') ?></h1>
    <?php if (!empty($page['summary'])): ?><p class="lede"><?= e($page['summary']) ?></p><?php endif; ?>
  </div>
</div>

<?php
// Past this many questions a single prose column reads as a wall, so the page
// gets a jump-to index and every heading an anchor. Mirrored in page.ejs.
$indexFrom = 6;
$article = md_sections($page['body'] ?? '');
$index = count($article['headings']) >= $indexFrom ? $article['headings'] : [];
?>
<section class="band">
  <div class="wrap split">
    <div class="prose">
      <?php if ($index !== []): ?>
      <nav class="page-index" aria-label="On this page">
        <p class="eyebrow">On this page</p>
        <ol>
          <?php foreach ($index as $heading): ?><li><a href="#<?= e($heading['id']) ?>"><?= $heading['html'] ?></a></li><?php endforeach; ?>
        </ol>
      </nav>
      <?php endif; ?>
      <?= $article['html'] ?>
    </div>
    <?php $app->partial('page-aside'); ?>
  </div>
</section>

<?php
// A page can carry a list of programs alongside its prose, the same way it can
// carry a roster. Stored on the page record, so the admin page form still edits
// the copy around it and leaves the list untouched. Mirrored in page.ejs.
$programs = is_array($page['programs'] ?? null) ? $page['programs'] : [];
?>
<?php if ($programs !== []): ?>
<section class="band tint" id="programs">
  <div class="wrap">
    <div class="program-list">
      <?php foreach ($programs as $i => $program): ?>
        <?php $app->partial('program-section', ['program' => $program, 'flip' => $i % 2 === 1]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
// Partners render as programs do -- a titled row with a photo and markdown --
// so they share the one partial rather than duplicating it. Mirrored in page.ejs.
$partners = is_array($page['partners'] ?? null) ? $page['partners'] : [];
$gallery = is_array($page['gallery'] ?? null) ? $page['gallery'] : [];
?>
<?php if ($partners !== []): ?>
<section class="band tint" id="partners">
  <div class="wrap">
    <div class="program-list">
      <?php foreach ($partners as $i => $partner): ?>
        <?php $app->partial('program-section', ['program' => $partner, 'flip' => $i % 2 === 1]); ?>
      <?php endforeach; ?>
    </div>

    <?php if ($gallery !== []): ?>
      <div class="gallery">
        <?php foreach ($gallery as $shot): ?>
          <img src="<?= e(link_url($shot['src'], $basePath)) ?>" alt="<?= e($shot['alt'] ?? '') ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php
// A page can carry a roster of people alongside its prose -- the board is the
// only one today. It is stored on the page record rather than as its own
// content type, so the admin page form still edits the copy around it and
// leaves the roster untouched. Mirrored in page.ejs.
$members = is_array($page['members'] ?? null) ? $page['members'] : [];
?>
<?php if ($members !== []): ?>
<?php
// The chief executive leads the page in a full-width row; everyone else keeps
// the three-up grid. Driven by a lead flag on the record rather than by
// position, so reordering the roster cannot silently promote someone.
$lead = null;
$rest = [];
foreach ($members as $member) {
    if ($lead === null && !empty($member['lead'])) {
        $lead = $member;
        continue;
    }
    $rest[] = $member;
}
?>
<section class="band tint" id="board">
  <div class="wrap">
    <h2 class="section-head">Board of Directors</h2>
    <?php if ($lead !== null): ?>
      <?php $app->partial('board-card', ['member' => $lead, 'lead' => true]); ?>
    <?php endif; ?>
    <?php if ($rest !== []): ?>
      <div class="grid grid-3"<?= $lead !== null ? ' style="margin-top:28px"' : '' ?>>
        <?php foreach ($rest as $member): ?>
          <?php $app->partial('board-card', ['member' => $member]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (($page['slug'] ?? '') === 'contact'): ?>
  <?php
  // Rendered from the site settings rather than the page body, so the address
  // block cannot drift from the footer and the admin only edits it once.
  $details = array_filter([
      'Email' => $site['email'] ?? '',
      'Phone' => $site['phone'] ?? '',
      'Location' => $site['location'] ?? '',
  ], static fn (string $value): bool => trim($value) !== '');
  ?>
  <?php if ($details !== []): ?>
  <section class="band" style="padding-top:0">
    <div class="wrap">
      <dl class="contact-grid">
        <?php foreach ($details as $label => $value): ?>
          <div>
            <dt><?= e($label) ?></dt>
            <dd>
              <?php if ($label === 'Email'): ?><a href="mailto:<?= e($value) ?>"><?= e($value) ?></a>
              <?php elseif ($label === 'Phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $value)) ?>"><?= e($value) ?></a>
              <?php else: ?><?= e($value) ?><?php endif; ?>
            </dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>

<?php $app->partial('page-cta'); ?>
<?php $app->partial('foot'); ?>
