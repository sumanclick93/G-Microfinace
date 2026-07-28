<?php use App\Core\Helpers; ?>
<div class="d-flex justify-content-between mb-4">
  <h1 class="h3">Categories</h1>
</div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6">Add Category</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/categories') ?>">
          <?= Helpers::csrfField() ?>
          <div class="mb-2"><input name="name" class="form-control" placeholder="Name" required></div>
          <div class="mb-2"><input name="slug" class="form-control" placeholder="Slug (optional)"></div>
          <div class="mb-2"><input name="display_order" type="number" class="form-control" value="0"></div>
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="status" id="st" checked><label for="st" class="form-check-label">Active</label></div>
          <button class="btn btn-warning">Create</button>
        </form>
        <hr>
        <h2 class="h6">Add Sub-category</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/subcategories') ?>">
          <?= Helpers::csrfField() ?>
          <div class="mb-2">
            <select name="category_id" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= Helpers::e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><input name="name" class="form-control" placeholder="Sub-category name" required></div>
          <button class="btn btn-outline-dark">Add Sub</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <ul class="list-group shadow-sm" id="category-sortable">
      <?php foreach ($categories as $c): ?>
        <li class="list-group-item" data-id="<?= (int)$c['id'] ?>">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong><?= Helpers::e($c['name']) ?></strong>
              <span class="text-muted small">/<?= Helpers::e($c['slug']) ?></span>
              <?php if (!(int)$c['status']): ?><span class="badge bg-secondary">Off</span><?php endif; ?>
              <?php if (!empty($c['subs'])): ?>
                <ul class="small mt-2 mb-0">
                  <?php foreach ($c['subs'] as $s): ?>
                    <li class="d-flex justify-content-between gap-2">
                      <span><?= Helpers::e($s['name']) ?></span>
                      <form method="post" action="<?= Helpers::baseUrl('admin/subcategories/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Delete sub-category?')">
                        <?= Helpers::csrfField() ?>
                        <button class="btn btn-link btn-sm text-danger p-0">×</button>
                      </form>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-1">
              <form method="post" action="<?= Helpers::baseUrl('admin/categories/' . $c['id'] . '/update') ?>" class="d-flex gap-1 flex-wrap">
                <?= Helpers::csrfField() ?>
                <input type="hidden" name="name" value="<?= Helpers::e($c['name']) ?>">
                <input type="hidden" name="slug" value="<?= Helpers::e($c['slug']) ?>">
                <input type="hidden" name="display_order" value="<?= (int)$c['display_order'] ?>">
                <?php if ((int)$c['status']): ?>
                  <input type="hidden" name="status" value="1">
                <?php endif; ?>
              </form>
              <form method="post" action="<?= Helpers::baseUrl('admin/categories/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Delete category?')">
                <?= Helpers::csrfField() ?>
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="small text-muted mt-2">Drag order is saved via the reorder API (use arrows in products display_order for fine control).</p>
  </div>
</div>
