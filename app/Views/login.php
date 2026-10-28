<div class="single">
  <section class="sheet">
    <div class="mark"><img src="<?= asset('img/ror-logo.webp') ?>" alt="" width="26" height="26"><b>Expense claims</b><span>RoR UK office</span></div>
    <h1>Sign in</h1>
    <p>Use the KingsChat account linked to your workCentral UK profile.</p>

    <?php if ($error): ?>
      <div class="alert" role="alert">
        <p><?= e($error) ?></p>
        <?php if ($handle): ?><p>KingsChat handle: @<?= e($handle) ?></p><?php endif ?>
      </div>
    <?php endif ?>

    <a class="btn btn-primary btn-block btn-lg kc" href="<?= url('/auth/kingschat') ?>">
      <img src="<?= asset('img/kingschat-logo.png') ?>" alt="" width="20" height="20">
      Continue with KingsChat
    </a>

    <div class="foot">
      <a href="<?= url('/help?topic=access') ?>">Can't sign in? Tell us</a>
    </div>
  </section>
</div>
