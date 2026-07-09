<?php
/** @var array $config @var array $booking @var string $reason */
?>
<div class="page" style="max-width:640px;text-align:center">
  <p class="eyebrow">Not just yet</p>
  <h1 class="page__title">Review <em>pending</em></h1>
  <p class="page__lede" style="margin-inline:auto"><?= e($reason) ?></p>

  <div class="hero__cta" style="justify-content:center;margin-top:26px">
    <a class="btn btn--maroon" href="/lookup?ref=<?= e(urlencode($booking['reference'])) ?>">See my booking</a>
    <a class="btn btn--ghost" href="/">Back to the price list</a>
  </div>
</div>
