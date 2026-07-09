<?php
/** @var array $config @var array $booking @var array $errors @var array $old @var bool $expired */
$co    = $config['company'];
$keys  = explode(',', $booking['services']);
$rated = (int) ($old['rating'] ?? 0);
?>

<div class="page" style="max-width:720px">
  <p class="eyebrow">Your visit</p>
  <h1 class="page__title">How did we <em>do</em>?</h1>
  <p class="page__lede">
    You saw us on <strong><?= e(pretty_date($booking['appointment_date'])) ?></strong>.
    Your rating helps other clients choose the right service — it takes ten seconds.
  </p>

  <?php if ($expired): ?>
    <div class="alert alert--warn">
      The page went stale while it sat open — nothing was lost. Just press
      <strong>Submit my review</strong> again.
    </div>
  <?php elseif ($errors): ?>
    <div class="alert alert--bad">Please fix the highlighted fields below.</div>
  <?php endif ?>

  <div class="card" style="margin-bottom:26px">
    <p class="reviewfor__label">You are reviewing</p>
    <p class="reviewfor__ref"><?= e($booking['reference']) ?></p>
    <ul class="reviewfor__services">
      <?php foreach ($keys as $k): ?>
        <li><span class="tick"><?= icon('check') ?></span> <?= e(service_label($config, $k)) ?></li>
      <?php endforeach ?>
    </ul>
  </div>

  <form class="form" method="post" action="/review">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="ref" value="<?= e($booking['reference']) ?>">

    <fieldset class="fieldset<?= isset($errors['rating']) ? ' field--bad' : '' ?>">
      <legend class="fieldset__legend"><span class="step">1</span> Your rating</legend>

      <!-- Rendered right-to-left so plain CSS can fill every star up to the hovered one. -->
      <div class="rating">
        <?php foreach ([5, 4, 3, 2, 1] as $value): ?>
          <input type="radio" id="star<?= $value ?>" name="rating" value="<?= $value ?>"
                 <?= $rated === $value ? 'checked' : '' ?> required>
          <label for="star<?= $value ?>" title="<?= $value ?> star<?= $value === 1 ? '' : 's' ?>">
            <?= star_svg('full') ?>
            <span class="visually-hidden"><?= $value ?> stars</span>
          </label>
        <?php endforeach ?>
      </div>
      <?php if (isset($errors['rating'])): ?><p class="field__error"><?= e($errors['rating']) ?></p><?php endif ?>
    </fieldset>

    <fieldset class="fieldset<?= isset($errors['comment']) ? ' field--bad' : '' ?>">
      <legend class="fieldset__legend"><span class="step">2</span> Tell us more <span style="text-transform:none;font-family:var(--body);font-size:13px;color:var(--muted);font-weight:600">(optional)</span></legend>
      <div class="field">
        <label for="comment">What went well, and what could be better?</label>
        <textarea id="comment" name="comment" maxlength="1000"
                  placeholder="e.g. The diet plan was easy to follow and my blood pressure has come down."><?= e((string) ($old['comment'] ?? '')) ?></textarea>
        <?php if (isset($errors['comment'])): ?>
          <p class="field__error"><?= e($errors['comment']) ?></p>
        <?php else: ?>
          <p class="field__hint">
            If you leave a comment it may appear on our website, shown as
            “<?= e(reviewer_name($booking)) ?>”. Your phone number is never published.
          </p>
        <?php endif ?>
      </div>
    </fieldset>

    <button class="btn btn--orange btn--block" type="submit"><?= icon('check') ?> Submit my review</button>
  </form>
</div>
