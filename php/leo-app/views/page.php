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
// A page can carry a roster of people alongside its prose -- the board is the
// only one today. It is stored on the page record rather than as its own
// content type, so the admin page form still edits the copy around it and
// leaves the roster untouched. Mirrored in page.ejs.
$members = is_array($page['members'] ?? null) ? $page['members'] : [];
?>
<?php if ($members !== []): ?>
<section class="band tint" id="board">
  <div class="wrap">
    <h2 class="section-head">Board of Directors</h2>
    <div class="grid grid-3">
      <?php foreach ($members as $member): ?>
        <?php $app->partial('board-card', ['member' => $member]); ?>
      <?php endforeach; ?>
    </div>
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
