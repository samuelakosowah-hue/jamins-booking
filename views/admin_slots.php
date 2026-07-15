<?php
/** @var array $config @var array $slots @var array $usage @var array $totals @var array $closedDates
 *  @var array $old @var array $errors @var int $editing @var string $flash
 *  @var string $closedError @var array $closedOld
 */
$totals = $totals ?? [];
$messages = [
    'added'          => 'Time window added.',
    'updated'        => 'Time window updated.',
    'deleted'        => 'Time window deleted.',
    'delete_blocked' => 'That window still has bookings, so it cannot be deleted.',
    'closed_added'   => 'Closed date added — clients cannot book that day.',
    'closed_removed' => 'Closed date removed.',
];

$weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$closedWeekdays = $config['closed_weekdays'] ?? [];

$editRow = null;
foreach ($slots as $s) {
    if ((int) $s['id'] === $editing) {
        $editRow = $s;
        break;
    }
}

$val = function (string $field, ?array $row = null) use ($old, $editing): string {
    if ($old && (int) ($old['id'] ?? 0) === $editing) {
        return e((string) ($old[$field] ?? ''));
    }
    if ($row) {
        return e((string) ($row[$field] ?? ''));
    }
    return e((string) ($old[$field] ?? ''));
};
?>

<div class="page" style="max-width:1100px">
  <p class="eyebrow">Hours</p>
  <h1 class="page__title">Time windows &amp; <em>closed days</em></h1>
  <p class="page__lede">
    Capacity is per window <strong>per day</strong>. Closing a date blocks new bookings
    (and staff reschedules) for that day. Weekly closures are set in config
    (currently
    <?php if ($closedWeekdays): ?>
      <?= e(implode(', ', array_map(fn(int $d): string => $weekdayNames[$d] ?? (string) $d, $closedWeekdays))) ?>
    <?php else: ?>
      none
    <?php endif ?>).
  </p>

  <div class="admin__bar">
    <a class="btn btn--ghost btn--sm" href="/admin">&larr; Appointments</a>
    <a class="btn btn--ghost btn--sm" href="/admin/services">Services</a>
    <span class="spacer"></span>
  </div>

  <?php if ($flash && isset($messages[$flash])): ?>
    <div class="alert alert--<?= str_starts_with($flash, 'delete_blocked') ? 'bad' : 'good' ?>">
      <?= e($messages[$flash]) ?>
    </div>
  <?php endif ?>
  <?php if ($errors): ?>
    <div class="alert alert--bad">Please fix the highlighted fields below.</div>
  <?php endif ?>
  <?php if ($closedError): ?>
    <div class="alert alert--bad"><?= e($closedError) ?></div>
  <?php endif ?>

  <!-- ----------------------------------------------------------- add / edit -->
  <div class="card" style="margin-bottom:34px">
    <h3 class="fieldset__legend" style="margin-bottom:18px">
      <?= $editRow ? 'Edit time window' : 'Add a time window' ?>
    </h3>

    <form class="form" method="post" action="/admin/slots">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editRow): ?>
        <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
      <?php endif ?>

      <div class="grid2">
        <div class="field<?= isset($errors['label']) ? ' field--bad' : '' ?>">
          <label for="label">Label</label>
          <input id="label" name="label" type="text" maxlength="60" required
                 placeholder="8:00 AM  –  9:00 AM"
                 value="<?= $editRow ? $val('label', $editRow) : $val('label') ?>">
          <?php if (isset($errors['label'])): ?><p class="field__error"><?= e($errors['label']) ?></p><?php endif ?>
        </div>

        <div class="field<?= isset($errors['capacity']) ? ' field--bad' : '' ?>">
          <label for="capacity">Clients per day</label>
          <input id="capacity" name="capacity" type="number" min="1" max="50" required
                 value="<?= $editRow ? e((string) (int) $editRow['capacity']) : ($val('capacity') !== '' ? $val('capacity') : '4') ?>">
          <?php if (isset($errors['capacity'])): ?><p class="field__error"><?= e($errors['capacity']) ?></p><?php endif ?>
        </div>
      </div>

      <div class="field" style="margin-top:18px;max-width:200px">
        <label for="position">Display order</label>
        <input id="position" name="position" type="number" min="0" max="999"
               value="<?= $editRow ? e((string) (int) $editRow['position']) : ($val('position') !== '' ? $val('position') : '0') ?>">
      </div>

      <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap">
        <button class="btn btn--maroon" type="submit">
          <?= $editRow ? 'Save changes' : 'Add window' ?>
        </button>
        <?php if ($editRow): ?>
          <a class="btn btn--ghost" href="/admin/slots">Cancel edit</a>
        <?php endif ?>
      </div>
    </form>
  </div>

  <!-- ---------------------------------------------------------------- list -->
  <h3 class="fieldset__legend">Current windows (<?= count($slots) ?>)</h3>

  <?php if (!$slots): ?>
    <div class="card empty">No time windows yet — add at least one so clients can book.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th>Order</th>
            <th>Window</th>
            <th>Capacity / day</th>
            <th>Active bookings</th>
            <th class="noprint">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($slots as $s):
          $sid = (int) $s['id'];
          $n = (int) ($usage[$sid] ?? 0);
          $all = (int) ($totals[$sid] ?? 0);
        ?>
          <tr>
            <td><?= (int) $s['position'] ?></td>
            <td><strong><?= e($s['label']) ?></strong></td>
            <td><?= (int) $s['capacity'] ?></td>
            <td><?= $n ?></td>
            <td class="noprint">
              <div class="rowactions">
                <a class="btn btn--ghost btn--sm" href="/admin/slots?edit=<?= $sid ?>">Edit</a>
                <?php if ($all === 0): ?>
                  <form method="post" action="/admin/slots" style="display:inline"
                        onsubmit="return confirm('Delete this time window permanently?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $sid ?>">
                    <button type="submit" class="btn btn--ghost btn--sm">Delete</button>
                  </form>
                <?php else: ?>
                  <small style="color:var(--muted)">In use</small>
                <?php endif ?>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <!-- ----------------------------------------------------------- closed days -->
  <h3 class="fieldset__legend" style="margin-top:44px">One-off closed dates</h3>
  <p class="page__lede" style="margin-top:0">
    Public holidays, leave days, or anything outside the weekly pattern.
  </p>

  <div class="card" style="margin-bottom:24px">
    <form class="form" method="post" action="/admin/slots">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_closed">
      <div class="grid2">
        <div class="field">
          <label for="closed_date">Date</label>
          <input id="closed_date" name="date" type="date" required
                 min="<?= e(date('Y-m-d')) ?>"
                 value="<?= e((string) ($closedOld['date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="closed_reason">Reason <span style="color:var(--muted);font-weight:500">(optional)</span></label>
          <input id="closed_reason" name="reason" type="text" maxlength="120"
                 placeholder="Public holiday"
                 value="<?= e((string) ($closedOld['reason'] ?? '')) ?>">
        </div>
      </div>
      <div style="margin-top:18px">
        <button class="btn btn--orange" type="submit">Close this date</button>
      </div>
    </form>
  </div>

  <?php if (!$closedDates): ?>
    <div class="card empty">No one-off closed dates yet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Reason</th><th class="noprint"></th></tr>
        </thead>
        <tbody>
        <?php foreach ($closedDates as $c): ?>
          <tr>
            <td><strong><?= e(pretty_date($c['date'])) ?></strong></td>
            <td><?= e((string) ($c['reason'] ?? '—')) ?></td>
            <td class="noprint">
              <form method="post" action="/admin/slots">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove_closed">
                <input type="hidden" name="date" value="<?= e($c['date']) ?>">
                <button type="submit" class="btn btn--ghost btn--sm">Re-open</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
