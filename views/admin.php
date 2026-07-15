<?php
/** @var array $config @var array $bookings @var array $stats @var array $reviews @var array $overall @var array $slots @var array $sms @var string $moved */
$movedNotice = [
    'ok'   => ['good', 'Appointment rescheduled. The client has been texted the new date and time.'],
    'date' => ['bad',  'That date is outside the booking window, so nothing was changed.'],
    'slot' => ['bad',  'That is not a real appointment time, so nothing was changed.'],
    'full' => ['bad',  'That window is already full on the day you chose. Pick another time.'],
    'same' => ['warn', 'That is the appointment’s current date and time — nothing to change.'],
][$moved] ?? null;

$liveSms = $config['sms']['driver'] !== 'log';
?>
<div class="page" style="max-width:1260px">
  <p class="eyebrow">Dashboard</p>
  <h1 class="page__title">Appointments</h1>

  <div class="admin__bar">
    <a class="btn btn--maroon btn--sm" href="/admin/services"><?= icon('tag') ?> Services &amp; prices</a>
    <a class="btn btn--maroon btn--sm" href="/admin/slots"><?= icon('calendar') ?> Hours &amp; closed days</a>
    <a class="btn btn--orange btn--sm" href="/admin/messages"><?= icon('phone') ?> SMS outbox<?= $sms['failed'] ? ' (' . $sms['failed'] . ' failed)' : '' ?></a>
    <a class="btn btn--green btn--sm" href="/admin/export"><?= icon('download') ?> Export CSV</a>
    <button class="btn btn--ghost btn--sm" onclick="window.print()"><?= icon('print') ?> Print list</button>
    <span class="spacer"></span>
    <a class="btn btn--ghost btn--sm" href="/admin/logout">Log out</a>
  </div>

  <?php if ($movedNotice): ?>
    <div class="alert alert--<?= e($movedNotice[0]) ?>"><?= e($movedNotice[1]) ?></div>
  <?php endif ?>

  <?php if (!$liveSms): ?>
    <div class="alert alert--warn noprint">
      <strong>SMS is in log mode.</strong> Messages are written to the
      <a href="/admin/messages">Outbox</a> but nothing is delivered. Set
      <code>SMS_DRIVER=mnotify</code> and <code>MNOTIFY_API_KEY</code> to send for real.
    </div>
  <?php endif ?>

  <div class="stats">
    <div class="stat">
      <div class="stat__n"><?= $stats['active'] ?></div>
      <div class="stat__l">Active bookings</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['upcoming'] ?></div>
      <div class="stat__l">Still upcoming</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['checked_in'] ?></div>
      <div class="stat__l">Seen</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['cancelled'] ?></div>
      <div class="stat__l">Cancelled</div>
    </div>
    <div class="stat">
      <div class="stat__n stat__n--money"><?= e(price_range($config, $stats['expected_min'], $stats['expected_max'])) ?></div>
      <div class="stat__l">Estimated value</div>
    </div>
    <div class="stat">
      <div class="stat__n">
        <?= $overall['count'] ? e(number_format($overall['avg'], 1)) : '—' ?>
        <?php if ($overall['count']): ?><small style="font-size:16px;color:var(--muted)">/5</small><?php endif ?>
      </div>
      <div class="stat__l"><?= (int) $overall['count'] ?> review<?= $overall['count'] === 1 ? '' : 's' ?></div>
    </div>
  </div>

  <h3 class="fieldset__legend">All bookings (<?= count($bookings) ?>)</h3>

  <?php if (!$bookings): ?>
    <div class="card empty">No appointments yet. Share the price list to get started.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th>Ref</th><th>Name</th><th>Phone</th><th>Location</th><th>Age</th><th>Gender</th>
            <th>Services</th><th>Appointment</th><th>Estimate</th><th>Status</th><th class="noprint">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b):
          $names = array_map(fn(string $k): string => service_label($config, $k), explode(',', $b['services']));
        ?>
          <tr id="b-<?= e($b['reference']) ?>">
            <td class="ref"><?= e($b['reference']) ?></td>
            <td><strong><?= e($b['full_name']) ?></strong><?= $b['email'] ? '<br><small style="color:var(--muted)">' . e($b['email']) . '</small>' : '' ?></td>
            <td><?= e($b['phone']) ?></td>
            <td><?= e($b['location']) ?></td>
            <td><?= (int) $b['age'] ?></td>
            <td><?= e($b['gender']) ?></td>
            <td style="max-width:260px"><small><?= e(implode(', ', $names)) ?></small></td>
            <td>
              <small><strong><?= e(pretty_date($b['appointment_date'])) ?></strong><br><?= e($b['slot_label']) ?></small>
            </td>
            <td class="due"><?= e(price_range($config, (float) $b['total_min'], (float) $b['total_max'])) ?></td>
            <td><span class="pill pill--<?= e($b['status']) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span></td>
            <td class="noprint">
              <form class="rowactions" method="post" action="/admin/status">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="reference" value="<?= e($b['reference']) ?>">

                <?php if ($b['status'] === 'pending'): ?>
                  <button class="primary" name="status" value="confirmed" title="Confirm and text the client">Confirm</button>
                <?php endif ?>

                <?php if ($b['status'] !== 'checked_in'): ?>
                  <button name="status" value="checked_in" title="Mark as seen">Seen</button>
                <?php else: ?>
                  <button name="status" value="confirmed" title="Undo">Undo</button>
                <?php endif ?>

                <?php if ($b['status'] !== 'cancelled'): ?>
                  <button name="status" value="cancelled" title="Cancel and text the client">Cancel</button>
                <?php else: ?>
                  <button name="status" value="pending" title="Restore booking">Restore</button>
                <?php endif ?>
              </form>

              <?php if ($b['status'] !== 'cancelled'): ?>
                <details class="reschedule">
                  <summary><?= icon('calendar') ?> Reschedule</summary>
                  <form method="post" action="/admin/reschedule" class="reschedule__form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="reference" value="<?= e($b['reference']) ?>">

                    <label>
                      New date
                      <input type="date" name="appointment_date" required
                             value="<?= e($b['appointment_date']) ?>"
                             min="<?= e(first_bookable_date($config)) ?>" max="<?= e(last_bookable_date($config)) ?>">
                    </label>

                    <label>
                      New time
                      <select name="slot_id" required>
                        <?php foreach ($slots as $slot): ?>
                          <option value="<?= (int) $slot['id'] ?>" <?= (int) $slot['id'] === (int) $b['slot_id'] ? 'selected' : '' ?>>
                            <?= e($slot['label']) ?>
                          </option>
                        <?php endforeach ?>
                      </select>
                    </label>

                    <button class="btn btn--orange btn--sm" type="submit">Move &amp; text client</button>
                  </form>
                </details>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <h3 class="fieldset__legend" id="reviews" style="margin-top:44px">
    Reviews (<?= count($reviews) ?>)
  </h3>

  <?php if (!$reviews): ?>
    <div class="card empty">
      No reviews yet. Clients can rate an appointment once you mark them <strong>Seen</strong>,
      or once the appointment date has passed.
    </div>
  <?php else: ?>
    <div class="reviewlist">
      <?php foreach ($reviews as $r): $hidden = !(int) $r['published']; ?>
        <article class="reviewcard<?= $hidden ? ' reviewcard--hidden' : '' ?>">
          <header class="reviewcard__head">
            <?= stars_html((float) $r['rating']) ?>
            <span class="reviewcard__who">
              <strong><?= e($r['full_name']) ?></strong>
              <small><?= e($r['location']) ?> &nbsp;•&nbsp; <?= e($r['reference']) ?></small>
            </span>
            <?php if ($hidden): ?><span class="pill pill--cancelled">Hidden</span><?php endif ?>
          </header>

          <?php if (trim((string) $r['comment']) !== ''): ?>
            <p class="reviewcard__body">“<?= e($r['comment']) ?>”</p>
          <?php else: ?>
            <p class="reviewcard__body reviewcard__body--empty">No comment left.</p>
          <?php endif ?>

          <footer class="reviewcard__foot noprint">
            <small><?= e(implode(', ', array_map(fn(string $k): string => service_label($config, $k), explode(',', $r['services'])))) ?></small>
            <form class="rowactions" method="post" action="/admin/review">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
              <button name="published" value="<?= $hidden ? '1' : '0' ?>">
                <?= $hidden ? 'Publish' : 'Hide from website' ?>
              </button>
            </form>
          </footer>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
