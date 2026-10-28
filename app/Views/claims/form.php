<?php
$errors = $_SESSION['errors'] ?? [];
$editing = $claim !== null;
$lines = old('items', null) ?? array_map(fn($it) => [
    'spent_on' => $it['spent_on'], 'category' => $it['category'], 'category_other' => $it['category_other'] ?? '', 'description' => $it['description'],
    'amount' => number_format($it['amount_pence'] / 100, 2, '.', ''),
], $items);
if (!$lines) $lines = [['spent_on' => date('Y-m-d')]];
$total = array_sum(array_map(fn($l) => parse_pence((string) ($l['amount'] ?? '')) ?? 0, $lines));

// Receipts already on the claim, by line. Older claims stored them for the whole claim; those show on the first line.
$byId = array_column($files, null, 'id');
$oldKeep = old('keep', null);
$filesFor = function ($key, int $pos) use ($files, $byId, $oldKeep): array {
    if ($oldKeep !== null) return array_values(array_filter(array_map(fn($id) => $byId[(int) $id] ?? null, (array) ($oldKeep[$key] ?? []))));
    return array_values(array_filter($files, fn($f) => (int) ($f['item_position'] ?? 0) === $pos));
};
$noteVal = old('notes', $editing ? '' : ($claim['notes'] ?? ''));
$next = $lines ? max(array_map('intval', array_keys($lines))) + 1 : 1;
?>
<a class="back" href="<?= url($editing ? '/claims/' . $claim['id'] : '/') ?>"><?= icon('back') ?><?= $editing ? claim_ref((int) $claim['id']) : 'My claims' ?></a>
<div class="title-row">
  <div><h1><?= $editing ? 'Update your claim' : 'New claim' ?></h1><p><?= $editing ? 'Make the changes asked for, then send it back for review.' : 'One line for each thing you paid for, with its receipt.' ?></p></div>
</div>

<?php if ($editing && !empty($request)): ?>
  <div class="callout callout-ask"><?= icon('pen', 18) ?><div><strong>What the reviewer asked for</strong><div class="quote"><?= e($request) ?></div></div></div>
<?php endif ?>

<form class="claim-form" method="post" action="<?= url($editing ? '/claims/' . $claim['id'] : '/claims') ?>" enctype="multipart/form-data" data-claim-form data-next="<?= $next ?>" novalidate>
  <?= csrf_field() ?>
  <section class="sheet" aria-labelledby="l-h">
    <div class="sheet-h"><h2 id="l-h">What you paid for</h2><span>Photo or PDF, up to 10 MB each</span></div>
    <div class="items-h" aria-hidden="true"><span>Date paid</span><span>Category</span><span>Description</span><span class="r">Amount</span><span></span></div>
    <div data-lines>
      <?php $pos = 0; foreach ($lines as $i => $it) { $err = $errors["item$i"] ?? null; $lineFiles = $filesFor($i, $pos++); require __DIR__ . '/line.php'; } ?>
    </div>
    <?php if (isset($errors['items'])): ?><p class="err items-foot" role="alert"><?= e($errors['items']) ?></p><?php endif ?>
    <div class="items-foot"><button type="button" class="btn btn-plain btn-sm" data-add-line><?= icon('plus') ?>Add another expense</button></div>
    <template data-line-template><?php $i = '__i__'; $it = []; $err = null; $lineFiles = []; require __DIR__ . '/line.php'; ?></template>

    <div class="send">
      <div class="tot"><span data-count><?= count($lines) ?> expense<?= count($lines) === 1 ? '' : 's' ?></span><b class="num" data-total><?= money($total) ?></b></div>
      <div class="send-actions">
        <details class="more" <?= $noteVal !== '' ? 'open' : '' ?>>
          <summary data-note-toggle><span class="if-closed"><?= icon('plus') ?><?= $editing ? 'Add a note about what you changed' : 'Add a note for the reviewer' ?></span><span class="if-open"><?= icon('x') ?>Cancel note</span></summary>
          <label class="sr-only" for="notes">Note for the reviewer</label>
          <textarea class="input" id="notes" name="notes" maxlength="2000" placeholder="<?= $editing ? 'Added the itemised receipt' : 'Anything the reviewer should know' ?>"><?= e($noteVal) ?></textarea>
        </details>
        <button class="btn btn-primary btn-lg" data-submit><?= $editing ? 'Send changes for review' : 'Send for review' ?></button>
      </div>
    </div>
  </section>
</form>
