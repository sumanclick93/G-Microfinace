<?php use App\Core\Helpers; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Orders <span id="pending-badge" class="badge bg-danger"></span></h1>
  <span class="small text-muted" id="poll-status">Listening for new orders…</span>
</div>
<audio id="order-chime" preload="auto">
  <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdH2Onp6ViXx0eYyhp6OViHlyfI2lpZ+Rh3ZxfY+opZ6PhnRxfZCqp5+Qh3RxfZCqqJ+Qh3RyfpGqqJ+Qh3RyfpGqqJ+Qh3RyfpGqqJ+Qhw==" type="audio/wav">
</audio>
<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table align-middle mb-0" id="orders-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Slot</th><th>Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr data-id="<?= (int)$o['id'] ?>">
        <td><code><?= Helpers::e($o['order_number']) ?></code><br><small><?= Helpers::e($o['created_at']) ?></small></td>
        <td><?= Helpers::e($o['customer_name']) ?><br><small><?= Helpers::e($o['customer_phone']) ?></small></td>
        <td><?= Helpers::e(ucfirst($o['fulfillment_type'])) ?></td>
        <td><?= Helpers::e($o['delivery_time_slot']) ?></td>
        <td><?= Helpers::money($o['total_amount']) ?></td>
        <td><span class="badge status-<?= Helpers::e($o['order_status']) ?>"><?= Helpers::e(Helpers::statusLabel($o['order_status'])) ?></span></td>
        <td><a class="btn btn-sm btn-outline-dark" href="<?= Helpers::baseUrl('admin/orders/' . $o['id']) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>window.KRAVED_LAST_ORDER_ID = <?= (int)$lastId ?>;</script>
