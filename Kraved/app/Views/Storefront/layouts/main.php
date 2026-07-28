<?php
use App\Core\AuthMiddleware;
use App\Core\Helpers;
$user = AuthMiddleware::user();
$fulfillment = $fulfillment ?? ['type' => 'collection', 'postcode' => '', 'time_slot' => 'ASAP'];
$cartCount = $cartCount ?? 0;
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::e(($title ?? 'Order') . ' · Kraved') ?></title>
  <link rel="icon" href="<?= Helpers::asset('images/logo.jpeg') ?>" type="image/jpeg">
  <link rel="apple-touch-icon" href="<?= Helpers::asset('images/logo.jpeg') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= Helpers::asset('css/store.css') ?>" rel="stylesheet">
</head>
<body>
<?php require dirname(__DIR__, 2) . '/Partials/header.php'; ?>

<main>
  <?= $content ?>
</main>

<?php require dirname(__DIR__, 2) . '/Partials/footer.php'; ?>
<?php require dirname(__DIR__, 2) . '/Partials/fulfillment-modal.php'; ?>
<?php require dirname(__DIR__, 2) . '/Partials/product-modal.php'; ?>
<?php require dirname(__DIR__, 2) . '/Partials/cart-drawer.php'; ?>

<script>
  window.KRAVED = {
    baseUrl: <?= json_encode(Helpers::baseUrl()) ?>,
    csrf: <?= json_encode(Helpers::csrfToken()) ?>,
    currency: <?= json_encode(Helpers::config('currency_symbol', '£')) ?>,
    freeDelivery: <?= (float) Helpers::config('free_delivery_threshold', 25) ?>,
    fulfillment: <?= json_encode($fulfillment) ?>
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= Helpers::asset('js/store.js') ?>"></script>
</body>
</html>
