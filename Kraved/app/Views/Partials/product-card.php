<?php use App\Core\Helpers; ?>
<article class="product-card">
  <div class="pc-media" style="background-image:url('<?= $p['image'] ? Helpers::upload($p['image']) : Helpers::asset('images/cookie-placeholder.svg') ?>')"></div>
  <div class="pc-body">
    <?php if (!empty($p['weight_label'])): ?>
      <span class="pc-tag"><?= Helpers::e($p['weight_label']) ?></span>
    <?php endif; ?>
    <h3 class="pc-title"><?= Helpers::e($p['title']) ?></h3>
    <p class="pc-desc"><?= Helpers::e($p['short_description'] ?? '') ?></p>
    <div class="pc-row">
      <span class="pc-price"><?= Helpers::money($p['base_price']) ?></span>
      <button type="button" class="btn-add"
        data-product-id="<?= (int)$p['id'] ?>"
        data-has-custom="<?= ((int)($p['is_box_deal'] ?? 0) || true) ? '1' : '0' ?>">
        + ADD
      </button>
    </div>
  </div>
</article>
