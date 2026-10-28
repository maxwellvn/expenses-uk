<div class="title-row">
  <div><h1>All claims</h1><p>Every claim from every member of staff.</p></div>
  <div class="actions"><a class="btn btn-plain" href="<?= url('/all/export?status=' . $status) ?>"><?= icon('download') ?>Download CSV</a></div>
</div>
<section class="sheet">
  <div class="toolbar">
    <nav class="filter" aria-label="Filter by status">
      <?php foreach ($filters as $k => $label): ?><a href="<?= url('/all?status=' . $k . ($q !== '' ? '&q=' . rawurlencode($q) : '')) ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach ?>
    </nav>
    <form class="search" role="search" method="get" action="<?= url('/all') ?>">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <?= icon('search') ?><label class="sr-only" for="q">Search by name or claim</label>
      <input class="input" id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or claim">
    </form>
  </div>
  <?php if ($claims): ?>
    <ul class="entries"><?php foreach ($claims as $c) { $href = url('/claims/' . $c['id']); $person = true; require __DIR__ . '/../partials/entry.php'; } ?></ul>
  <?php else: ?>
    <div class="empty"><?= icon('search', 32) ?><h2>No claims found</h2><p><?= $q !== '' ? 'Nothing matches “' . e($q) . '”.' : 'Claims will show here once staff send them.' ?></p></div>
  <?php endif ?>
</section>
