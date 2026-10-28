<?php $active = 'feedback'; require __DIR__ . '/../partials/adminnav.php'; ?>
<div class="title-row"><div><h1>Help requests</h1><p>Messages from the help form, including people who cannot sign in. Reply on KingsChat, then mark them dealt with.</p></div></div>
<section class="sheet">
  <div class="toolbar">
    <nav class="filter" aria-label="Filter">
      <a href="<?= url('/admin/feedback') ?>" <?= $tab === 'new' ? 'aria-current="page"' : '' ?>>Open <span class="num"><?= $newCount ?></span></a>
      <a href="<?= url('/admin/feedback?status=resolved') ?>" <?= $tab === 'resolved' ? 'aria-current="page"' : '' ?>>Dealt with</a>
    </nav>
  </div>
  <?php if (!$items): ?>
    <div class="empty"><?= icon('inbox', 32) ?><h2><?= $tab === 'new' ? 'No open messages' : 'Nothing here yet' ?></h2><p>Messages sent through the help form appear here.</p></div>
  <?php else: ?>
    <ul class="notes">
      <?php foreach ($items as $f): ?>
        <li>
          <div class="from">
            <b><?= e($f['name']) ?></b>
            <?php if ($f['kc_handle']): ?><a href="https://kingschat.online/user/<?= rawurlencode($f['kc_handle']) ?>" target="_blank" rel="noopener noreferrer">@<?= e($f['kc_handle']) ?></a><?php endif ?>
            <span class="role"><?= e(Feedback::TOPICS[$f['topic']] ?? $f['topic']) ?></span>
            <span><?= $f['staff_id'] ? 'Signed in' : 'Not signed in' ?></span>
            <time datetime="<?= e(date('c', strtotime($f['created_at']))) ?>"><?= e(when($f['created_at'])) ?></time>
            <?php if ($tab === 'resolved' && $f['resolved_by']): ?><span>Dealt with by <?= e($people[$f['resolved_by']]['name'] ?? 'admin') ?></span><?php endif ?>
          </div>
          <p class="msg"><?= e($f['message']) ?></p>
          <form method="post" action="<?= url('/admin/feedback/' . $f['id']) ?>">
            <?= csrf_field() ?><input type="hidden" name="done" value="<?= $tab === 'new' ? '1' : '0' ?>">
            <button class="btn btn-plain btn-sm"><?= $tab === 'new' ? icon('check') . 'Mark dealt with' : 'Reopen' ?></button>
          </form>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>
