<?php use App\Core\Helpers; ?>
<section class="section pt-4">
  <div class="container">
    <h1 class="section-title"><?= Helpers::e($category['name']) ?></h1>
    <div class="product-grid mt-4">
      <?php if (!$products): ?>
        <p class="text-muted">No products in this category yet.</p>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
        <?php require dirname(__DIR__, 2) . '/Partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
