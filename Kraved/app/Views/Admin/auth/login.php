<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — Kraved</title>
  <link rel="icon" href="<?= Helpers::asset('images/logo.jpeg') ?>" type="image/jpeg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= Helpers::asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body class="admin-login-page d-flex align-items-center justify-content-center min-vh-100">
  <div class="card shadow login-card p-4">
    <div class="text-center mb-3">
      <img src="<?= Helpers::asset('images/logo.jpeg') ?>" alt="Kraved" class="admin-login-logo">
    </div>
    <p class="text-muted text-center mb-4">Admin Panel</p>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= Helpers::e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= Helpers::baseUrl('admin/login') ?>">
      <?= Helpers::csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required value="admin@kraved.local">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-warning w-100" type="submit">Sign in</button>
    </form>
  </div>
</body>
</html>
