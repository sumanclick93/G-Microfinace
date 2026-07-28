<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Helpers;
use App\Models\Addon;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Product;

final class CartController extends Controller
{
    public function summary(): void
    {
        $this->json(Cart::summary());
    }

    public function add(): void
    {
        Helpers::requireCsrf();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $productId = (int) ($input['product_id'] ?? 0);
        $qty = max(1, (int) ($input['quantity'] ?? 1));
        $product = (new Product())->find($productId);
        if (!$product) {
            $this->json(['error' => 'Product not found'], 404);
        }

        $addonIds = array_map('intval', $input['addon_ids'] ?? []);
        $addons = (new Addon())->findMany($addonIds);
        $addonTotal = 0.0;
        $addonLabels = [];
        foreach ($addons as $a) {
            $addonTotal += (float) $a['price'];
            $addonLabels[] = [
                'id'    => (int) $a['id'],
                'name'  => $a['name'],
                'price' => (float) $a['price'],
            ];
        }

        $boxPicks = [];
        if ((int) $product['is_box_deal']) {
            $picks = $input['box_picks'] ?? [];
            $max = (int) ($product['box_max_items'] ?? 4);
            if (!is_array($picks) || count($picks) !== $max) {
                $this->json(['error' => "Please select exactly {$max} cookies."], 422);
            }
            $productModel = new Product();
            foreach ($picks as $pid) {
                $p = $productModel->find((int) $pid);
                if ($p) {
                    $boxPicks[] = ['id' => (int) $p['id'], 'name' => $p['title']];
                }
            }
        }

        $unit = (float) $product['base_price'] + $addonTotal;
        $item = [
            'product_id'   => (int) $product['id'],
            'name'         => $product['title'],
            'image'        => $product['image'],
            'quantity'     => $qty,
            'unit_price'   => round($unit, 2),
            'line_total'   => round($unit * $qty, 2),
            'addons'       => $addonLabels,
            'box_picks'    => $boxPicks,
            'is_box_deal'  => (int) $product['is_box_deal'],
        ];
        $item['key'] = md5(json_encode([
            $item['product_id'],
            $addonIds,
            array_column($boxPicks, 'id'),
        ]) ?: uniqid('', true));

        Cart::add($item);
        $this->json(['ok' => true, 'cart' => Cart::summary()]);
    }

    public function update(): void
    {
        Helpers::requireCsrf();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
        $key = (string) ($input['key'] ?? '');
        $qty = (int) ($input['quantity'] ?? 1);
        Cart::updateQty($key, $qty);
        $this->json(['ok' => true, 'cart' => Cart::summary()]);
    }

    public function remove(): void
    {
        Helpers::requireCsrf();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
        Cart::remove((string) ($input['key'] ?? ''));
        $this->json(['ok' => true, 'cart' => Cart::summary()]);
    }

    public function setFulfillment(): void
    {
        Helpers::requireCsrf();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
        $type = ($input['type'] ?? 'collection') === 'delivery' ? 'delivery' : 'collection';
        $postcode = strtoupper(trim((string) ($input['postcode'] ?? '')));
        $timeSlot = trim((string) ($input['time_slot'] ?? 'ASAP'));

        $fee = 0.0;
        $minOrder = 0.0;
        $zone = null;

        if ($type === 'delivery') {
            if ($postcode === '') {
                $this->json(['error' => 'Please enter a postcode.'], 422);
            }
            $prefix = Helpers::postcodePrefix($postcode);
            $zone = (new DeliveryZone())->findByPrefix($prefix);
            if (!$zone) {
                $this->json([
                    'ok' => false,
                    'available' => false,
                    'message' => "Sorry, we don't deliver to {$postcode} yet.",
                ], 422);
            }
            $fee = (float) $zone['delivery_fee'];
            $minOrder = (float) $zone['min_order_amount'];
        }

        Cart::setFulfillment([
            'type'      => $type,
            'postcode'  => $postcode,
            'fee'       => $fee,
            'min_order' => $minOrder,
            'time_slot' => $timeSlot ?: 'ASAP',
            'zone'      => $zone,
        ]);

        $this->json([
            'ok' => true,
            'available' => true,
            'fulfillment' => Cart::fulfillment(),
            'cart' => Cart::summary(),
            'message' => $type === 'delivery'
                ? 'Delivery available — fee ' . Helpers::money($fee)
                : 'Collection selected',
        ]);
    }
}
