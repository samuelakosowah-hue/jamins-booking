<?php
/** @var array $config @var ?string $error */
?>
<div class="page" style="max-width:520px">
  <p class="eyebrow"><?= e($config['company']['short']) ?> team only</p>
  <h1 class="page__title">Admin <em>login</em></h1>
  <p class="page__lede">Sign in to view registrations, check people in on the day, and export the attendance list.</p>

  <?php if ($error): ?>
    <div class="alert alert--bad"><?= e($error) ?></div>
  <?php endif ?>

  <form class="form" method="post" action="/admin/login">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autofocus>
    </div>
    <button class="btn btn--maroon btn--block" type="submit"><?= icon('lock') ?> Sign in</button>
  </form>
</div>
