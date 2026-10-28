<div class="title-row">
  <div><h1>Payments</h1><p>Approved claims waiting for a bank transfer. Record the reference once the money is sent.</p></div>
  <div class="actions"><a class="btn btn-plain" href="<?= url('/all/export?status=' . ($tab === 'paid' ? 'paid' : 'approved')) ?>"><?= icon('download') ?>Download CSV</a></div>
</div>

<dl class="sheet position">
  <div><dt>To pay</dt><dd class="num"><?= money($toPay['t']) ?><small><?= $toPay['n'] ?> claim<?= $toPay['n'] === 1 ? '' : 's' ?></small></dd></div>
  <div><dt>Paid in <?= date('Y') ?></dt><dd class="num"><?= money($paidYear) ?></dd></div>
</dl>

<section class="sheet">
  <div class="toolbar">
    <nav class="filter" aria-label="Filter">
      <a href="<?= url('/payments') ?>" <?= $tab === 'approved' ? 'aria-current="page"' : '' ?>>To pay <span class="num"><?= $toPay['n'] ?></span></a>
      <a href="<?= url('/payments?status=paid') ?>" <?= $tab === 'paid' ? 'aria-current="page"' : '' ?>>Paid <span class="num"><?= $paidCount ?></span></a>
    </nav>
  </div>
  <?php if (!$claims): ?>
    <div class="empty"><?= icon('pound', 32) ?><h2><?= $tab === 'approved' ? 'Nothing to pay' : 'No payments yet' ?></h2><p><?= $tab === 'approved' ? 'Claims appear here as soon as they are approved.' : 'Paid claims and their bank references show here.' ?></p></div>
  <?php else: ?>
    <?php foreach ($claims as $c): $mineRow = $c['staff_id'] === $user['id']; ?>
      <div class="payrow">
        <?= avatar(['name' => $c['name'], 'avatar_url' => $c['avatar_url']], 'md') ?>
        <div class="min0">
          <b><a href="<?= url('/claims/' . $c['id']) ?>"><?= e($c['name']) ?>, <?= e($c['title']) ?></a></b>
          <small><?= claim_ref((int) $c['id']) ?> · <?= $tab === 'paid' ? 'Paid ' . date('j M Y', strtotime($c['paid_at'])) . ' · Ref ' . e($c['payment_ref']) : 'Approved ' . e(when($c['decided_at'])) ?></small>
        </div>
        <span class="amt num"><?= money((int) $c['total_pence']) ?></span>
        <?php if ($tab === 'approved'): ?>
          <?php if ($mineRow): ?>
            <p class="hint">Your own claim. Another finance user or the admin pays it.</p>
          <?php else: ?>
            <form method="post" action="<?= url('/claims/' . $c['id'] . '/paid') ?>">
              <?= csrf_field() ?><input type="hidden" name="return" value="payments">
              <label class="sr-only" for="ref<?= $c['id'] ?>">Bank reference</label>
              <input class="input" id="ref<?= $c['id'] ?>" name="payment_ref" maxlength="80" required placeholder="Bank reference">
              <label class="sr-only" for="on<?= $c['id'] ?>">Paid on</label>
              <input class="input" id="on<?= $c['id'] ?>" type="date" name="paid_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
              <button class="btn btn-approve">Mark paid</button>
            </form>
          <?php endif ?>
        <?php else: ?><span></span><?php endif ?>
      </div>
    <?php endforeach ?>
  <?php endif ?>
</section>
