<?php use App\Core\Helpers; $c = $cart; $f = $fulfillment; ?>
<section class="section pt-4">
  <div class="container" style="max-width:720px">
    <h1 class="section-title">Checkout</h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= Helpers::e($error) ?></div>
    <?php endif; ?>

    <div class="checkout-summary mb-4">
      <?php foreach ($c['items'] as $item): ?>
        <div class="d-flex justify-content-between py-2 border-bottom border-secondary border-opacity-25">
          <div>
            <strong><?= (int)$item['quantity'] ?>× <?= Helpers::e($item['name']) ?></strong>
            <?php foreach ($item['addons'] ?? [] as $a): ?>
              <div class="small opacity-75">+ <?= Helpers::e($a['name']) ?></div>
            <?php endforeach; ?>
            <?php foreach ($item['box_picks'] ?? [] as $b): ?>
              <div class="small opacity-75">• <?= Helpers::e($b['name']) ?></div>
            <?php endforeach; ?>
          </div>
          <span><?= Helpers::money($item['line_total']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="d-flex justify-content-between mt-2"><span>Subtotal</span><span><?= Helpers::money($c['subtotal']) ?></span></div>
      <div class="d-flex justify-content-between"><span>Delivery</span><span><?= Helpers::money($c['delivery_fee']) ?></span></div>
      <div class="d-flex justify-content-between fw-bold mt-1"><span>Total</span><span><?= Helpers::money($c['total']) ?></span></div>
    </div>

    <form method="post" action="<?= Helpers::baseUrl('checkout') ?>" class="checkout-form">
      <?= Helpers::csrfField() ?>
      <input type="hidden" name="delivery_time_slot" value="<?= Helpers::e($f['time_slot'] ?? 'ASAP') ?>">

      <h2 class="h5 mt-4">Your details</h2>
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="customer_name" class="form-control" required value="<?= Helpers::e($user['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="customer_email" class="form-control" required value="<?= Helpers::e($user['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input name="customer_phone" class="form-control" required>
      </div>

      <?php if (($f['type'] ?? '') === 'delivery'): ?>
        <h2 class="h5 mt-4">Delivery address</h2>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <input name="delivery_address" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Postcode</label>
          <input name="postcode" class="form-control" required value="<?= Helpers::e($f['postcode'] ?? '') ?>">
        </div>
      <?php else: ?>
        <p class="small opacity-75">Collection from 12 Baker Street, London W1U 3BW · <?= Helpers::e($f['time_slot'] ?? 'ASAP') ?></p>
      <?php endif; ?>

      <h2 class="h5 mt-4">Payment</h2>
      <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="payment_method" id="pay-cod" value="cod" checked>
        <label class="form-check-label" for="pay-cod">Cash on Delivery / Collection</label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="radio" name="payment_method" id="pay-card" value="card">
        <label class="form-check-label" for="pay-card">Card (stub — pay on arrival)</label>
      </div>

      <?php if (!$user): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="create_account" id="create_account" value="1">
          <label class="form-check-label" for="create_account">Create an account</label>
        </div>
        <div class="mb-3" id="reg-pass" style="display:none">
          <input type="password" name="password" class="form-control" placeholder="Password (min 6)" minlength="6">
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Order notes</label>
        <textarea name="notes" class="form-control" rows="2" placeholder="Allergies, doorbell notes…"></textarea>
      </div>

      <button class="btn btn-accent btn-lg w-100" type="submit">Place Order · <?= Helpers::money($c['total']) ?></button>
    </form>
  </div>
</section>
<script>
document.getElementById('create_account')?.addEventListener('change', function() {
  document.getElementById('reg-pass').style.display = this.checked ? '' : 'none';
});
</script>
