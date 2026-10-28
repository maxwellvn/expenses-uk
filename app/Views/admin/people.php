<?php $active = 'people'; require __DIR__ . '/../partials/adminnav.php'; ?>
<div class="title-row"><div><h1>People and roles</h1><p>Everyone active in workCentral UK can sign in and claim. Give extra rights here.</p></div></div>

<div class="legend">
  <div class="sheet"><b>Approver</b><p>Reviews claims: approve, decline or ask for changes.</p></div>
  <div class="sheet"><b>Finance</b><p>Pays approved claims, records the bank reference, sees and exports every claim.</p></div>
  <div class="sheet"><b>Admin</b><p>Everything above, plus roles and help requests. Nobody acts on their own claim.</p></div>
</div>

<section class="sheet">
  <div class="toolbar">
    <span class="muted"><?= count($staff) ?> people</span>
    <form class="search" role="search" method="get" action="<?= url('/admin/people') ?>">
      <?= icon('search') ?><label class="sr-only" for="q">Search people</label>
      <input class="input" id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, title or handle">
    </form>
  </div>
  <table class="people">
    <thead><tr><th>Person</th><th>KingsChat</th><th class="r">Roles</th></tr></thead>
    <tbody>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td><div class="who"><?= avatar($s, 'md') ?><div class="min0"><b><?= e($s['name']) ?></b><small><?= e($s['title']) ?></small></div></div></td>
          <td class="muted"><?= $s['kc_handle'] ? '@' . e($s['kc_handle']) : 'No handle' ?></td>
          <td>
            <form method="post" action="<?= url('/admin/people/' . rawurlencode($s['id'])) ?>" data-autosave>
              <?= csrf_field() ?><input type="hidden" name="q" value="<?= e($q) ?>">
              <?php foreach (Auth::ROLES as $key => $label): $locked = $key === 'admin' && $s['fixed_admin']; $on = in_array($key, $s['roles'], true) || $locked; ?>
                <label class="toggle<?= $locked ? ' locked' : '' ?>" <?= $locked ? 'title="Set in the server settings"' : '' ?>><input type="checkbox" name="roles[]" value="<?= $key ?>" <?= $on ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>><?= e($label) ?></label>
              <?php endforeach ?>
              <button class="btn btn-plain btn-sm" data-save>Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</section>
