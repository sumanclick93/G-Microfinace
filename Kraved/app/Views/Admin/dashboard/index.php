<?php use App\Core\Helpers; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Dashboard</h1>
  <span class="text-muted small"><?= date('D j M Y') ?></span>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-label">Sales Today</div>
      <div class="stat-value"><?= Helpers::money($stats['sales_today']) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-label">Orders Today</div>
      <div class="stat-value"><?= (int) $stats['orders_today'] ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card accent">
      <div class="stat-label">Pending / Active</div>
      <div class="stat-value"><?= (int) $stats['pending'] ?></div>
    </div>
  </div>
</div>
<h2 class="h5 mb-3">Recent Orders</h2>
<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-hover mb-0 align-middle">
    <thead><tr>
      <th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><code><?= Helpers::e($o['order_number']) ?></code><br><small class="text-muted"><?= Helpers::e($o['created_at']) ?></small></td>
        <td><?= Helpers::e($o['customer_name']) ?></td>
        <td><?= Helpers::e(ucfirst($o['fulfillment_type'])) ?></td>
        <td><?= Helpers::money($o['total_amount']) ?></td>
        <td><span class="badge status-<?= Helpers::e($o['order_status']) ?>"><?= Helpers::e(Helpers::statusLabel($o['order_status'])) ?></span></td>
        <td><a href="<?= Helpers::baseUrl('admin/orders/' . $o['id']) ?>" class="btn btn-sm btn-outline-dark">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
