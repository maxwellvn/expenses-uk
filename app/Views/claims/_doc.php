<?php
/** The claim document. Used on its own page and inside the review in-tray ($context = 'review'). */
$context ??= 'page';
$id = (int) $claim['id'];
$gone = ['name' => 'Former staff member', 'title' => ''];
$owner = $people[$claim['staff_id']] ?? $gone;
$decider = $claim['decided_by'] ? ($people[$claim['decided_by']] ?? $gone) : null;
$payer = $claim['paid_by'] ? ($people[$claim['paid_by']] ?? $gone) : null;
$verbs = ['submitted' => 'sent this for review', 'resubmitted' => 'updated and resent it', 'comment' => 'wrote', 'approved' => 'approved it', 'rejected' => 'declined it', 'changes_requested' => 'asked for changes', 'paid' => 'marked it paid'];
$last = null;
foreach (array_reverse($events) as $ev) if (in_array($ev['type'], ['changes_requested', 'rejected'], true)) { $last = $ev; break; }
$isImg = fn(array $f) => in_array($f['mime'], ['image/jpeg', 'image/png', 'image/webp'], true);
$lineOf = [];
foreach ($items as $n => $it) $lineOf[(int) $it['position']] = $n + 1;
// Caption for each receipt: which expense it proves.
$labelFor = function (array $f) use ($items, $lineOf): string {
    if ($f['item_position'] === null || !isset($lineOf[(int) $f['item_position']])) return 'Whole claim';
    $n = $lineOf[(int) $f['item_position']];
    return 'Line ' . $n . ': ' . $items[$n - 1]['description'];
};
$fileIndex = array_flip(array_column($files, 'id'));
$first = $files[0] ?? null;
$who = fn(array $p, string $pid) => $pid === $user['id'] ? 'You' : $p['name'];
?>
<?php if ($mine): ?>
  <?php if ($claim['status'] === 'pending'): ?>
    <div class="callout callout-wait"><?= icon('clock', 18) ?><div><strong>In review since <?= e(when_lc($claim["submitted_at"])) ?></strong>The decision and any questions will appear here.</div></div>
  <?php elseif ($claim['status'] === 'changes_requested'): ?>
    <div class="callout callout-ask"><?= icon('pen', 18) ?><div><strong><?= e($decider['name'] ?? 'The reviewer') ?> asked for changes</strong><?php if ($last && $last['body']): ?><div class="quote"><?= e($last['body']) ?></div><?php endif ?></div><a class="btn btn-primary" href="<?= url("/claims/$id/edit") ?>">Update claim</a></div>
  <?php elseif ($claim['status'] === 'approved'): ?>
    <div class="callout callout-ok"><?= icon('check', 18) ?><div><strong>Approved. Waiting for finance to pay it.</strong>You will see the payment date and bank reference here once it is sent.</div></div>
  <?php elseif ($claim['status'] === 'paid'): ?>
    <div class="callout callout-ok"><?= icon('pound', 18) ?><div><strong>Paid on <?= date('j F Y', strtotime($claim['paid_at'])) ?></strong>Bank reference: <span class="num"><?= e($claim['payment_ref']) ?></span></div></div>
  <?php else: ?>
    <div class="callout callout-no"><?= icon('ban', 18) ?><div><strong>Declined</strong><?php if ($last && $last['body']): ?><div class="quote"><?= e($last['body']) ?></div><?php endif ?></div></div>
  <?php endif ?>
<?php endif ?>

<div class="doc">
  <div class="doc-main">
    <section class="sheet">
      <div class="doc-head">
        <<?= $context === 'review' ? 'h2' : 'h1' ?> class="<?= $context === 'review' ? 'h1-like' : '' ?>"><?= e($claim['title']) ?></<?= $context === 'review' ? 'h2' : 'h1' ?>>
        <div class="meta"><span class="num"><?= claim_ref($id) ?></span><span>Sent <?= e(when($claim['submitted_at'])) ?></span><?= status_chip($claim['status']) ?></div>
        <div class="amount"><span>Total claimed</span><b class="num"><?= money((int) $claim['total_pence']) ?></b></div>
      </div>
      <?php if (!$mine): ?>
        <div class="claimant"><?= avatar($owner, 'md') ?><div class="min0"><b><?= e($owner['name']) ?></b><span><?= e($owner['title']) ?></span></div></div>
      <?php endif ?>
      <table class="lines">
        <thead><tr><th>Date</th><th>Description</th><th class="hide-sm">Category</th><th>Receipt</th><th class="r">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php $mineFiles = array_values(array_filter($files, fn($f) => $f['item_position'] !== null && (int) $f['item_position'] === (int) $it['position'])); ?>
            <tr>
              <td class="d num"><?= date('j M', strtotime($it['spent_on'])) ?></td>
              <td><?= e($it['description']) ?><span class="cat show-sm"><?= e(category_label($it)) ?></span></td>
              <td class="cat hide-sm"><?= e(category_label($it)) ?></td>
              <td class="rc-cell">
                <?php foreach ($mineFiles as $f): ?><button type="button" class="rc-mini" data-show="<?= $fileIndex[$f['id']] ?>" aria-label="Show receipt for <?= e($it['description']) ?>"><?php if ($isImg($f)): ?><img src="<?= url('/files/' . $f['id']) ?>" alt="" loading="lazy" width="32" height="38"><?php else: ?><?= strtoupper(pathinfo($f['stored_name'], PATHINFO_EXTENSION)) ?><?php endif ?></button><?php endforeach ?>
                <?php if (!$mineFiles): ?><span class="muted">See claim</span><?php endif ?>
              </td>
              <td class="r num"><?= money((int) $it['amount_pence']) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="hide-sm"></td><td></td><td class="r num"><?= money((int) $claim['total_pence']) ?></td></tr></tfoot>
      </table>
      <dl class="signoff">
        <div><dt>Claimed by</dt><dd class="sig"><?= e($owner['name']) ?></dd><dd class="dated num"><?= date('j M Y', strtotime($claim['submitted_at'])) ?></dd></div>
        <div><dt>Reviewed by</dt>
          <?php if ($decider && in_array($claim['status'], ['approved', 'paid', 'rejected'], true)): ?>
            <dd class="sig"><?= e($decider['name']) ?></dd><dd class="dated"><span class="<?= $claim['status'] === 'rejected' ? 'v-no' : 'v-ok' ?>"><?= $claim['status'] === 'rejected' ? 'Declined' : 'Approved' ?></span> <span class="num"><?= date('j M Y', strtotime($claim['decided_at'])) ?></span></dd>
          <?php elseif ($claim['status'] === 'changes_requested'): ?>
            <dd class="sig blank">Returned for changes</dd><dd class="dated v-ask">Waiting on the claimant</dd>
          <?php else: ?>
            <dd class="sig blank">Not yet reviewed</dd><dd class="dated">&nbsp;</dd>
          <?php endif ?>
        </div>
        <div><dt>Paid by</dt>
          <?php if ($payer && $claim['status'] === 'paid'): ?>
            <dd class="sig"><?= e($payer['name']) ?></dd><dd class="dated num"><?= date('j M Y', strtotime($claim['paid_at'])) ?> · Ref <?= e($claim['payment_ref']) ?></dd>
          <?php else: ?>
            <dd class="sig blank"><?= $claim['status'] === 'rejected' ? 'Not payable' : 'Not yet paid' ?></dd><dd class="dated">&nbsp;</dd>
          <?php endif ?>
        </div>
      </dl>
    </section>

    <?php if ($canDecide): ?>
      <form class="action" method="post" action="<?= url("/claims/$id/decision") ?>" data-decide>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($context) ?>">
        <div class="field">
          <label for="note<?= $id ?>">Message to <?= e(first_name($owner['name'])) ?> <span class="opt">(needed to decline or ask for changes)</span></label>
          <textarea class="input" id="note<?= $id ?>" name="note" maxlength="2000" data-note></textarea>
        </div>
        <div class="btns">
          <button class="btn btn-decline" name="decision" value="reject"><?= icon('ban') ?>Decline</button>
          <button class="btn btn-plain" name="decision" value="changes"><?= icon('pen') ?>Ask for changes</button>
          <button class="btn btn-approve" name="decision" value="approve"><?= icon('check') ?>Approve <?= money((int) $claim['total_pence']) ?></button>
        </div>
        <p class="err" data-note-err hidden>Write a message so <?= e(first_name($owner['name'])) ?> knows what to do next.</p>
      </form>
    <?php elseif ($canPay): ?>
      <form class="action pay" method="post" action="<?= url("/claims/$id/paid") ?>">
        <?= csrf_field() ?>
        <div class="field"><label for="ref<?= $id ?>">Bank transfer reference</label><input class="input" id="ref<?= $id ?>" name="payment_ref" maxlength="80" required placeholder="Ref shown on the bank statement"></div>
        <div class="field"><label for="on<?= $id ?>">Paid on</label><input class="input" id="on<?= $id ?>" type="date" name="paid_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
        <button class="btn btn-approve btn-lg"><?= icon('pound') ?>Mark <?= money((int) $claim['total_pence']) ?> paid</button>
      </form>
    <?php endif ?>

    <?php if ($claim['notes']): ?>
      <section class="sheet pad"><h2 class="label">Note from <?= e($mine ? 'you' : first_name($owner['name'])) ?></h2><p class="mt-12 pre"><?= e($claim['notes']) ?></p></section>
    <?php endif ?>

    <section class="sheet" id="activity" aria-labelledby="a-h<?= $id ?>">
      <div class="sheet-h"><h2 id="a-h<?= $id ?>">History and messages</h2></div>
      <ol class="feed">
        <?php foreach ($events as $ev): $p = $people[$ev['actor_id']] ?? $gone; ?>
          <li class="t-<?= e($ev['type']) ?>">
            <?= avatar($p, 'sm') ?>
            <div class="min0">
              <p class="act"><b><?= e($who($p, $ev['actor_id'])) ?></b> <span><?= e($verbs[$ev['type']] ?? $ev['type']) ?></span></p>
              <time datetime="<?= e(date('c', strtotime($ev['created_at']))) ?>"><?= e(when($ev['created_at'])) ?></time>
              <?php if ($ev['body']): ?><p class="body"><?= e($ev['body']) ?></p><?php endif ?>
            </div>
          </li>
        <?php endforeach ?>
      </ol>
      <form class="reply" method="post" action="<?= url("/claims/$id/comment") ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="body<?= $id ?>">Message</label>
        <textarea class="input" id="body<?= $id ?>" name="body" maxlength="2000" required placeholder="<?= $mine ? 'Message the reviewer' : 'Ask ' . e(first_name($owner['name'])) . ' a question' ?>"></textarea>
        <button class="btn btn-plain">Send message</button>
      </form>
    </section>

  </div>

  <aside class="viewer" aria-label="Receipts" data-viewer>
    <?php if ($first): $isImg = in_array($first['mime'], ['image/jpeg', 'image/png', 'image/webp'], true); ?>
      <a class="viewer-main" href="<?= url('/files/' . $first['id']) ?>" target="_blank" rel="noopener" data-viewer-link>
        <?php if ($isImg): ?><img src="<?= url('/files/' . $first['id']) ?>" alt="Receipt: <?= e($first['original_name']) ?>" data-viewer-img><?php else: ?><span class="pdf"><b><?= strtoupper(pathinfo($first['stored_name'], PATHINFO_EXTENSION)) ?></b>Open <?= e($first['original_name']) ?></span><?php endif ?>
      </a>
      <div class="viewer-cap"><span data-viewer-name><?= e($labelFor($first)) ?></span><span><?= count($files) ?> receipt<?= count($files) === 1 ? '' : 's' ?> · tap to enlarge</span></div>
      <?php if (count($files) > 1): ?>
        <div class="thumbs">
          <?php foreach ($files as $n => $f): $img = in_array($f['mime'], ['image/jpeg', 'image/png', 'image/webp'], true); ?>
            <button type="button" aria-pressed="<?= $n === 0 ? 'true' : 'false' ?>" aria-label="Show receipt, <?= e($labelFor($f)) ?>" data-src="<?= url('/files/' . $f['id']) ?>" data-name="<?= e($labelFor($f)) ?>" data-img="<?= $img ? '1' : '0' ?>"><?php if ($img): ?><img src="<?= url('/files/' . $f['id']) ?>" alt="" loading="lazy"><?php else: ?><?= strtoupper(pathinfo($f['stored_name'], PATHINFO_EXTENSION)) ?><?php endif ?></button>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    <?php endif ?>
  </aside>
</div>
