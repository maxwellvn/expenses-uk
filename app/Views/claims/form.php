<?php
$errors = $_SESSION['errors'] ?? [];
$editing = $claim !== null;
$lines = old('items', null) ?? array_map(fn($it) => [
    'spent_on' => $it['spent_on'], 'category' => $it['category'], 'category_other' => $it['category_other'] ?? '', 'description' => $it['description'],
    'amount' => number_format($it['amount_pence'] / 100, 2, '.', ''),
], $items);
if (!$lines) $lines = [['spent_on' => date('Y-m-d')]];
$total = array_sum(array_map(fn($l) => parse_pence((string) ($l['amount'] ?? '')) ?? 0, $lines));
$titleVal = old('title', $claim['title'] ?? '');
$notesVal = old('notes', $claim['notes'] ?? '');
?>
<a class="back" href="<?= url($editing ? '/claims/' . $claim['id'] : '/') ?>"><?= icon('back') ?><?= $editing ? claim_ref((int) $claim['id']) : 'My claims' ?></a>
<div class="title-row">
  <div><h1><?= $editing ? 'Update your claim' : 'New claim' ?></h1><p><?= $editing ? 'Make the changes asked for, then send it back for review.' : 'Attach the receipt, say what you paid for, and send it for review.' ?></p></div>
</div>

<?php if ($editing && !empty($request)): ?>
  <div class="callout callout-ask"><?= icon('pen', 18) ?><div><strong>What the reviewer asked for</strong><div class="quote"><?= e($request) ?></div></div></div>
<?php endif ?>

<form class="claim-form" method="post" action="<?= url($editing ? '/claims/' . $claim['id'] : '/claims') ?>" enctype="multipart/form-data" data-claim-form novalidate>
  <?= csrf_field() ?>

  <aside class="receipt-panel">
    <section class="sheet pad" aria-labelledby="r-h">
      <h2 class="label" id="r-h"><span class="step-n">1</span>Receipts</h2>
      <p class="hint mt-12">A photo or PDF of each receipt or invoice, up to 10 MB each.</p>
      <label class="drop mt-12" data-drop>
        <input type="file" name="proof[]" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" data-proof <?= isset($errors['proof']) ? 'aria-invalid="true" aria-describedby="proof-err"' : '' ?>>
        <span class="drop-big"><?= icon('camera', 28) ?></span>
        <b class="drop-big">Take a photo or choose a file</b>
        <span class="hint drop-big">or drop it here</span>
        <span class="drop-small"><?= icon('plus') ?> Add another receipt</span>
      </label>
      <?php if (isset($errors['proof'])): ?><p class="err mt-12" id="proof-err" role="alert"><?= e($errors['proof']) ?></p><?php endif ?>
      <ul class="picked" data-picked><?php foreach ($files as $f): $img = in_array($f['mime'], ['image/jpeg', 'image/png', 'image/webp'], true); ?><li data-existing>
          <?php if ($img): ?><img class="pk-img" src="<?= url('/files/' . $f['id']) ?>" alt=""><?php else: ?><span class="pk-img"><?= strtoupper(pathinfo($f['stored_name'], PATHINFO_EXTENSION)) ?></span><?php endif ?>
          <div class="pk-cap"><?= e($f['original_name']) ?></div>
          <label class="pk-x" title="Remove"><input class="sr-only" type="checkbox" name="remove_files[]" value="<?= (int) $f['id'] ?>" data-remove-existing><?= icon('x', 14) ?><span class="sr-only">Remove <?= e($f['original_name']) ?></span></label>
        </li><?php endforeach ?></ul>
    </section>
  </aside>

  <div class="stack">
    <section class="sheet" aria-labelledby="l-h">
      <div class="sheet-h"><h2 id="l-h"><span class="step-n">2</span>What you paid for</h2><span>One line per receipt</span></div>
      <div class="items-h" aria-hidden="true"><span>Date paid</span><span>Category</span><span>Description</span><span class="r">Amount</span><span></span></div>
      <div data-lines>
        <?php foreach (array_values($lines) as $i => $it) { $err = $errors["item$i"] ?? null; require __DIR__ . '/line.php'; } ?>
      </div>
      <?php if (isset($errors['items'])): ?><p class="err items-foot"><?= e($errors['items']) ?></p><?php endif ?>
      <div class="items-foot"><button type="button" class="btn btn-plain btn-sm" data-add-line><?= icon('plus') ?>Add another line</button></div>
      <template data-line-template><?php $i = '__i__'; $it = []; $err = null; require __DIR__ . '/line.php'; ?></template>
    </section>

    <section class="sheet pad" aria-labelledby="n-h">
      <h2 class="label" id="n-h"><span class="step-n">3</span>Anything else</h2>
      <details class="more mt-12" <?= ($titleVal !== '' || $notesVal !== '' || $editing) ? 'open' : '' ?>>
        <summary><?= icon('plus') ?>Add a claim name or a note for the reviewer</summary>
        <div class="field">
          <label for="title">Claim name <span class="opt">(optional, we use the first line if blank)</span></label>
          <input class="input" id="title" name="title" maxlength="160" value="<?= e($titleVal) ?>" placeholder="Leeds outreach, November">
        </div>
        <div class="field">
          <label for="notes">Note for the reviewer <span class="opt">(optional)</span></label>
          <textarea class="input" id="notes" name="notes" maxlength="2000" placeholder="Who it was for, or why it cost more than usual"><?= e($notesVal) ?></textarea>
        </div>
        <?php if ($editing): ?>
          <div class="field">
            <label for="message">What you changed <span class="opt">(optional)</span></label>
            <textarea class="input" id="message" name="message" maxlength="2000" placeholder="Added the missing taxi receipt"><?= e(old('message')) ?></textarea>
          </div>
        <?php endif ?>
      </details>
    </section>

    <section class="sheet send">
      <div class="tot"><span data-count><?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?></span><b class="num" data-total><?= money($total) ?></b></div>
      <button class="btn btn-primary btn-lg" data-submit><?= $editing ? 'Send changes for review' : 'Send for review' ?></button>
    </section>
  </div>
</form>
