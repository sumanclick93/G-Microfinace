<?php use App\Core\Helpers; ?>
<div class="d-flex justify-content-between mb-4">
  <h1 class="h3">Products</h1>
  <a href="<?= Helpers::baseUrl('admin/products/create') ?>" class="btn btn-warning">Add Product</a>
</div>
<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table align-middle mb-0">
    <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Flags</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td>
          <strong><?= Helpers::e($p['title']) ?></strong><br>
          <small class="text-muted"><?= Helpers::e($p['slug']) ?></small>
        </td>
        <td><?= Helpers::e($p['category_name']) ?></td>
        <td><?= Helpers::money($p['base_price']) ?></td>
        <td><?= (int)$p['stock_qty'] ?></td>
        <td>
          <?php if ((int)$p['is_featured']): ?><span class="badge bg-info">Featured</span><?php endif; ?>
          <?php if ((int)$p['is_box_deal']): ?><span class="badge bg-warning text-dark">Box</span><?php endif; ?>
          <?php if (!(int)$p['status']): ?><span class="badge bg-secondary">Off</span><?php endif; ?>
        </td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-outline-dark" href="<?= Helpers::baseUrl('admin/products/' . $p['id'] . '/edit') ?>">Edit</a>
          <form class="d-inline" method="post" action="<?= Helpers::baseUrl('admin/products/' . $p['id'] . '/delete') ?>" onsubmit="return confirm('Delete?')">
            <?= Helpers::csrfField() ?>
            <button class="btn btn-sm btn-outline-danger">Del</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
