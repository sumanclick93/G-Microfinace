<?php use App\Core\Helpers; ?>
<section class="section pt-4">
  <div class="container" style="max-width:640px">
    <p class="text-uppercase small letter-spaced opacity-75 mb-1">Order confirmed</p>
    <h1 class="section-title mb-1"><?= Helpers::e($order['order_number']) ?></h1>
    <p class="mb-4">Thanks <?= Helpers::e($order['customer_name']) ?> — we're on it.</p>

    <div class="status-tracker mb-5" id="status-tracker" data-order="<?= Helpers::e($order['order_number']) ?>">
      <?php foreach ($steps as $step): ?>
        <div class="status-step <?= Helpers::e($step['state']) ?>" data-key="<?= Helpers::e($step['key']) ?>">
          <div class="status-dot"></div>
          <div class="status-label"><?= Helpers::e($step['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="checkout-summary">
      <p class="mb-2"><strong><?= Helpers::e(ucfirst($order['fulfillment_type'])) ?></strong> · <?= Helpers::e($order['delivery_time_slot']) ?></p>
      <?php foreach ($items as $it):
        $meta = json_decode($it['addons_json'] ?? '{}', true) ?: [];
      ?>
        <div class="d-flex justify-content-between py-2 border-bottom border-secondary border-opacity-25">
          <div>
            <?= (int)$it['quantity'] ?>× <?= Helpers::e($it['product_name']) ?>
            <?php foreach ($meta['addons'] ?? [] as $a): ?>
              <div class="small opacity-75">+ <?= Helpers::e($a['name']) ?></div>
            <?php endforeach; ?>
          </div>
          <span><?= Helpers::money($it['total_item_price']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="d-flex justify-content-between fw-bold mt-2">
        <span>Total</span><span><?= Helpers::money($order['total_amount']) ?></span>
      </div>
    </div>

    <a href="<?= Helpers::baseUrl() ?>" class="btn btn-ghost mt-4">Back to menu</a>
  </div>
</section>
