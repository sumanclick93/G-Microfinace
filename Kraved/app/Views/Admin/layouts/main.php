<?php
use App\Core\AuthMiddleware;
use App\Core\Helpers;
$user = AuthMiddleware::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::e($title ?? 'Admin') ?> — Kraved Admin</title>
  <link rel="icon" href="<?= Helpers::asset('images/logo.jpeg') ?>" type="image/jpeg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= Helpers::asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body class="admin-body">
<nav class="navbar navbar-dark bg-dark border-bottom border-secondary">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= Helpers::baseUrl('admin') ?>">
      <img src="<?= Helpers::asset('images/logo.jpeg') ?>" alt="Kraved" class="admin-brand-logo">
      <span>Admin</span>
    </a>
    <div class="d-flex align-items-center gap-3 text-white-50 small">
      <span><?= Helpers::e($user['name'] ?? '') ?></span>
      <a class="btn btn-sm btn-outline-light" href="<?= Helpers::baseUrl() ?>" target="_blank">View Store</a>
      <a class="btn btn-sm btn-warning" href="<?= Helpers::baseUrl('admin/logout') ?>">Logout</a>
    </div>
  </div>
</nav>
<div class="d-flex">
  <aside class="admin-sidebar p-3">
    <ul class="nav flex-column gap-1">
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin') ?>">Dashboard</a></li>
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin/orders') ?>">Orders</a></li>
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin/products') ?>">Products</a></li>
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin/categories') ?>">Categories</a></li>
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin/addons') ?>">Add-ons</a></li>
      <li><a class="nav-link" href="<?= Helpers::baseUrl('admin/delivery-zones') ?>">Delivery Zones</a></li>
    </ul>
  </aside>
  <main class="admin-main flex-grow-1 p-4">
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= Helpers::e($success) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</div>
<script>
  window.KRAVED = {
    baseUrl: <?= json_encode(Helpers::baseUrl()) ?>,
    csrf: <?= json_encode(Helpers::csrfToken()) ?>
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= Helpers::asset('js/admin.js') ?>"></script>
</body>
</html>
