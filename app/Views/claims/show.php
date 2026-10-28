<a class="back" href="<?= url($mine ? '/' : (Auth::can('approve', $user) && $claim['status'] === 'pending' ? '/review?claim=' . $claim['id'] : (Auth::can('pay', $user) && $claim['status'] === 'approved' ? '/payments' : '/all'))) ?>"><?= icon('back') ?><?= $mine ? 'My claims' : 'Back' ?></a>
<?php require __DIR__ . '/_doc.php' ?>
