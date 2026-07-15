<?php
/** @var array $config @var array $slots @var array $old @var array $errors @var bool $expired */
$co  = $config['company'];
$val = fn(string $k): string => e((string) ($old[$k] ?? ''));
$checked = fn(string $k): bool => in_array($k, (array) ($old['services'] ?? []), true);
$bad = fn(string $k): string => isset($errors[$k]) ? ' field--bad' : '';

$chosenDate = (string) ($old['appointment_date'] ?? first_bookable_date($config));
if ($chosenDate === '' || !is_bookable_date($chosenDate, $config)) {
    $chosenDate = first_bookable_date($config);
}
?>

<div class="page">
  <p class="eyebrow">Book an appointment</p>
  <h1 class="page__title">Start your <em>nutrition</em> plan</h1>
  <p class="page__lede">
    Consultations run at <strong><?= e($co['location']) ?></strong>, with home and virtual follow-up available.
    Booking is free — you only pay for the service itself, agreed with you at your first consultation.
  </p>

  <?php if ($expired): ?>
    <div class="alert alert--warn">
      You were idle for a while, so the page went stale — but nothing was lost.
      Everything you typed is still below. Just press <strong>Confirm my appointment</strong> again.
    </div>
  <?php elseif ($errors): ?>
    <div class="alert alert--bad">Please fix the highlighted fields below and submit again.</div>
  <?php endif ?>

  <form class="form" method="post" action="/book" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <fieldset class="fieldset">
      <legend class="fieldset__legend"><span class="step">1</span> Your details</legend>

      <div class="grid2">
        <div class="field<?= $bad('full_name') ?>">
          <label for="full_name">Full name</label>
          <input id="full_name" name="full_name" type="text" value="<?= $val('full_name') ?>" placeholder="Ama Mensah" required>
          <?php if (isset($errors['full_name'])): ?><p class="field__error"><?= e($errors['full_name']) ?></p><?php endif ?>
        </div>

        <div class="field<?= $bad('phone') ?>">
          <label for="phone">Phone number</label>
          <input id="phone" name="phone" type="tel" value="<?= $val('phone') ?>" placeholder="024 123 4567" required>
          <?php if (isset($errors['phone'])): ?><p class="field__error"><?= e($errors['phone']) ?></p><?php endif ?>
        </div>
      </div>

      <div class="field<?= $bad('location') ?>" style="margin-top:18px">
        <label for="location">Where do you live?</label>
        <input id="location" name="location" type="text" value="<?= $val('location') ?>"
               placeholder="Town, area or community — e.g. Aputuogya, Ejisu" required>
        <?php if (isset($errors['location'])): ?>
          <p class="field__error"><?= e($errors['location']) ?></p>
        <?php else: ?>
          <p class="field__hint">We use this to arrange home visits and virtual follow-ups, and to plan travel.</p>
        <?php endif ?>
      </div>

      <div class="grid2" style="margin-top:18px">
        <div class="field<?= $bad('email') ?>">
          <label for="email">Email <span style="color:var(--muted);font-weight:500">(optional)</span></label>
          <input id="email" name="email" type="email" value="<?= $val('email') ?>" placeholder="you@example.com">
          <?php if (isset($errors['email'])): ?><p class="field__error"><?= e($errors['email']) ?></p><?php endif ?>
        </div>

        <div class="field<?= $bad('age') ?>">
          <label for="age">Age</label>
          <input id="age" name="age" type="number" min="1" max="120" value="<?= $val('age') ?>" placeholder="34" required>
          <?php if (isset($errors['age'])): ?><p class="field__error"><?= e($errors['age']) ?></p><?php endif ?>
        </div>

        <div class="field<?= $bad('gender') ?>">
          <label for="gender">Gender</label>
          <select id="gender" name="gender" required>
            <option value="">Select…</option>
            <?php foreach (['Female', 'Male', 'Prefer not to say'] as $g): ?>
              <option value="<?= e($g) ?>" <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach ?>
          </select>
          <?php if (isset($errors['gender'])): ?><p class="field__error"><?= e($errors['gender']) ?></p><?php endif ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="fieldset<?= $bad('services') ?>">
      <legend class="fieldset__legend"><span class="step">2</span> Which services?</legend>

      <div class="tiles tiles--service">
        <?php foreach ($config['services'] as $key => $svc): ?>
          <label class="tile">
            <input type="checkbox" name="services[]" value="<?= e($key) ?>"
                   data-min="<?= (int) $svc['min'] ?>" data-max="<?= (int) $svc['max'] ?>"
                   <?= $checked($key) ? 'checked' : '' ?>>
            <span>
              <span class="tile__body">
                <span class="tile__label"><?= e($svc['label']) ?></span>
                <span class="tile__meta">
                  <span class="tile__duration"><?= e($svc['duration']) ?></span>
                  <span class="tag tag--paid"><?= e(price_range($config, (float) $svc['min'], (float) $svc['max'])) ?></span>
                </span>
              </span>
            </span>
          </label>
        <?php endforeach ?>
      </div>
      <?php if (isset($errors['services'])): ?><p class="field__error"><?= e($errors['services']) ?></p><?php endif ?>

      <div class="total total--zero" id="total" data-currency="<?= e($config['currency']) ?>">
        <span class="total__label">Estimated cost</span>
        <span class="total__value" id="total-value">—</span>
      </div>
      <p class="field__hint">
        An estimate only. Nothing is charged online — the final fee is agreed at your consultation.
      </p>
    </fieldset>

    <fieldset class="fieldset">
      <legend class="fieldset__legend"><span class="step">3</span> When suits you?</legend>

      <div class="field<?= $bad('appointment_date') ?>" style="max-width:340px">
        <label for="appointment_date">Preferred date</label>
        <input id="appointment_date" name="appointment_date" type="date"
               value="<?= e($chosenDate) ?>"
               min="<?= e(first_bookable_date($config)) ?>" max="<?= e(last_bookable_date($config)) ?>"
               data-closed-weekdays="<?= e(implode(',', array_map('strval', $config['closed_weekdays'] ?? []))) ?>"
               data-closed-dates="<?= e(implode(',', $config['closed_dates'] ?? [])) ?>"
               required>
        <?php if (isset($errors['appointment_date'])): ?><p class="field__error"><?= e($errors['appointment_date']) ?></p><?php endif ?>
        <p class="field__hint">Sundays and listed closed days are not bookable.</p>
      </div>

      <div class="slots<?= $bad('slot_id') ?>" id="slots" style="margin-top:20px">
        <?php foreach ($slots as $slot): $full = (int) $slot['remaining'] < 1; ?>
          <label class="slot<?= $full ? ' slot--full' : '' ?>" data-slot="<?= (int) $slot['id'] ?>">
            <input type="radio" name="slot_id" value="<?= (int) $slot['id'] ?>"
                   <?= $full ? 'disabled' : '' ?>
                   <?= (string) ($old['slot_id'] ?? '') === (string) $slot['id'] ? 'checked' : '' ?>>
            <span>
              <span class="slot__time"><?= e($slot['label']) ?></span>
              <span class="slot__left">
                <?= $full ? 'Fully booked' : $slot['remaining'] . ' of ' . $slot['capacity'] . ' left' ?>
              </span>
            </span>
          </label>
        <?php endforeach ?>
      </div>
      <?php if (isset($errors['slot_id'])): ?><p class="field__error"><?= e($errors['slot_id']) ?></p><?php endif ?>
    </fieldset>

    <fieldset class="fieldset">
      <legend class="fieldset__legend"><span class="step">4</span> Anything we should know?</legend>
      <div class="field">
        <label for="notes">Conditions, medication, allergies or goals <span style="color:var(--muted);font-weight:500">(optional)</span></label>
        <textarea id="notes" name="notes" placeholder="e.g. I am hypertensive and on medication, and I would like help with my weight."><?= $val('notes') ?></textarea>
      </div>
    </fieldset>

    <div>
      <button class="btn btn--orange btn--block" type="submit"><?= icon('check') ?> Confirm my appointment</button>
      <p class="field__hint" style="text-align:center;margin-top:14px">
        You'll get a booking reference on the next screen. Quote it when you call
        <?= e($co['phones'][0]) ?>.
      </p>
    </div>
  </form>
</div>

<script>
(function () {
  var currency = document.getElementById('total').dataset.currency;

  // ---- Estimated cost. The server re-prices from config on submit; this is display only.
  var box    = document.getElementById('total');
  var output = document.getElementById('total-value');
  var boxes  = document.querySelectorAll('input[name="services[]"]');

  function updateTotal() {
    var min = 0, max = 0, any = false;
    boxes.forEach(function (b) {
      if (b.checked) { min += +b.dataset.min; max += +b.dataset.max; any = true; }
    });
    output.textContent = any
      ? (min === max ? currency + ' ' + min : currency + ' ' + min + ' – ' + max)
      : '—';
    box.classList.toggle('total--zero', !any);
  }

  boxes.forEach(function (b) { b.addEventListener('change', updateTotal); });
  updateTotal();

  // ---- Availability depends on the chosen day, so refresh the times when it changes.
  var dateInput = document.getElementById('appointment_date');
  var slotsBox  = document.getElementById('slots');
  var closedWeekdays = (dateInput.dataset.closedWeekdays || '').split(',').filter(Boolean).map(Number);
  var closedDates = (dateInput.dataset.closedDates || '').split(',').filter(Boolean);

  function isClosedDate(date) {
    if (!date) return true;
    if (closedDates.indexOf(date) !== -1) return true;
    var parts = date.split('-');
    if (parts.length !== 3) return true;
    var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
    return closedWeekdays.indexOf(d.getDay()) !== -1;
  }

  function refreshSlots() {
    var date = dateInput.value;
    if (!date) return;

    if (isClosedDate(date)) {
      slotsBox.querySelectorAll('[data-slot]').forEach(function (label) {
        var input = label.querySelector('input');
        var left  = label.querySelector('.slot__left');
        label.classList.add('slot--full');
        input.disabled = true;
        input.checked = false;
        left.textContent = 'Closed that day';
      });
      return;
    }

    fetch('/availability?date=' + encodeURIComponent(date))
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (data) {
        data.slots.forEach(function (slot) {
          var label = slotsBox.querySelector('[data-slot="' + slot.id + '"]');
          if (!label) return;
          var input = label.querySelector('input');
          var left  = label.querySelector('.slot__left');
          var full  = slot.remaining < 1;

          label.classList.toggle('slot--full', full);
          input.disabled = full;
          if (full && input.checked) input.checked = false;
          left.textContent = full ? 'Fully booked' : slot.remaining + ' of ' + slot.capacity + ' left';
        });
      })
      .catch(function () { /* leave the server-rendered availability in place */ });
  }

  dateInput.addEventListener('change', refreshSlots);
  if (isClosedDate(dateInput.value)) refreshSlots();
})();
</script>
