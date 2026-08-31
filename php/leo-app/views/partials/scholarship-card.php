<article class="card">
  <span class="pill pill-<?= e($scholarship['window']['state']) ?>"><?= $scholarship['isOpen'] ? 'Open' : ($scholarship['window']['state'] === 'upcoming' ? 'Opens later' : 'Closed') ?></span>
  <h3 style="margin-top:14px"><a href="<?= e($basePath) ?>/scholarships/<?= e($scholarship['slug'] ?? '') ?>"><?= e($scholarship['name'] ?? '') ?></a></h3>
  <?php if (!empty($scholarship['amount'])): ?><div class="amount"><?= e($scholarship['amount']) ?></div><?php endif; ?>
  <p style="margin-top:12px"><?= e($scholarship['summary'] ?? '') ?></p>
  <div class="card-foot">
    <a class="btn btn-sm <?= $scholarship['isOpen'] ? 'btn-ink' : 'btn-outline' ?>" href="<?= e($basePath) ?>/scholarships/<?= e($scholarship['slug'] ?? '') ?>">
      <?= $scholarship['isOpen'] ? 'View and apply' : 'View details' ?>
    </a>
    <span class="small muted"><?= e($scholarship['statusLabel']) ?></span>
  </div>
</article>
