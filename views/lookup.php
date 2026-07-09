<?php
/** @var array $config @var ?array $booking @var bool $notFound @var string $ref */
?>
<div class="page">
  <p class="eyebrow">Already registered?</p>
  <h1 class="page__title">Find my <em>booking</em></h1>
  <p class="page__lede">Enter the reference you were given when you booked — it looks like <strong>JNC-4F2A9C</strong>.</p>

  <form class="form" method="get" action="/lookup" style="margin-bottom:34px">
    <div class="grid2" style="align-items:end">
      <div class="field<?= $notFound ? ' field--bad' : '' ?>">
        <label for="ref">Booking reference</label>
        <input id="ref" name="ref" type="text" value="<?= e($ref) ?>" placeholder="JNC-4F2A9C" autocapitalize="characters" required>
      </div>
      <div>
        <button class="btn btn--orange btn--block" type="submit"><?= icon('search') ?> Look it up</button>
      </div>
    </div>
  </form>

  <?php if ($notFound): ?>
    <div class="alert alert--bad">
      No booking matches “<?= e($ref) ?>”. Check the reference, or <a href="/book">book an appointment</a>.
    </div>
  <?php elseif ($booking): ?>
    <?php $heading = 'Booking found'; require __DIR__ . '/_ticket.php'; ?>
  <?php endif ?>
</div>
