<?php /** One claim in a ledger. $c row (with thumb_id, item_count, file_count), $href, $person (bool) */ $t = strtotime($c['submitted_at']); ?>
<li class="entry<?= !empty($person) ? ' with-person' : '' ?>">
  <?php if ($c['thumb_id']): ?><img class="thumb" src="<?= url('/files/' . $c['thumb_id']) ?>" alt="" loading="lazy" width="44" height="52"><?php else: ?><span class="thumb"><?= icon('receipt', 18) ?></span><?php endif ?>
  <?php if (!empty($person)): ?>
    <div class="person min0"><b><?= e($c['name']) ?></b><small><?= e($c['staff_title']) ?></small></div>
  <?php else: ?>
    <span class="when num"><?= date('j M', $t) ?></span>
  <?php endif ?>
  <div class="min0">
    <a class="cover" href="<?= e($href) ?>"><b><?= e($c['title']) ?></b></a>
    <small><?= claim_ref((int) $c['id']) ?> · <?= (int) $c['item_count'] ?> item<?= $c['item_count'] == 1 ? '' : 's' ?> · <?= (int) $c['file_count'] ?> receipt<?= $c['file_count'] == 1 ? '' : 's' ?><?= !empty($person) ? ' · ' . e(when($c['submitted_at'])) : '' ?></small>
  </div>
  <?= status_chip($c['status']) ?>
  <span class="amt num"><?= money((int) $c['total_pence']) ?></span>
</li>
