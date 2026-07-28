<?php use App\Core\Helpers; ?>
<h1 class="h3 mb-4">Add-on Groups</h1>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">New Group</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/addons/groups') ?>">
          <?= Helpers::csrfField() ?>
          <input name="title" class="form-control mb-2" placeholder="Title" required>
          <div class="row g-2 mb-2">
            <div class="col"><input type="number" name="min_selection" class="form-control" placeholder="Min" value="0"></div>
            <div class="col"><input type="number" name="max_selection" class="form-control" placeholder="Max" value="1"></div>
          </div>
          <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_required" id="req"><label for="req" class="form-check-label">Required</label></div>
          <button class="btn btn-warning">Create Group</button>
        </form>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6">New Add-on</h2>
        <form method="post" action="<?= Helpers::baseUrl('admin/addons') ?>">
          <?= Helpers::csrfField() ?>
          <select name="group_id" class="form-select mb-2" required>
            <?php foreach ($groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= Helpers::e($g['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <input name="name" class="form-control mb-2" placeholder="Name" required>
          <input type="number" step="0.01" name="price" class="form-control mb-2" placeholder="Price" value="0">
          <button class="btn btn-outline-dark">Add Option</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php foreach ($groups as $g): ?>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <strong><?= Helpers::e($g['title']) ?></strong>
              <span class="text-muted small">min <?= (int)$g['min_selection'] ?> / max <?= (int)$g['max_selection'] ?>
                <?= (int)$g['is_required'] ? '· required' : '' ?></span>
            </div>
            <form method="post" action="<?= Helpers::baseUrl('admin/addons/groups/' . $g['id'] . '/delete') ?>" onsubmit="return confirm('Delete group?')">
              <?= Helpers::csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </div>
          <ul class="list-group list-group-flush mt-2">
            <?php foreach ($g['addons'] as $a): ?>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span><?= Helpers::e($a['name']) ?></span>
                <span>
                  <?= Helpers::money($a['price']) ?>
                  <form class="d-inline" method="post" action="<?= Helpers::baseUrl('admin/addons/' . $a['id'] . '/delete') ?>">
                    <?= Helpers::csrfField() ?>
                    <button class="btn btn-link btn-sm text-danger">×</button>
                  </form>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
