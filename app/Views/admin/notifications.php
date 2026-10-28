<?php
$active = 'notifications'; require __DIR__ . '/../partials/adminnav.php';
$connected = !empty($agent['refresh_token']);
$labels = ['pending' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Not deliverable'];
?>
<div class="title-row"><div><h1>KingsChat notifications</h1><p>Staff get a KingsChat message when a claim is sent, decided, paid or messaged. Messages come from one sender account.</p></div></div>

<section class="sheet pad notify-status">
  <?php if ($connected): ?>
    <div class="ns-line"><?= status_chip('paid') ?><div class="min0"><b>Sending from @<?= e($agent['handle']) ?></b><span class="muted">Connected <?= e(when($agent['connected_at'])) ?><?= $connectedBy ? ' by ' . e($connectedBy['name']) : '' ?></span></div></div>
    <div class="ns-actions">
      <form method="post" action="<?= url('/admin/notifications/test') ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('check') ?>Send me a test message</button></form>
      <form method="post" action="<?= url('/admin/notifications/connect') ?>"><?= csrf_field() ?><button class="btn btn-plain">Change sender account</button></form>
      <form method="post" action="<?= url('/admin/notifications/disconnect') ?>"><?= csrf_field() ?><button class="btn btn-decline">Disconnect</button></form>
    </div>
  <?php else: ?>
    <div class="ns-line"><?= status_chip('changes_requested') ?><div class="min0"><b>No sender account connected</b><span class="muted">Messages are queued and go out as soon as one is connected.</span></div></div>
    <ol class="ns-steps">
      <li>Sign out of KingsChat on this browser, or use a private window.</li>
      <li>Click Connect, then sign in to KingsChat as the account messages should come from, for example <b>@gpd-tech</b>.</li>
      <li>You come back here, still signed in as yourself.</li>
    </ol>
    <form method="post" action="<?= url('/admin/notifications/connect') ?>"><?= csrf_field() ?><button class="btn btn-primary btn-lg">Connect KingsChat sender</button></form>
  <?php endif ?>
</section>

<section class="sheet mt-20">
  <div class="toolbar">
    <span class="muted"><?php foreach ($labels as $k => $l): ?><?= e($l) ?> <b class="num"><?= (int) ($counts[$k] ?? 0) ?></b>&nbsp;&nbsp;&nbsp;<?php endforeach ?></span>
    <?php if (($counts['failed'] ?? 0) > 0): ?><form method="post" action="<?= url('/admin/notifications/retry') ?>"><?= csrf_field() ?><button class="btn btn-plain btn-sm">Retry failed</button></form><?php endif ?>
  </div>
  <?php if (!$recent): ?>
    <div class="empty"><?= icon('inbox', 32) ?><h2>No messages yet</h2><p>They appear here as claims are sent and answered.</p></div>
  <?php else: ?>
    <ul class="notes">
      <?php foreach ($recent as $n): ?>
        <li>
          <div class="from"><b><?= e($n['name'] ?? $n['kc_handle']) ?></b><span>@<?= e($n['kc_handle']) ?></span><span class="role"><?= e($labels[$n['status']]) ?></span><time><?= e(when($n['sent_at'] ?? $n['created_at'])) ?></time><?php if ($n['last_error']): ?><span class="err"><?= e($n['last_error']) ?></span><?php endif ?></div>
          <p class="msg"><?= e($n['body']) ?></p>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>
