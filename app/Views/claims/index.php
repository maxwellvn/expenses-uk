<?php
$by = $stats['by'];
$needs = array_values(array_filter($claims, fn($c) => $c['status'] === 'changes_requested'));
$months = [];
foreach ($claims as $c) $months[date('F Y', strtotime($c['submitted_at']))][] = $c;
?>
<div class="title-row">
  <div><h1>My claims</h1><p>Money you paid for the office, and where each claim is.</p></div>
</div>

<?php if ($claims): ?>
  <dl class="sheet position">
    <div><dt>In review</dt><dd class="num"><?= money($by['pending']['t']) ?><small><?= $by['pending']['n'] ?> claim<?= $by['pending']['n'] === 1 ? '' : 's' ?></small></dd></div>
    <div><dt>Approved, awaiting payment</dt><dd class="num"><?= money($by['approved']['t']) ?></dd></div>
    <div><dt>Paid to you in <?= date('Y') ?></dt><dd class="num"><?= money($stats['paidYear']) ?></dd></div>
  </dl>

  <?php foreach ($needs as $c): ?>
    <div class="callout callout-ask">
      <?= icon('pen', 18) ?>
      <div><strong>Changes needed on “<?= e($c['title']) ?>”</strong><span><?= claim_ref((int) $c['id']) ?>, <?= money((int) $c['total_pence']) ?>. Update it and it goes back into review.</span></div>
      <a class="btn btn-plain" href="<?= url('/claims/' . $c['id'] . '/edit') ?>">Update claim</a>
    </div>
  <?php endforeach ?>

  <section class="sheet" aria-label="Claims by month">
    <?php foreach ($months as $label => $rows): ?>
      <div class="book-month"><span><?= e($label) ?></span><span class="num"><?= money(array_sum(array_map('intval', array_column($rows, 'total_pence')))) ?></span></div>
      <ul class="entries">
        <?php foreach ($rows as $c) { $href = url('/claims/' . $c['id']); $person = false; require __DIR__ . '/../partials/entry.php'; } ?>
      </ul>
    <?php endforeach ?>
  </section>
<?php else: ?>
  <section class="sheet empty">
    <?= icon('receipt', 32) ?>
    <h2>No claims yet</h2>
    <p>Paid for something for the office yourself? Photograph the receipt, add what it was for, and send it for review.</p>
    <a class="btn btn-primary" href="<?= url('/claims/new') ?>"><?= icon('plus') ?>New claim</a>
  </section>
<?php endif ?>
