<?php
/** @var array $config @var array $booking @var array $review */
?>
<div class="page" style="max-width:640px;text-align:center">
  <div class="thanks">
    <div class="thanks__stars"><?= stars_html((float) $review['rating']) ?></div>
    <h1 class="page__title" style="margin-top:10px">Thank you!</h1>
    <p class="page__lede" style="margin-inline:auto">
      Your <?= (int) $review['rating'] ?>-star review of <strong><?= e($booking['reference']) ?></strong> is saved.
      <?php if (trim((string) $review['comment']) !== ''): ?>
        We may show your comment on the price list to help other clients choose.
      <?php endif ?>
    </p>

    <?php if (trim((string) $review['comment']) !== ''): ?>
      <blockquote class="quote quote--solo">“<?= e($review['comment']) ?>”</blockquote>
    <?php endif ?>

    <div class="hero__cta" style="justify-content:center;margin-top:26px">
      <a class="btn btn--maroon" href="/">Back to the price list</a>
      <a class="btn btn--ghost" href="/book">Book again</a>
    </div>
  </div>
</div>
