<?php
/** One expense line with its own receipts. $i form key (or __i__ in the template), $it values, $err message, $lineFiles kept receipts. */
$lineFiles ??= [];
?>
<div class="item" data-line>
  <div class="field">
    <label class="sr-only" for="d<?= $i ?>">Date paid</label>
    <input class="input" id="d<?= $i ?>" type="date" name="items[<?= $i ?>][spent_on]" value="<?= e($it['spent_on'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
  </div>
  <div class="field">
    <label class="sr-only" for="c<?= $i ?>">Category</label>
    <select class="input" id="c<?= $i ?>" name="items[<?= $i ?>][category]" required>
      <option value="">Category</option>
      <?php foreach (CATEGORIES as $cat): ?><option value="<?= e($cat) ?>" <?= ($it['category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach ?>
    </select>
  </div>
  <div class="field i-desc">
    <label class="sr-only" for="t<?= $i ?>">What it was for</label>
    <input class="input" id="t<?= $i ?>" name="items[<?= $i ?>][description]" value="<?= e($it['description'] ?? '') ?>" maxlength="255" placeholder="What was it for?" required>
  </div>
  <div class="field money">
    <label class="sr-only" for="a<?= $i ?>">Amount in pounds</label>
    <span aria-hidden="true">£</span>
    <input class="input" id="a<?= $i ?>" name="items[<?= $i ?>][amount]" value="<?= e($it['amount'] ?? '') ?>" inputmode="decimal" placeholder="0.00" autocomplete="off" required data-amount>
  </div>
  <button type="button" class="icon-btn i-del" aria-label="Remove this expense" data-remove-line><?= icon('trash') ?></button>
  <div class="field i-other">
    <label class="sr-only" for="o<?= $i ?>">What kind of expense</label>
    <input class="input" id="o<?= $i ?>" name="items[<?= $i ?>][category_other]" value="<?= e($it['category_other'] ?? '') ?>" maxlength="80" placeholder="What kind? e.g. Parking">
  </div>
  <div class="i-rc">
    <ul class="rc" data-rc><?php foreach ($lineFiles as $f): $img = in_array($f['mime'], ['image/jpeg', 'image/png', 'image/webp'], true); ?><li>
        <?php if ($img): ?><img class="rc-img" src="<?= url('/files/' . $f['id']) ?>" alt="" width="48" height="56"><?php else: ?><span class="rc-img"><?= strtoupper(pathinfo($f['stored_name'], PATHINFO_EXTENSION)) ?></span><?php endif ?>
        <input type="hidden" name="keep[<?= $i ?>][]" value="<?= (int) $f['id'] ?>">
        <button type="button" class="rc-x" aria-label="Remove <?= e($f['original_name']) ?>" data-rc-remove><?= icon('x', 12) ?></button>
      </li><?php endforeach ?></ul>
    <label class="rc-add">
      <input type="file" name="receipts[<?= $i ?>][]" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" data-rc-input>
      <?= icon('camera') ?><span data-rc-label><?= $lineFiles ? 'Add another' : 'Add receipt' ?></span>
    </label>
  </div>
  <?php if (!empty($err)): ?><p class="err" role="alert"><?= e($err) ?></p><?php endif ?>
</div>
