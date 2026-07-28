<?php use App\Core\Helpers; ?>
<div class="d-flex justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-1">Order <?= Helpers::e($order['order_number']) ?></h1>
    <p class="text-muted mb-0"><?= Helpers::e($order['created_at']) ?> · <?= Helpers::e(ucfirst($order['fulfillment_type'])) ?> · <?= Helpers::e($order['delivery_time_slot']) ?></p>
  </div>
  <a class="btn btn-outline-dark" href="<?= Helpers::baseUrl('admin/orders/' . $order['id'] . '/receipt') ?>" target="_blank">Print Receipt</a>
</div>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">Customer</h2>
        <p class="mb-1"><?= Helpers::e($order['customer_name']) ?></p>
        <p class="mb-1"><?= Helpers::e($order['customer_email']) ?></p>
        <p class="mb-1"><?= Helpers::e($order['customer_phone']) ?></p>
        <?php if ($order['fulfillment_type'] === 'delivery'): ?>
          <p class="mb-0"><?= Helpers::e($order['delivery_address']) ?><br><?= Helpers::e($order['postcode']) ?></p>
        <?php endif; ?>
        <?php if ($order['notes']): ?><p class="mt-2 small"><em><?= Helpers::e($order['notes']) ?></em></p><?php endif; ?>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6">Update Status</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/orders/' . $order['id'] . '/status') ?>">
          <?= Helpers::csrfField() ?>
          <select name="order_status" class="form-select mb-2">
            <?php foreach (['received','baking','ready','out_for_delivery','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>><?= Helpers::e(Helpers::statusLabel($s)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-warning w-100">Update</button>
        </form>
        <div class="d-grid gap-2 mt-3">
          <?php
          $quick = $order['fulfillment_type'] === 'delivery'
            ? ['received','baking','out_for_delivery','completed']
            : ['received','baking','ready','completed'];
          foreach ($quick as $s):
          ?>
          <form method="post" action="<?= Helpers::baseUrl('admin/orders/' . $order['id'] . '/status') ?>">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="order_status" value="<?= $s ?>">
            <button class="btn btn-sm btn-outline-dark w-100 <?= $order['order_status']===$s?'active':'' ?>" type="submit"><?= Helpers::e(Helpers::statusLabel($s)) ?></button>
          </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6">Items</h2>
        <table class="table">
          <thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it):
            $meta = json_decode($it['addons_json'] ?? '{}', true) ?: [];
          ?>
            <tr>
              <td>
                <strong><?= Helpers::e($it['product_name']) ?></strong>
                <?php if (!empty($meta['addons'])): ?>
                  <ul class="small text-muted mb-0">
                    <?php foreach ($meta['addons'] as $a): ?>
                      <li><?= Helpers::e($a['name']) ?> (+<?= Helpers::money($a['price']) ?>)</li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (!empty($meta['box_picks'])): ?>
                  <ul class="small text-muted mb-0">
                    <?php foreach ($meta['box_picks'] as $b): ?>
                      <li><?= Helpers::e($b['name']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </td>
              <td><?= (int)$it['quantity'] ?></td>
              <td><?= Helpers::money($it['total_item_price']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="2">Subtotal</td><td><?= Helpers::money($order['subtotal']) ?></td></tr>
            <tr><td colspan="2">Delivery</td><td><?= Helpers::money($order['delivery_fee']) ?></td></tr>
            <tr><th colspan="2">Total</th><th><?= Helpers::money($order['total_amount']) ?></th></tr>
            <tr><td colspan="2">Payment</td><td><?= Helpers::e(strtoupper($order['payment_method'])) ?> / <?= Helpers::e($order['payment_status']) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
