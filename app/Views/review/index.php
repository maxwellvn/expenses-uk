<?php $openId = $open['claim']['id'] ?? 0; ?>
<div class="title-row">
  <div><h1>Review</h1><p><?php if ($counts['pending']): ?><span class="num"><?= $counts['pending'] ?></span> claim<?= $counts['pending'] === 1 ? '' : 's' ?> waiting, <span class="num"><?= money($waitingTotal) ?></span> in total. Oldest first.<?php else: ?>Nothing is waiting for review.<?php endif ?></p></div>
</div>

<div class="tray<?= $explicit ? ' has-open' : '' ?>">
  <section class="sheet tray-list" aria-label="Claims">
    <div class="tray-tools">
      <nav class="filter" aria-label="Filter">
        <?php foreach ($tabs as $k => $label): ?>
          <a href="<?= url('/review?status=' . $k) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span class="num"><?= $counts[$k] ?></span></a>
        <?php endforeach ?>
      </nav>
      <form class="search" role="search" method="get" action="<?= url('/review') ?>">
        <input type="hidden" name="status" value="<?= e($tab) ?>">
        <?= icon('search') ?><label class="sr-only" for="q">Search by name or claim</label>
        <input class="input" id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or claim">
      </form>
    </div>
    <?php if ($claims): ?>
      <ul class="tray-rows">
        <?php foreach ($claims as $c): $days = (int) floor((time() - strtotime($c['submitted_at'])) / 86400); ?>
          <li class="tray-row" <?= (int) $c['id'] === (int) $openId ? 'aria-current="true"' : '' ?>>
            <?= avatar(['name' => $c['name'], 'avatar_url' => $c['avatar_url']], 'md') ?>
            <div class="min0">
              <a class="cover" href="<?= url('/review?status=' . $tab . '&claim=' . $c['id'] . ($q !== '' ? '&q=' . rawurlencode($q) : '')) ?>"><b><?= e($c['name']) ?></b></a>
              <small><?= e($c['title']) ?></small>
              <small><?php if ($c['status'] === 'pending' && $days >= 7): ?><span class="late">Waiting <?= $days ?> days</span><?php else: ?><?= e(when($c['submitted_at'])) ?><?php endif ?> · <?= (int) $c['file_count'] ?> receipt<?= $c['file_count'] == 1 ? '' : 's' ?></small>
            </div>
            <span class="amt num"><?= money((int) $c['total_pence']) ?></span>
          </li>
        <?php endforeach ?>
      </ul>
    <?php else: ?>
      <p class="tray-empty"><?= $q !== '' ? 'No claims match “' . e($q) . '”.' : ($tab === 'pending' ? 'All caught up. New claims appear here, oldest first.' : 'No claims here.') ?></p>
    <?php endif ?>
  </section>

  <div class="tray-detail min0">
    <?php if ($open): ?>
      <a class="back" href="<?= url('/review?status=' . $tab) ?>"><?= icon('back') ?>All claims to review</a>
      <?php extract($open); $context = 'review'; require __DIR__ . '/../claims/_doc.php'; ?>
    <?php else: ?>
      <section class="sheet empty"><?= icon('check', 32) ?><h2>Nothing to open</h2><p>Pick a claim from the list.</p></section>
    <?php endif ?>
  </div>
</div>
