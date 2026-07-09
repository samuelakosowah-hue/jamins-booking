<?php
/** @var array $config @var string $ref */
?>
<div class="page" style="max-width:640px;text-align:center">
  <p class="eyebrow">Hmm</p>
  <h1 class="page__title">We can't find <em>that booking</em></h1>
  <p class="page__lede" style="margin-inline:auto">
    <?php if ($ref !== ''): ?>
      No appointment matches “<?= e($ref) ?>”. Check the reference on your ticket — it looks like <strong>JNC-4F2A9C</strong>.
    <?php else: ?>
      To leave a review we need the booking reference from your ticket.
    <?php endif ?>
  </p>

  <div class="hero__cta" style="justify-content:center;margin-top:26px">
    <a class="btn btn--maroon" href="/lookup">Find my booking</a>
    <a class="btn btn--ghost" href="/">Back to the price list</a>
  </div>
</div>
