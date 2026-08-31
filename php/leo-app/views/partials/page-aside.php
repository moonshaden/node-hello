<?php
/**
 * Standing sidebar for the content pages.
 *
 * Every value comes from the enrollment window and the site settings, so it
 * cannot fall out of date with the ribbon or the footer, and it needs no
 * per-page editing. The contact page has its own detail row and would only
 * repeat itself, so it opts out.
 */
if (($page['slug'] ?? '') === 'contact') {
    return;
}
?>
<aside class="sidebar-card">
  <p class="eyebrow" style="margin-bottom:.6rem">At a glance</p>
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
      <dd class="soft"><?= e($enrollmentSettings['awardedNote']) ?></dd>
    <?php endif; ?>
    <?php if (!empty($site['email'])): ?>
      <dt>Questions</dt>
      <dd class="wrapany"><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></dd>
    <?php endif; ?>
  </dl>
  <a class="btn btn-sm btn-outline" href="<?= e($basePath) ?>/scholarships" style="margin-top:4px">See all scholarships</a>
</aside>
