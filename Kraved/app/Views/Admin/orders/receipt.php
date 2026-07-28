<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt <?= Helpers::e($order['order_number']) ?></title>
  <style>
    body { font-family: ui-monospace, monospace; width: 280px; margin: 12px auto; font-size: 12px; }
    h1 { font-size: 16px; text-align: center; margin: 0; }
    .center { text-align: center; }
    hr { border: none; border-top: 1px dashed #000; }
    table { width: 100%; }
    td { vertical-align: top; }
    .right { text-align: right; }
    @media print { button { display: none; } }
  </style>
</head>
<body>
  <h1>KRAVED</h1>
  <p class="center">Freshly Baked Cookies</p>
  <hr>
  <p><strong><?= Helpers::e($order['order_number']) ?></strong><br><?= Helpers::e($order['created_at']) ?></p>
  <p><?= Helpers::e(ucfirst($order['fulfillment_type'])) ?> · <?= Helpers::e($order['delivery_time_slot']) ?></p>
  <p><?= Helpers::e($order['customer_name']) ?><br><?= Helpers::e($order['customer_phone']) ?>
  <?php if ($order['fulfillment_type'] === 'delivery'): ?><br><?= Helpers::e($order['delivery_address']) ?> <?= Helpers::e($order['postcode']) ?><?php endif; ?>
  </p>
  <hr>
  <table>
    <?php foreach ($items as $it):
      $meta = json_decode($it['addons_json'] ?? '{}', true) ?: [];
    ?>
    <tr>
      <td><?= (int)$it['quantity'] ?>x <?= Helpers::e($it['product_name']) ?>
        <?php foreach ($meta['addons'] ?? [] as $a): ?><br>&nbsp;&nbsp;+ <?= Helpers::e($a['name']) ?><?php endforeach; ?>
        <?php foreach ($meta['box_picks'] ?? [] as $b): ?><br>&nbsp;&nbsp;• <?= Helpers::e($b['name']) ?><?php endforeach; ?>
      </td>
      <td class="right"><?= Helpers::money($it['total_item_price']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr><td>Subtotal</td><td class="right"><?= Helpers::money($order['subtotal']) ?></td></tr>
    <tr><td>Delivery</td><td class="right"><?= Helpers::money($order['delivery_fee']) ?></td></tr>
    <tr><td><strong>TOTAL</strong></td><td class="right"><strong><?= Helpers::money($order['total_amount']) ?></strong></td></tr>
    <tr><td>Pay</td><td class="right"><?= Helpers::e(strtoupper($order['payment_method'])) ?></td></tr>
  </table>
  <hr>
  <p class="center">Thank you — bake fresh, eat warm.</p>
  <p class="center"><button onclick="window.print()">Print</button></p>
</body>
</html>
