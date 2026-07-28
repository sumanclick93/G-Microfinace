<?php use App\Core\Helpers; ?>
<section class="hero">
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <p class="hero-eyebrow">Homemade · Baked to order</p>
    <h1 class="brand-hero">
      <img src="<?= Helpers::asset('images/logo.jpeg') ?>" alt="Kraved" class="brand-logo brand-logo-hero">
    </h1>
    <p class="hero-lead">Warm stuffed cookies, deep-dish pies &amp; cookie milkshakes — delivered fresh across London.</p>
    <div class="hero-cta">
      <a href="#menu" class="btn btn-accent btn-lg">Order Now</a>
      <button type="button" class="btn btn-ghost btn-lg" data-bs-toggle="modal" data-bs-target="#fulfillmentModal">Set Delivery</button>
    </div>
  </div>
</section>

<section class="badges-strip">
  <div class="container d-flex flex-wrap justify-content-center gap-4 py-3">
    <span>Freshly Baked Daily</span>
    <span>Stuffed &amp; Loaded</span>
    <span>Box Deals from <?= Helpers::money(15) ?></span>
  </div>
</section>

<nav class="category-nav sticky-cat" id="cat-nav">
  <div class="container">
    <div class="cat-scroll">
      <?php foreach ($categories as $cat): ?>
        <a href="#cat-<?= Helpers::e($cat['slug']) ?>" class="cat-link"><?= Helpers::e($cat['name']) ?></a>
        <?php if (!empty($cat['sub_categories'])): ?>
          <div class="cat-dropdown">
            <?php foreach ($cat['sub_categories'] as $sub): ?>
              <a href="<?= Helpers::baseUrl('subcategory/' . $sub['slug']) ?>"><?= Helpers::e($sub['name']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<section class="section" id="featured">
  <div class="container">
    <h2 class="section-title">Featured Cookies</h2>
    <p class="section-lead">Our most-ordered warm bakes.</p>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?>
        <?php require dirname(__DIR__, 2) . '/Partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="menu">
  <div class="container">
    <?php foreach ($menu as $block):
      $cat = $block['category'];
      $products = $block['products'];
      if (!$products) continue;
    ?>
      <div class="category-block" id="cat-<?= Helpers::e($cat['slug']) ?>">
        <h2 class="section-title"><?= Helpers::e($cat['name']) ?></h2>
        <?php if (!empty($cat['sub_categories'])): ?>
          <div class="subcat-pills mb-3">
            <?php foreach ($cat['sub_categories'] as $sub): ?>
              <a href="<?= Helpers::baseUrl('subcategory/' . $sub['slug']) ?>" class="subcat-pill"><?= Helpers::e($sub['name']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="product-grid">
          <?php foreach ($products as $p): ?>
            <?php require dirname(__DIR__, 2) . '/Partials/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
