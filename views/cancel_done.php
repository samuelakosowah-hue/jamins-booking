<?php
/** @var array $config @var array $booking */
$co = $config['company'];
?>
<div class="page" style="max-width:640px">
  <p class="eyebrow">Done</p>
  <h1 class="page__title">Appointment <em>cancelled</em></h1>
  <p class="page__lede">
    <strong><?= e($booking['reference']) ?></strong> on
    <?= e(pretty_date($booking['appointment_date'])) ?> has been cancelled.
    A confirmation text is on its way to the number on the booking.
  </p>

  <div class="alert alert--good">
    Your slot is free again. If you still need care, you can
    <a href="/book">book a new appointment</a> any time, or call
    <a href="tel:<?= e($co['phones'][0]) ?>"><?= e($co['phones'][0]) ?></a>.
  </div>

  <div class="hero__cta" style="justify-content:center;margin-top:28px">
    <a class="btn btn--orange" href="/book">Book again</a>
    <a class="btn btn--ghost" href="/">Back to price list</a>
  </div>
</div>
