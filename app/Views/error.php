<div class="error-page">
  <b class="num"><?= (int) $code ?></b>
  <h1><?= $code === 404 ? 'Nothing here' : ($code === 403 ? 'Not allowed' : 'Something went wrong') ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn btn-plain" href="<?= url('/') ?>">Back to my claims</a>
</div>
