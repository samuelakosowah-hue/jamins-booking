<?php
/** @var array $config @var array $booking @var string $heading */
$co = $config['company'];

// Prices the client was quoted, not whatever the price list says today.
$items = booking_line_items($config, $booking);
?>
<div class="ticket">
  <div class="ticket__head">
    <div class="ticket__check"><?= icon('check') ?></div>
    <h2><?= e($heading) ?></h2>
    <p style="margin:6px 0 0;opacity:.9"><?= e($co['motto']) ?></p>
  </div>

  <p class="ticket__by">
    <span class="ticket__by-mark"><?= logo_jamins() ?></span>
    <span>
      <strong><?= e($co['name']) ?></strong>
      <small><?= e($co['tagline']) ?></small>
    </span>
  </p>

  <div class="ticket__ref">
    <small>Your booking reference</small>
    <strong><?= e($booking['reference']) ?></strong>
  </div>

  <dl class="ticket__rows">
    <div class="trow"><dt>Name</dt><dd><?= e($booking['full_name']) ?></dd></div>
    <div class="trow"><dt>Phone</dt><dd><?= e($booking['phone']) ?></dd></div>
    <div class="trow"><dt>Your location</dt><dd><?= e($booking['location']) ?></dd></div>
    <div class="trow"><dt>Appointment</dt><dd><?= e(pretty_date($booking['appointment_date'])) ?></dd></div>
    <div class="trow"><dt>Time</dt><dd><?= e($booking['slot_label']) ?></dd></div>
    <div class="trow"><dt>Seen at</dt><dd><?= e($co['location']) ?></dd></div>
    <div class="trow">
      <dt>Status</dt>
      <dd><span class="pill pill--<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></dd>
    </div>
  </dl>

  <div class="ticket__bill">
    <h3>Your services</h3>
    <ul class="bill">
      <?php foreach ($items as $item): ?>
        <li>
          <span>
            <?= e($item['label']) ?>
            <small class="bill__duration"><?= e($item['duration']) ?></small>
          </span>
          <span class="bill__amount"><?= e(price_range($config, $item['min'], $item['max'])) ?></span>
        </li>
      <?php endforeach ?>
    </ul>

    <p class="bill__total">
      <span>Estimated cost</span>
      <strong><?= e(price_range($config, (float) $booking['total_min'], (float) $booking['total_max'])) ?></strong>
    </p>
    <p class="bill__note">
      This is the estimate you were quoted when you booked. Your exact fee is agreed at your first
      consultation and paid in person — nothing is charged online.
    </p>
  </div>
</div>

<?php [$canReview] = review_eligibility($booking); ?>

<div class="hero__cta noprint" style="justify-content:center;margin-top:28px">
  <?php if ($canReview): ?>
    <a class="btn btn--green" href="/review?ref=<?= e(urlencode($booking['reference'])) ?>">
      <?= star_svg('full') ?> Rate this service
    </a>
  <?php endif ?>
  <button class="btn btn--maroon" onclick="window.print()"><?= icon('print') ?> Print / save as PDF</button>
  <a class="btn btn--ghost" href="/">Back to price list</a>
</div>
