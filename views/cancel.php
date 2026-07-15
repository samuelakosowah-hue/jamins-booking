<?php
/** @var array $config @var ?array $booking @var string $ref @var array $old @var array $errors @var bool $expired */
$val = fn(string $k): string => e((string) ($old[$k] ?? ''));
$bad = fn(string $k): string => isset($errors[$k]) ? ' field--bad' : '';
?>
<div class="page" style="max-width:640px">
  <p class="eyebrow">Change of plans?</p>
  <h1 class="page__title">Cancel an <em>appointment</em></h1>
  <p class="page__lede">
    Enter your booking reference and the phone number you used when you booked.
    We text you a confirmation once it is cancelled.
  </p>

  <?php if ($expired): ?>
    <div class="alert alert--warn">Your session expired — please submit again.</div>
  <?php elseif ($errors): ?>
    <div class="alert alert--bad">Please fix the highlighted fields below.</div>
  <?php endif ?>

  <?php if ($booking && empty($errors)): ?>
    <div class="card" style="margin-bottom:24px">
      <p style="margin:0 0 8px"><strong><?= e($booking['full_name']) ?></strong></p>
      <p style="margin:0;color:var(--muted)">
        <?= e(pretty_date($booking['appointment_date'])) ?> · <?= e($booking['slot_label']) ?>
        · <span class="pill pill--<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span>
      </p>
    </div>
  <?php endif ?>

  <form class="form" method="post" action="/cancel">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="field<?= $bad('ref') ?>">
      <label for="ref">Booking reference</label>
      <input id="ref" name="ref" type="text" required autocapitalize="characters"
             placeholder="JNC-A1B2C3D4E5F6G7H8"
             value="<?= $val('ref') !== '' ? $val('ref') : e($ref) ?>">
      <?php if (isset($errors['ref'])): ?><p class="field__error"><?= e($errors['ref']) ?></p><?php endif ?>
    </div>

    <div class="field<?= $bad('phone') ?>" style="margin-top:18px">
      <label for="phone">Phone number on the booking</label>
      <input id="phone" name="phone" type="tel" required placeholder="024 123 4567"
             value="<?= $val('phone') ?>">
      <?php if (isset($errors['phone'])): ?>
        <p class="field__error"><?= e($errors['phone']) ?></p>
      <?php else: ?>
        <p class="field__hint">Must match the number you gave when you booked.</p>
      <?php endif ?>
    </div>

    <div style="margin-top:28px">
      <button class="btn btn--maroon btn--block" type="submit"
              onclick="return confirm('Cancel this appointment permanently?');">
        <?= icon('x') ?> Cancel my appointment
      </button>
      <p class="field__hint" style="text-align:center;margin-top:14px">
        Prefer to reschedule? Call <?= e($config['company']['phones'][0]) ?> instead.
      </p>
    </div>
  </form>
</div>
