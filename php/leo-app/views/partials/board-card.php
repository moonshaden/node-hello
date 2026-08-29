<?php
// One board member. Mirrors views/partials/board-card.ejs.
//
// The published bios run from 446 to 1,993 characters, so they go through the
// same excerpt() the recipient stories use and the full text sits behind a
// disclosure -- nothing published is lost, and one card cannot end up four
// times the height of its neighbour.
$isLead = !empty($lead);
// The lead has a full-width row to itself, so its bio needs no excerpting.
$story = excerpt($member['bio'] ?? '');
$paragraphs = static fn (string $text): array => preg_split('/\n{2,}/', trim($text)) ?: [];
?>
<article class="card board-member<?= $isLead ? ' board-lead' : '' ?>">
  <?php if (!empty($member['photoUrl'])): ?>
    <img class="portrait portrait-round" src="<?= e(link_url($member['photoUrl'], $basePath)) ?>" alt="<?= e($member['name'] ?? '') ?>" loading="lazy" width="800" height="800">
  <?php else: ?>
    <div class="portrait portrait-round portrait-fallback"><?= e(mb_strtoupper(mb_substr(trim((string) ($member['name'] ?? '?')), 0, 1))) ?></div>
  <?php endif; ?>

  <h3 class="name"><?= e($member['name'] ?? '') ?></h3>
  <p class="role"><?= e($member['role'] ?? '') ?></p>

  <?php if (!empty($member['email']) || !empty($member['phone'])): ?>
    <ul class="member-contact">
      <?php if (!empty($member['email'])): ?><li><a class="wrapany" href="mailto:<?= e($member['email']) ?>"><?= e($member['email']) ?></a></li><?php endif; ?>
      <?php if (!empty($member['phone'])): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $member['phone'])) ?>"><?= e($member['phone']) ?></a></li><?php endif; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($member['statement'])): ?>
    <blockquote class="member-statement">
      <?php foreach ($paragraphs($member['statement']) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
    </blockquote>
  <?php endif; ?>

  <?php if (!empty($member['bio'])): ?>
    <?php if ($story['truncated'] && !$isLead): ?>
      <details class="story">
        <summary>
          <p class="story-excerpt"><?= e($story['text']) ?></p>
          <span class="story-toggle"><span class="when-shut">Read the full bio</span><span class="when-open">Show less</span></span>
        </summary>
        <div class="story-full">
          <?php foreach ($paragraphs($member['bio']) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
        </div>
      </details>
    <?php else: ?>
      <div class="story-full">
        <?php foreach ($paragraphs($member['bio']) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</article>
