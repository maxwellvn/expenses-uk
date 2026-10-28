<?php
$u = Auth::user();
$path = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$stats = Auth::can('view_all', $u) ? Claim::stats()['by'] : null;
$sections = [['/', 'My claims', null, 'receipt']];
if (Auth::can('approve', $u)) $sections[] = ['/review', 'Review', $stats['pending']['n'], 'check'];
if (Auth::can('pay', $u)) $sections[] = ['/payments', 'Payments', $stats['approved']['n'], 'pound'];
if (Auth::can('view_all', $u)) $sections[] = ['/all', 'All claims', null, 'list'];
if (Auth::can('admin', $u)) $sections[] = ['/admin', 'Admin', Feedback::countNew(), 'users'];
$isCurrent = fn(string $href) => $path === $href || ($href !== '/' && str_starts_with($path, $href));
$f = flash();
?>
<!doctype html>
<html lang="en-GB">
<head><?php require __DIR__ . '/partials/head.php' ?></head>
<body>
  <header class="mast">
    <div class="mast-in">
      <a class="mark" href="<?= url('/') ?>"><img src="<?= asset('img/ror-logo.webp') ?>" alt="" width="26" height="26"><b>Expense claims</b><span>RoR UK office</span></a>
      <nav class="sections" aria-label="Sections">
        <?php foreach ($sections as [$href, $label, $n]): ?>
          <a href="<?= url($href === '/admin' ? '/admin/people' : $href) ?>" <?= $isCurrent($href) ? 'aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n): ?><span class="count num" aria-label="<?= (int) $n ?> waiting"><?= (int) $n ?></span><?php endif ?></a>
        <?php endforeach ?>
      </nav>
      <div class="mast-end">
        <a class="btn btn-primary btn-sm btn-new" href="<?= url('/claims/new') ?>" aria-label="New claim"><?= icon('plus') ?><span>New claim</span></a>
        <a class="icon-btn" href="<?= url('/help') ?>" title="Help and feedback" aria-label="Help and feedback"><?= icon('help', 18) ?></a>
        <span class="me"><?= avatar($u, 'sm') ?><span class="me-name"><?= e(first_name($u['name'])) ?></span></span>
        <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button class="icon-btn" title="Sign out" aria-label="Sign out"><?= icon('logout', 18) ?></button></form>
      </div>
    </div>
  </header>
  <main class="page<?= count($sections) > 1 ? ' has-tabbar' : '' ?>" id="main"><?= $content ?></main>
  <?php if (count($sections) > 1): ?>
    <nav class="tabbar" aria-label="Sections">
      <?php foreach ($sections as [$href, $label, $n, $ico]): ?>
        <a href="<?= url($href === '/admin' ? '/admin/people' : $href) ?>" <?= $isCurrent($href) ? 'aria-current="page"' : '' ?>><span class="tb-ico"><?= icon($ico, 22) ?><?php if ($n): ?><span class="count num"><?= (int) $n ?></span><?php endif ?></span><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
  <?php endif ?>
  <?php if ($f): ?>
    <div class="toast<?= $f[1] === 'error' ? ' is-error' : '' ?>" role="status" data-toast><?= icon($f[1] === 'error' ? 'alert' : 'check') ?><span><?= e($f[0]) ?></span></div>
  <?php endif ?>
</body>
</html>
