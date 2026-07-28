<?php use App\Core\Helpers; ?>
<section class="section pt-5">
  <div class="container" style="max-width:420px">
    <h1 class="section-title">Login</h1>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= Helpers::e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= Helpers::baseUrl('login') ?>">
      <?= Helpers::csrfField() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-accent w-100">Sign in</button>
    </form>
    <p class="mt-3 small">No account? <a href="<?= Helpers::baseUrl('register') ?>">Register</a></p>
  </div>
</section>
