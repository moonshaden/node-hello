<?php
// One program on the Programs & Partnerships page.
//
// Mirrors views/partials/program-section.ejs. Same image-beside-text row the
// board lead uses, alternating side so three of them do not read as one column
// of repeats. The copy is markdown so the two partner links stay editable in
// /admin rather than being baked into the template.
//
// The lead paragraph shows and the rest opens behind "Read more", because these
// three run 900-1,500 characters each. The split is on the blank line between
// paragraphs, NOT on a character count: these bodies carry markdown links, and a
// count cuts one in half. Both builds split the same way -- a test asserts the
// rendered pages agree.
$isFlipped = !empty($flip);
// Hiding has to earn its keep: the three programs keep 541, 1,346 and 731
// characters behind the toggle, while the one partner on /community -- which
// shares this partial -- had only 116, which is more friction than the text it
// saves. Under this and the whole body just renders. Mirrored in the ejs.
$discloseFrom = 240;
$paragraphs = array_values(array_filter(
    preg_split('/\n{2,}/', (string) ($program['body'] ?? '')),
    static fn ($p) => trim($p) !== ''
));
$remainder = implode("\n\n", array_slice($paragraphs, 1));
$disclose = strlen($remainder) >= $discloseFrom;
$lead = $disclose ? ($paragraphs[0] ?? '') : (string) ($program['body'] ?? '');
$rest = $disclose ? $remainder : '';
?>
<article class="card program<?= $isFlipped ? ' program-flip' : '' ?>">
  <?php if (!empty($program['photoUrl'])): ?>
    <img class="program-photo" src="<?= e(asset_url($program['photoUrl'], $basePath)) ?>" alt="<?= e($program['alt'] ?? $program['name'] ?? '') ?>" loading="lazy" width="800" height="800">
  <?php endif; ?>
  <h3 class="program-name" id="<?= e($program['slug'] ?? '') ?>"><?= e($program['name'] ?? '') ?></h3>
  <div class="program-body">
    <?= md($lead) ?>
    <?php if ($rest !== ''): ?>
      <?php /* The lead stays outside the disclosure: it is the first paragraph
          of the same copy rather than a summary of it, so it should not
          disappear when the rest opens -- and `summary` may only carry phrasing
          content. */ ?>
      <details class="story">
        <summary>
          <span class="story-toggle"><span class="when-shut">Read more</span><span class="when-open">Show less</span></span>
        </summary>
        <div class="story-rest"><?= md($rest) ?></div>
      </details>
    <?php endif; ?>
  </div>
</article>
