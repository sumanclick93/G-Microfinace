<?php use App\Core\Helpers; ?>
<section class="section pt-5">
  <div class="container" style="max-width:420px">
    <h1 class="section-title">Create Account</h1>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= Helpers::e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= Helpers::baseUrl('register') ?>">
      <?= Helpers::csrfField() ?>
      <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
      <button class="btn btn-accent w-100">Register</button>
    </form>
  </div>
</section>
