<?php use App\Core\Helpers; ?>
<h1 class="h3 mb-4">Delivery Zones</h1>
<div class="row g-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6">Add Zone</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/delivery-zones') ?>">
          <?= Helpers::csrfField() ?>
          <input name="postcode_prefix" class="form-control mb-2" placeholder="Prefix e.g. E1" required>
          <input type="number" step="0.01" name="delivery_fee" class="form-control mb-2" placeholder="Fee" required>
          <input type="number" step="0.01" name="min_order_amount" class="form-control mb-2" placeholder="Min order" value="12">
          <input type="number" name="estimated_mins" class="form-control mb-2" placeholder="ETA mins" value="45">
          <button class="btn btn-warning">Add</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table mb-0 align-middle">
        <thead><tr><th>Prefix</th><th>Fee</th><th>Min</th><th>ETA</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($zones as $z): ?>
          <tr>
            <td colspan="6" class="p-2">
              <form method="post" action="<?= Helpers::baseUrl('admin/delivery-zones/' . $z['id'] . '/update') ?>" class="row g-2 align-items-center">
                <?= Helpers::csrfField() ?>
                <div class="col-md-2"><input name="postcode_prefix" class="form-control form-control-sm" value="<?= Helpers::e($z['postcode_prefix']) ?>"></div>
                <div class="col-md-2"><input type="number" step="0.01" name="delivery_fee" class="form-control form-control-sm" value="<?= Helpers::e((string)$z['delivery_fee']) ?>"></div>
                <div class="col-md-2"><input type="number" step="0.01" name="min_order_amount" class="form-control form-control-sm" value="<?= Helpers::e((string)$z['min_order_amount']) ?>"></div>
                <div class="col-md-2"><input type="number" name="estimated_mins" class="form-control form-control-sm" value="<?= (int)$z['estimated_mins'] ?>"></div>
                <div class="col-md-1"><input type="checkbox" name="status" <?= (int)$z['status'] ? 'checked' : '' ?>></div>
                <div class="col-md-3 text-nowrap">
                  <button class="btn btn-sm btn-outline-dark">Save</button>
                  <button formaction="<?= Helpers::baseUrl('admin/delivery-zones/' . $z['id'] . '/delete') ?>" formmethod="post" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Del</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
