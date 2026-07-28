<?php use App\Core\Helpers; $p = $product; ?>
<h1 class="h3 mb-4"><?= Helpers::e($title) ?></h1>
<form method="post" enctype="multipart/form-data"
  action="<?= $p ? Helpers::baseUrl('admin/products/' . $p['id'] . '/update') : Helpers::baseUrl('admin/products') ?>"
  class="card border-0 shadow-sm">
  <div class="card-body row g-3">
    <?= Helpers::csrfField() ?>
    <div class="col-md-6">
      <label class="form-label">Title</label>
      <input name="title" class="form-control" required value="<?= Helpers::e($p['title'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Slug</label>
      <input name="slug" class="form-control" value="<?= Helpers::e($p['slug'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Category</label>
      <select name="category_id" id="category_id" class="form-select" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (($p['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Sub-category</label>
      <select name="sub_category_id" id="sub_category_id" class="form-select">
        <option value="">— None —</option>
        <?php foreach ($subs as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (($p['sub_category_id'] ?? '') == $s['id']) ? 'selected' : '' ?>><?= Helpers::e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Base Price (£)</label>
      <input type="number" step="0.01" name="base_price" class="form-control" required value="<?= Helpers::e((string)($p['base_price'] ?? '0')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Stock</label>
      <input type="number" name="stock_qty" class="form-control" value="<?= (int)($p['stock_qty'] ?? 0) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Weight / Pack</label>
      <input name="weight_label" class="form-control" value="<?= Helpers::e($p['weight_label'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Display order</label>
      <input type="number" name="display_order" class="form-control" value="<?= (int)($p['display_order'] ?? 0) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Image</label>
      <input type="file" name="image" class="form-control" accept="image/*">
      <?php if (!empty($p['image'])): ?>
        <img src="<?= Helpers::upload($p['image']) ?>" alt="" class="mt-2 rounded" style="height:48px">
      <?php endif; ?>
    </div>
    <div class="col-12">
      <label class="form-label">Short description</label>
      <input name="short_description" class="form-control" value="<?= Helpers::e($p['short_description'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Full description</label>
      <textarea name="full_description" class="form-control" rows="3"><?= Helpers::e($p['full_description'] ?? '') ?></textarea>
    </div>
    <div class="col-12 d-flex flex-wrap gap-4">
      <div class="form-check"><input class="form-check-input" type="checkbox" name="status" id="status" <?= !isset($p) || (int)($p['status'] ?? 1) ? 'checked' : '' ?>><label for="status" class="form-check-label">Active</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="feat" <?= (int)($p['is_featured'] ?? 0) ? 'checked' : '' ?>><label for="feat" class="form-check-label">Featured</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_box_deal" id="box" <?= (int)($p['is_box_deal'] ?? 0) ? 'checked' : '' ?>><label for="box" class="form-check-label">Box Deal</label></div>
    </div>
    <div class="col-md-4" id="box-max-wrap" style="<?= (int)($p['is_box_deal'] ?? 0) ? '' : 'display:none' ?>">
      <label class="form-label">Box max items</label>
      <input type="number" name="box_max_items" class="form-control" value="<?= (int)($p['box_max_items'] ?? 4) ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Add-on groups</label>
      <div class="row">
        <?php foreach ($addonGroups as $g): ?>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="addon_groups[]" value="<?= (int)$g['id'] ?>" id="ag<?= (int)$g['id'] ?>"
                <?= in_array((int)$g['id'], $selectedGroups, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ag<?= (int)$g['id'] ?>"><?= Helpers::e($g['title']) ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-12" id="box-choices-wrap" style="<?= (int)($p['is_box_deal'] ?? 0) ? '' : 'display:none' ?>">
      <label class="form-label">Eligible cookies for this box</label>
      <div class="row">
        <?php foreach ($allProducts as $ap): ?>
          <?php if ((int)$ap['is_box_deal']) continue; ?>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="box_choices[]" value="<?= (int)$ap['id'] ?>" id="bc<?= (int)$ap['id'] ?>"
                <?= in_array((int)$ap['id'], $selectedChoices, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="bc<?= (int)$ap['id'] ?>"><?= Helpers::e($ap['title']) ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-12">
      <button class="btn btn-warning">Save Product</button>
      <a href="<?= Helpers::baseUrl('admin/products') ?>" class="btn btn-link">Cancel</a>
    </div>
  </div>
</form>
<script>
document.getElementById('box')?.addEventListener('change', function() {
  document.getElementById('box-max-wrap').style.display = this.checked ? '' : 'none';
  document.getElementById('box-choices-wrap').style.display = this.checked ? '' : 'none';
});
document.getElementById('category_id')?.addEventListener('change', async function() {
  const res = await fetch(KRAVED.baseUrl + '/admin/api/categories/' + this.value + '/subs');
  const data = await res.json();
  const sel = document.getElementById('sub_category_id');
  sel.innerHTML = '<option value="">— None —</option>';
  data.forEach(s => {
    const o = document.createElement('option');
    o.value = s.id; o.textContent = s.name; sel.appendChild(o);
  });
});
</script>
