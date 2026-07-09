<?php
/** @var string $content */
/** @var array $config */
$co = $config['company'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($co['name']) ?> — Book an appointment</title>
<meta name="description" content="<?= e($co['name']) ?> — <?= e($co['tagline']) ?> Nutrition consultation, hypertension and diabetes management, weight, pregnancy and child nutrition. Book an appointment in <?= e($co['location']) ?>.">
<link rel="stylesheet" href="/assets/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>&#127793;</text></svg>">
</head>
<body>

<div class="leaf leaf--tl" aria-hidden="true"></div>
<div class="leaf leaf--br" aria-hidden="true"></div>

<header class="topbar">
  <a class="brand" href="/">
    <span class="brand__mark"><?= logo_jamins() ?></span>
    <span class="brand__text">
      <strong><?= e($co['short']) ?></strong>
      <small>Nutrition Consult</small>
      <em><?= e($co['tagline']) ?></em>
    </span>
  </a>

  <nav class="topnav">
    <a href="/#prices">Price list</a>
    <a href="/lookup">Find my booking</a>
    <a class="btn btn--sm btn--orange" href="/book">Book appointment</a>
  </nav>
</header>

<main>
  <?= $content ?>
</main>

<section class="contactbar">
  <div class="contactbar__item">
    <span class="contactbar__icon"><?= icon('pin') ?></span>
    <span>
      <small>Location</small>
      <strong><?= e($co['location']) ?></strong>
    </span>
  </div>
  <div class="contactbar__rule" aria-hidden="true"></div>
  <div class="contactbar__item">
    <span class="contactbar__icon contactbar__icon--green"><?= icon('phone') ?></span>
    <span>
      <small>Contact</small>
      <strong>
        <?php foreach ($co['phones'] as $i => $phone): ?>
          <a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a><?= $i < count($co['phones']) - 1 ? ' / ' : '' ?>
        <?php endforeach ?>
      </strong>
    </span>
  </div>
</section>

<footer class="footer">
  <p class="footer__script"><?= e($co['motto']) ?></p>
  <p class="footer__meta">
    <strong><?= e($co['name']) ?></strong>
    &nbsp;•&nbsp; <?= e($co['tagline']) ?>
    &nbsp;•&nbsp; <a href="/admin">Staff login</a>
  </p>
</footer>

</body>
</html>
