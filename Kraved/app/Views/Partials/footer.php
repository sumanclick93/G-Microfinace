<?php use App\Core\Helpers; ?>
<footer class="site-footer">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-md-6">
        <div class="brand footer-brand">
          <img src="<?= Helpers::asset('images/logo.jpeg') ?>" alt="Kraved" class="brand-logo brand-logo-footer">
        </div>
        <p class="mb-0 opacity-75">Freshly baked cookies & homemade treats — order for collection or delivery.</p>
      </div>
      <div class="col-md-3">
        <h3 class="h6 text-uppercase letter-spaced">Visit</h3>
        <p class="small opacity-75 mb-0">12 Baker Street<br>London W1U 3BW</p>
      </div>
      <div class="col-md-3">
        <h3 class="h6 text-uppercase letter-spaced">Order</h3>
        <p class="small opacity-75 mb-0">Daily 11:00 – 22:00<br>admin@kraved.local</p>
      </div>
    </div>
    <p class="small opacity-50 mt-4 mb-0">&copy; <?= date('Y') ?> Kraved. Homemade with care.</p>
  </div>
</footer>
