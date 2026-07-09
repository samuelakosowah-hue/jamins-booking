<?php
/** @var array $config @var array $messages @var array $stats */
$driver  = $config['sms']['driver'];
$liveSms = $driver !== 'log';

$kinds = [
    'booked'      => 'Booking received',
    'confirmed'   => 'Confirmed',
    'rescheduled' => 'Rescheduled',
    'cancelled'   => 'Cancelled',
    'admin_new'   => 'New booking alert',
];
?>

<div class="page" style="max-width:1100px">
  <p class="eyebrow">Notifications</p>
  <h1 class="page__title">SMS <em>outbox</em></h1>
  <p class="page__lede">
    Every message the system has sent, or would have sent. Clients are texted their booking ID,
    date, time and services — and again whenever you confirm, reschedule or cancel.
  </p>

  <div class="admin__bar">
    <a class="btn btn--ghost btn--sm" href="/admin">&larr; Appointments</a>
    <span class="spacer"></span>
    <span class="driverchip driverchip--<?= $liveSms ? 'live' : 'log' ?>">
      Driver: <strong><?= e($driver) ?></strong>
    </span>
  </div>

  <?php if (!$liveSms): ?>
    <div class="alert alert--warn">
      <strong>Nothing is being delivered.</strong> The <code>log</code> driver records messages here
      so you can check the wording, but no SMS leaves the server. To send for real, restart with
      <code>SMS_DRIVER=mnotify MNOTIFY_API_KEY=your-key SMS_SENDER_ID=YourID</code>.
    </div>
  <?php elseif ($config['sms']['api_key'] === ''): ?>
    <div class="alert alert--bad">
      Driver is <code>mnotify</code> but <code>MNOTIFY_API_KEY</code> is empty — every message will fail.
    </div>
  <?php endif ?>

  <div class="stats">
    <div class="stat">
      <div class="stat__n"><?= $stats['total'] ?></div>
      <div class="stat__l">Messages</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['sent'] ?></div>
      <div class="stat__l">Delivered</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['logged'] ?></div>
      <div class="stat__l">Logged only</div>
    </div>
    <div class="stat">
      <div class="stat__n"><?= $stats['failed'] ?></div>
      <div class="stat__l">Failed</div>
    </div>
  </div>

  <?php if (!$messages): ?>
    <div class="card empty">No messages yet. They appear here the moment someone books.</div>
  <?php else: ?>
    <div class="msglist">
      <?php foreach ($messages as $m): $segments = sms_segments($m['body']); ?>
        <article class="msg msg--<?= e($m['status']) ?>">
          <header class="msg__head">
            <span class="pill pill--<?= e($m['status']) ?>"><?= e($m['status']) ?></span>
            <span class="msg__kind"><?= e($kinds[$m['kind']] ?? $m['kind']) ?></span>
            <?php if ($m['audience'] === 'admin'): ?>
              <span class="tag tag--paid">To staff</span>
            <?php endif ?>
            <span class="spacer"></span>
            <?php if ($m['reference']): ?>
              <a class="msg__ref" href="/admin#b-<?= e($m['reference']) ?>"><?= e($m['reference']) ?></a>
            <?php endif ?>
          </header>

          <p class="msg__to">
            <?= icon('phone') ?> <strong><?= e($m['recipient']) ?></strong>
            <small><?= e(date('j M Y, H:i', strtotime($m['created_at']))) ?></small>
          </p>

          <p class="msg__body"><?= e($m['body']) ?></p>

          <footer class="msg__foot">
            <small>
              <?= mb_strlen($m['body']) ?> chars &nbsp;•&nbsp;
              <?= $segments ?> segment<?= $segments === 1 ? '' : 's' ?>
              <?php if ($m['response']): ?>
                <br><span class="msg__response"><?= e($m['response']) ?></span>
              <?php endif ?>
            </small>

            <?php if ($m['status'] !== 'sent' && $liveSms): ?>
              <form class="rowactions" method="post" action="/admin/messages/retry">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                <button type="submit">Retry</button>
              </form>
            <?php endif ?>
          </footer>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
