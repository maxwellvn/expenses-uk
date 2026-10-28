<?php
$errors = $_SESSION['errors'] ?? [];
$t = old('topic', $topic);
$public = !$user;
?>
<?php if ($public): ?><div class="single"><section class="sheet wide"><div class="mark"><img src="<?= asset('img/ror-logo.webp') ?>" alt="" width="26" height="26"><b>Expense claims</b><span>RoR UK office</span></div><?php else: ?><div class="help-wrap"><?php endif ?>

<?php if ($sent): ?>
  <div class="<?= $public ? '' : 'sheet ' ?>sent">
    <?= icon('check', 32) ?>
    <h2>Message received</h2>
    <p>The admin reads every message and will reply on KingsChat.</p>
    <a class="btn btn-plain mt-20" href="<?= url($public ? '/login' : '/') ?>"><?= $public ? 'Back to sign in' : 'Back to my claims' ?></a>
  </div>
<?php else: ?>
  <?php if (!$public): ?><div class="title-row"><div><h1>Help and feedback</h1><p>Something not working, or an idea to make claiming easier? The admin reads every message.</p></div></div><?php else: ?><h1>Help and feedback</h1><p>Tell us what is wrong. The admin reads every message and replies on KingsChat.</p><?php endif ?>

  <form class="<?= $public ? 'mt-20' : 'sheet pad' ?>" method="post" action="<?= url('/help') ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="page" value="<?= e($_SERVER['HTTP_REFERER'] ?? '') ?>">
    <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

    <fieldset class="field">
      <legend class="label">What is it about?</legend>
      <div class="radios mt-12">
        <?php foreach (Feedback::TOPICS as $k => $label): ?>
          <label><input type="radio" name="topic" value="<?= e($k) ?>" <?= $t === $k ? 'checked' : '' ?>><?= e($label) ?></label>
        <?php endforeach ?>
      </div>
      <?php if (isset($errors['topic'])): ?><p class="err"><?= e($errors['topic']) ?></p><?php endif ?>
    </fieldset>

    <?php if ($public): ?>
      <div class="field">
        <label for="name">Your name</label>
        <input class="input" id="name" name="name" maxlength="160" autocomplete="name" value="<?= e(old('name')) ?>" <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif ?>
      </div>
      <div class="field">
        <label for="kc_handle">KingsChat handle</label>
        <input class="input" id="kc_handle" name="kc_handle" maxlength="80" autocapitalize="none" placeholder="@yourhandle" value="<?= e(old('kc_handle')) ?>" <?= isset($errors['kc_handle']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['kc_handle'])): ?><p class="err"><?= e($errors['kc_handle']) ?></p><?php endif ?>
      </div>
    <?php endif ?>

    <div class="field">
      <label for="message">Message</label>
      <textarea class="input" id="message" name="message" maxlength="3000" rows="5" placeholder="<?= $public ? 'What happens when you try to sign in?' : 'What happened, and on which claim?' ?>" <?= isset($errors['message']) ? 'aria-invalid="true"' : '' ?>><?= e(old('message')) ?></textarea>
      <?php if (isset($errors['message'])): ?><p class="err"><?= e($errors['message']) ?></p><?php endif ?>
    </div>

    <button class="btn btn-primary btn-lg mt-20">Send message</button>
  </form>
  <?php if ($public): ?><div class="foot"><a href="<?= url('/login') ?>">Back to sign in</a></div><?php endif ?>
<?php endif ?>

<?php if ($public): ?></section></div><?php else: ?></div><?php endif ?>
