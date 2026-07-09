<?php
/** @var array $config @var array $booking */
?>
<div class="page">
  <div class="alert alert--good">
    Booking received — we've sent your booking ID, date, time and services by SMS to
    <strong><?= e($booking['phone']) ?></strong>. Our team will confirm shortly, and you'll
    get another text when they do.
  </div>

  <?php $heading = "You're booked in!"; require __DIR__ . '/_ticket.php'; ?>
</div>
