<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Helpers;
use App\Models\Cart;
use App\Models\Order;

final class OrderController extends Controller
{
    public function status(string $number): void
    {
        $order = (new Order())->findByNumber($number);
        if (!$order) {
            http_response_code(404);
            echo 'Order not found';
            return;
        }

        $this->view('Storefront/order/status', [
            'title'     => 'Order ' . $order['order_number'],
            'order'     => $order,
            'items'     => (new Order())->items((int) $order['id']),
            'cartCount' => Cart::count(),
            'fulfillment' => Cart::fulfillment(),
            'steps'     => $this->stepsFor($order),
        ], 'Storefront/layouts/main');
    }

    public function statusJson(string $number): void
    {
        $order = (new Order())->findByNumber($number);
        if (!$order) {
            $this->json(['error' => 'Not found'], 404);
        }
        $this->json([
            'order_status' => $order['order_status'],
            'label'        => Helpers::statusLabel($order['order_status']),
            'steps'        => $this->stepsFor($order),
            'updated_at'   => $order['status_updated_at'],
        ]);
    }

    private function stepsFor(array $order): array
    {
        $type = $order['fulfillment_type'];
        $flow = $type === 'delivery'
            ? ['received', 'baking', 'out_for_delivery', 'completed']
            : ['received', 'baking', 'ready', 'completed'];

        $current = $order['order_status'];
        if ($current === 'cancelled') {
            return array_map(fn ($s) => [
                'key' => $s,
                'label' => Helpers::statusLabel($s),
                'state' => 'cancelled',
            ], $flow);
        }

        $idx = array_search($current, $flow, true);
        // Map ready/out_for_delivery if somehow mismatched
        if ($idx === false && $current === 'ready') {
            $idx = array_search('ready', $flow, true);
        }
        if ($idx === false) {
            $idx = 0;
        }

        $steps = [];
        foreach ($flow as $i => $s) {
            $state = 'upcoming';
            if ($i < $idx) {
                $state = 'done';
            } elseif ($i === $idx) {
                $state = 'current';
            }
            $steps[] = [
                'key'   => $s,
                'label' => Helpers::statusLabel($s),
                'state' => $state,
            ];
        }
        return $steps;
    }
}
