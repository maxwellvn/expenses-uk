<nav class="subnav" aria-label="Admin">
  <a href="<?= url('/admin/people') ?>" <?= ($active ?? '') === 'people' ? 'aria-current="page"' : '' ?>>People and roles</a>
  <a href="<?= url('/admin/feedback') ?>" <?= ($active ?? '') === 'feedback' ? 'aria-current="page"' : '' ?>>Help requests<?php $nf = Feedback::countNew(); if ($nf): ?> <span class="count num"><?= $nf ?></span><?php endif ?></a>
</nav>
