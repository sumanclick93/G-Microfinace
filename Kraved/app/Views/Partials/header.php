<?php
use App\Core\AuthMiddleware;
use App\Core\Helpers;
$user = AuthMiddleware::user();
$f = $fulfillment ?? ['type' => 'collection', 'postcode' => '', 'time_slot' => 'ASAP'];
?>
<div class="fulfillment-bar">
  <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
    <button type="button" class="fulfillment-trigger" data-bs-toggle="modal" data-bs-target="#fulfillmentModal">
      <span class="ft-type" id="ff-type-label"><?= ($f['type'] ?? '') === 'delivery' ? 'Delivery' : 'Collection' ?></span>
      <span class="ft-sep">·</span>
      <span id="ff-detail-label">
        <?php if (($f['type'] ?? '') === 'delivery' && !empty($f['postcode'])): ?>
          <?= Helpers::e($f['postcode']) ?>
        <?php else: ?>
          Baker St, London
        <?php endif; ?>
      </span>
      <span class="ft-sep">·</span>
      <span id="ff-slot-label"><?= Helpers::e($f['time_slot'] ?? 'ASAP') ?></span>
      <span class="ft-change">Change</span>
    </button>
    <div class="d-flex align-items-center gap-3">
      <?php if ($user): ?>
        <span class="small text-white-50 d-none d-md-inline">Hi, <?= Helpers::e($user['name']) ?></span>
        <a class="link-light small" href="<?= Helpers::baseUrl('logout') ?>">Logout</a>
      <?php else: ?>
        <a class="link-light small" href="<?= Helpers::baseUrl('login') ?>">Login</a>
      <?php endif; ?>
      <button type="button" class="btn-basket" id="open-cart" aria-label="Open basket">
        Basket <span class="badge-count" id="cart-badge"><?= (int)($cartCount ?? 0) ?></span>
      </button>
    </div>
  </div>
</div>

<header class="site-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a class="brand" href="<?= Helpers::baseUrl() ?>" aria-label="Kraved home">
      <img src="<?= Helpers::asset('images/logo.jpeg') ?>" alt="Kraved" class="brand-logo">
    </a>
    <nav class="d-none d-lg-flex gap-3 small">
      <a href="<?= Helpers::baseUrl() ?>#menu">Menu</a>
      <a href="<?= Helpers::baseUrl() ?>#featured">Featured</a>
      <a href="<?= Helpers::baseUrl('checkout') ?>">Checkout</a>
    </nav>
  </div>
</header>
