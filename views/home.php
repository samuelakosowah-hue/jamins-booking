<?php
/** @var array $config @var array $ratings @var array $overall @var array $comments */
$co = $config['company'];

// The flyer cycles its service icons through maroon, orange and green.
$tones = ['maroon', 'orange', 'green'];
?>

<section class="hero">
  <div>
    <p class="eyebrow"><?= e($co['tagline']) ?></p>

    <h1>
      <span class="l1">Invest</span>
      <span class="l2">In Your</span>
      <span class="l3"><span class="edu">Health</span> <span class="amp">Today</span></span>
    </h1>

    <span class="hero__ribbon">Personalised Nutrition Care &nbsp;•&nbsp; <?= e($co['location']) ?></span>

    <?php if ($overall['count'] > 0): ?>
      <p class="hero__rating">
        <?= stars_html($overall['avg']) ?>
        <span><strong><?= e(number_format($overall['avg'], 1)) ?></strong>
          from <?= (int) $overall['count'] ?> client review<?= $overall['count'] === 1 ? '' : 's' ?></span>
      </p>
    <?php endif ?>

    <p class="hero__blurb"><?= e($co['blurb']) ?></p>

    <div class="hero__cta">
      <a class="btn btn--orange" href="/book"><?= icon('calendar') ?> Book an appointment</a>
      <a class="btn btn--ghost" href="#prices"><?= icon('tag') ?> See the price list</a>
    </div>
  </div>

  <div class="collage" aria-hidden="true">
    <div class="bubble bubble--main"><?= icon('consult') ?></div>
    <div class="bubble bubble--sugar"><?= icon('sugar') ?></div>
    <div class="bubble bubble--ribbon"><?= icon('hypertension') ?></div>
    <div class="bubble bubble--dot"><?= icon('heart') ?></div>
  </div>
</section>

<section class="section" id="prices">
  <div class="pricehead">
    <span class="pricehead__tag"><?= icon('tag') ?></span>
    <h2>One Month Service Price List</h2>
  </div>

  <div class="pricecard">
    <div class="tablewrap tablewrap--flush">
      <table class="pricetable">
        <thead>
          <tr>
            <th><span class="th"><?= icon('dietplan') ?> Service</span></th>
            <th><span class="th"><?= icon('calendar') ?> Duration</span></th>
            <th class="ta-r"><span class="th"><?= icon('tag') ?> Price (<?= e($config['currency']) ?>)</span></th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 0; foreach ($config['services'] as $key => $svc): $tone = $tones[$i++ % 3];
                $rating = $ratings[$key] ?? null; ?>
            <tr>
              <td>
                <span class="svc">
                  <span class="svc__icon svc__icon--<?= $tone ?>"><?= icon($svc['icon']) ?></span>
                  <span>
                    <span class="svc__name"><?= e($svc['label']) ?></span>
                    <?php if ($rating): ?>
                      <span class="svc__rating">
                        <?= stars_html($rating['avg']) ?>
                        <small><?= e(number_format($rating['avg'], 1)) ?> (<?= (int) $rating['count'] ?>)</small>
                      </span>
                    <?php endif ?>
                  </span>
                </span>
              </td>
              <td class="svc__duration"><?= e($svc['duration']) ?></td>
              <td class="ta-r svc__price"><?= e(number_format((float) $svc['min'], 0)) ?>–<?= e(number_format((float) $svc['max'], 0)) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <p class="pricecard__note">
      Prices are a guide. The exact fee is agreed with you at your first consultation,
      and depends on how much follow-up your plan needs.
      <?php if ($overall['count'] > 0): ?>
        Star ratings come from clients we have actually seen.
      <?php endif ?>
    </p>
  </div>

  <?php if ($comments): ?>
    <div class="quotes">
      <h3 class="quotes__title">What clients say</h3>
      <div class="quotes__grid">
        <?php foreach ($comments as $c): ?>
          <figure class="quote">
            <?= stars_html((float) $c['rating']) ?>
            <blockquote>“<?= e($c['comment']) ?>”</blockquote>
            <figcaption><?= e(reviewer_name($c)) ?></figcaption>
          </figure>
        <?php endforeach ?>
      </div>
    </div>
  <?php endif ?>

  <div class="promo">
    <div class="promo__seal">
      <small>Invest in your</small>
      <strong>Health</strong>
      <small>Today!</small>
    </div>

    <ul class="benefits">
      <?php foreach ($config['benefits'] as $benefit): ?>
        <li><span class="tick"><?= icon('check') ?></span> <?= e($benefit) ?></li>
      <?php endforeach ?>
    </ul>

    <div class="promo__cta">
      <p>Ready to start?</p>
      <a class="btn btn--maroon" href="/book"><?= icon('calendar') ?> Book your appointment</a>
    </div>
  </div>
</section>
