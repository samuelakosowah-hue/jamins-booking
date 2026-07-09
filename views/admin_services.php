<?php
/** @var array $config @var array $services @var array $usage @var array $old @var array $errors @var int $editing @var string $flash */
$cur = $config['currency'];

// When a save fails validation we re-show the offending values; otherwise the row's own.
$value = function (string $field, array $service = []) use ($old, $editing) {
    $isTarget = ($editing && ($old['id'] ?? '') == ($service['id'] ?? -1))
             || (!$editing && !$service && $old);
    return e((string) ($isTarget ? ($old[$field] ?? '') : ($service[$field] ?? '')));
};

$messages = [
    'added'     => 'Service added — it is live on the price list now.',
    'updated'   => 'Service updated.',
    'retired'   => 'Service retired. It is off the price list, and past bookings still show it.',
    'activated' => 'Service is back on the price list.',
    'deleted'   => 'Service deleted permanently.',
];

$iconFor = fn(array $s): string => $s['icon'] ?? 'consult';
?>

<div class="page" style="max-width:1100px">
  <p class="eyebrow">Price list</p>
  <h1 class="page__title">Manage <em>services</em></h1>
  <p class="page__lede">
    Everything here drives the public price list and the booking form. Prices are bands —
    clients see “<?= e($cur) ?> 150 – 250”, never a single number.
  </p>

  <div class="admin__bar">
    <a class="btn btn--ghost btn--sm" href="/admin">&larr; Appointments</a>
    <span class="spacer"></span>
    <a class="btn btn--ghost btn--sm" href="/" target="_blank">View price list</a>
  </div>

  <?php if ($flash && isset($messages[$flash])): ?>
    <div class="alert alert--good"><?= e($messages[$flash]) ?></div>
  <?php endif ?>
  <?php if ($errors): ?>
    <div class="alert alert--bad">Please fix the highlighted fields below.</div>
  <?php endif ?>

  <!-- ------------------------------------------------------------ add / edit -->
  <?php
  $editRow = null;
  foreach ($services as $s) {
      if ($s['id'] === $editing) {
          $editRow = $s;
      }
  }
  ?>

  <div class="card" style="margin-bottom:34px">
    <h3 class="fieldset__legend" style="margin-bottom:18px">
      <span class="step"><?= $editRow ? icon('dietplan') : '+' ?></span>
      <?= $editRow ? 'Edit “' . e($editRow['label']) . '”' : 'Add a service' ?>
    </h3>

    <form class="form" method="post" action="/admin/services">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>"><?php endif ?>

      <div class="grid2">
        <div class="field<?= isset($errors['label']) ? ' field--bad' : '' ?>">
          <label for="label">Service name</label>
          <input id="label" name="label" type="text" maxlength="80" required
                 placeholder="Weight Management Program"
                 value="<?= $editRow ? $value('label', $editRow) : $value('label') ?>">
          <?php if (isset($errors['label'])): ?><p class="field__error"><?= e($errors['label']) ?></p><?php endif ?>
        </div>

        <div class="field<?= isset($errors['duration']) ? ' field--bad' : '' ?>">
          <label for="duration">Duration</label>
          <input id="duration" name="duration" type="text" list="durations" required
                 placeholder="1 month"
                 value="<?= $editRow ? $value('duration', $editRow) : $value('duration') ?>">
          <datalist id="durations">
            <option value="1 month"><option value="One session"><option value="Per session"><option value="Per client">
          </datalist>
          <?php if (isset($errors['duration'])): ?><p class="field__error"><?= e($errors['duration']) ?></p><?php endif ?>
        </div>
      </div>

      <div class="grid2" style="margin-top:18px">
        <div class="field<?= isset($errors['min']) ? ' field--bad' : '' ?>">
          <label for="min">Lowest price (<?= e($cur) ?>)</label>
          <input id="min" name="min" type="number" min="0" step="1" required placeholder="150"
                 value="<?= $editRow ? e((string) (int) $editRow['min']) : $value('min') ?>">
          <?php if (isset($errors['min'])): ?><p class="field__error"><?= e($errors['min']) ?></p><?php endif ?>
        </div>

        <div class="field<?= isset($errors['max']) ? ' field--bad' : '' ?>">
          <label for="max">Highest price (<?= e($cur) ?>)</label>
          <input id="max" name="max" type="number" min="0" step="1" required placeholder="250"
                 value="<?= $editRow ? e((string) (int) $editRow['max']) : $value('max') ?>">
          <?php if (isset($errors['max'])): ?>
            <p class="field__error"><?= e($errors['max']) ?></p>
          <?php else: ?>
            <p class="field__hint">Set both to the same number for a fixed price.</p>
          <?php endif ?>
        </div>

        <div class="field">
          <label for="position">Order on the list</label>
          <input id="position" name="position" type="number" min="0" max="999" step="1"
                 value="<?= $editRow ? (int) $editRow['position'] : count($services) ?>">
          <p class="field__hint">Lower numbers appear first.</p>
        </div>
      </div>

      <div class="field<?= isset($errors['icon']) ? ' field--bad' : '' ?>" style="margin-top:18px">
        <label>Icon</label>
        <div class="iconpick">
          <?php
          $selected = $editRow ? $editRow['icon'] : ($old['icon'] ?? 'consult');
          foreach ($config['service_icons'] as $name): ?>
            <label class="iconpick__opt" title="<?= e($name) ?>">
              <input type="radio" name="icon" value="<?= e($name) ?>" <?= $selected === $name ? 'checked' : '' ?>>
              <span><?= icon($name) ?></span>
            </label>
          <?php endforeach ?>
        </div>
        <?php if (isset($errors['icon'])): ?><p class="field__error"><?= e($errors['icon']) ?></p><?php endif ?>
      </div>

      <div class="hero__cta" style="margin-top:8px">
        <button class="btn btn--orange" type="submit">
          <?= icon('check') ?> <?= $editRow ? 'Save changes' : 'Add service' ?>
        </button>
        <?php if ($editRow): ?>
          <a class="btn btn--ghost" href="/admin/services">Cancel</a>
        <?php endif ?>
      </div>
    </form>
  </div>

  <!-- ------------------------------------------------------------ the list -->
  <h3 class="fieldset__legend">Current services (<?= count($services) ?>)</h3>

  <div class="tablewrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Service</th><th>Duration</th><th class="ta-r">Price band</th>
          <th>Bookings</th><th>Status</th><th class="noprint">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($services as $slug => $s):
          $used = $usage[$slug] ?? 0;
          $isEditing = $s['id'] === $editing;
        ?>
          <tr<?= $isEditing ? ' class="row--editing"' : '' ?>>
            <td><?= (int) $s['position'] ?></td>
            <td>
              <span class="svc">
                <span class="svc__icon svc__icon--maroon"><?= icon($iconFor($s)) ?></span>
                <span>
                  <span class="svc__name"><?= e($s['label']) ?></span>
                  <small style="color:var(--muted);font-size:11.5px"><?= e($slug) ?></small>
                </span>
              </span>
            </td>
            <td class="svc__duration"><?= e($s['duration']) ?></td>
            <td class="ta-r due"><?= e(price_range($config, $s['min'], $s['max'])) ?></td>
            <td><?= $used ?></td>
            <td>
              <span class="pill pill--<?= $s['active'] ? 'checked_in' : 'cancelled' ?>">
                <?= $s['active'] ? 'Live' : 'Retired' ?>
              </span>
            </td>
            <td class="noprint">
              <div class="rowactions">
                <a class="btnlink" href="/admin/services?edit=<?= (int) $s['id'] ?>">Edit</a>

                <form method="post" action="/admin/services" style="display:contents">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <?php if ($s['active']): ?>
                    <button name="action" value="deactivate" title="Take it off the price list">Retire</button>
                  <?php else: ?>
                    <button name="action" value="activate" title="Put it back on the price list">Restore</button>
                  <?php endif ?>

                  <?php if ($used === 0): ?>
                    <button name="action" value="delete" class="danger"
                            onclick="return confirm('Delete “<?= e($s['label']) ?>” permanently? This cannot be undone.')">
                      Delete
                    </button>
                  <?php else: ?>
                    <span class="rowactions__note" title="<?= $used ?> booking(s) reference this service">In use</span>
                  <?php endif ?>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <p class="field__hint" style="margin-top:16px">
    <strong>Retire</strong> hides a service from the price list and the booking form, but keeps it
    on the appointments that already chose it. <strong>Delete</strong> is only offered once no
    booking references the service. Changing a price never rewrites a past client's ticket —
    each booking stores the prices it was quoted.
  </p>
</div>
