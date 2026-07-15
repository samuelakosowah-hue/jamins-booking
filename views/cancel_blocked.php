<?php
/** @var array $config @var array $booking @var string $reason */
?>
<div class="page" style="max-width:640px">
  <p class="eyebrow">Cannot cancel online</p>
  <h1 class="page__title">This booking <em>cannot</em> be cancelled here</h1>
  <p class="page__lede"><?= e($reason) ?></p>

  <?php if (($booking['status'] ?? '') === 'cancelled'): ?>
    <div class="alert alert--warn">
      Reference <strong><?= e($booking['reference']) ?></strong> is already cancelled.
    </div>
  <?php endif ?>

  <div class="hero__cta" style="justify-content:center;margin-top:28px">
    <a class="btn btn--orange" href="/lookup?ref=<?= e(urlencode($booking['reference'])) ?>">View booking</a>
    <a class="btn btn--ghost" href="tel:<?= e($config['company']['phones'][0]) ?>">
      Call <?= e($config['company']['phones'][0]) ?>
    </a>
  </div>
</div>
