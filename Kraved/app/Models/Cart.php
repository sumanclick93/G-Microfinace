<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Helpers;
use App\Core\Session;

/**
 * Session-based shopping cart.
 */
final class Cart
{
    private const KEY = 'cart';
    private const FULFILLMENT = 'fulfillment';

    public static function items(): array
    {
        return Session::get(self::KEY, []);
    }

    public static function count(): int
    {
        $n = 0;
        foreach (self::items() as $item) {
            $n += (int) ($item['quantity'] ?? 1);
        }
        return $n;
    }

    public static function subtotal(): float
    {
        $sum = 0.0;
        foreach (self::items() as $item) {
            $sum += (float) $item['line_total'];
        }
        return round($sum, 2);
    }

    public static function add(array $item): void
    {
        $cart = self::items();
        $key = $item['key'] ?? md5(json_encode($item) ?: uniqid('', true));
        $item['key'] = $key;

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += (int) $item['quantity'];
            $cart[$key]['line_total'] = round(
                (float) $cart[$key]['unit_price'] * (int) $cart[$key]['quantity'],
                2
            );
        } else {
            $cart[$key] = $item;
        }

        Session::set(self::KEY, $cart);
    }

    public static function updateQty(string $key, int $qty): void
    {
        $cart = self::items();
        if (!isset($cart[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['quantity'] = $qty;
            $cart[$key]['line_total'] = round((float) $cart[$key]['unit_price'] * $qty, 2);
        }
        Session::set(self::KEY, $cart);
    }

    public static function remove(string $key): void
    {
        $cart = self::items();
        unset($cart[$key]);
        Session::set(self::KEY, $cart);
    }

    public static function clear(): void
    {
        Session::remove(self::KEY);
    }

    public static function setFulfillment(array $data): void
    {
        Session::set(self::FULFILLMENT, $data);
    }

    public static function fulfillment(): array
    {
        return Session::get(self::FULFILLMENT, [
            'type'       => 'collection',
            'postcode'   => '',
            'fee'        => 0,
            'min_order'  => 0,
            'time_slot'  => 'ASAP',
            'zone'       => null,
        ]);
    }

    public static function deliveryFee(): float
    {
        $f = self::fulfillment();
        if (($f['type'] ?? 'collection') !== 'delivery') {
            return 0.0;
        }
        $threshold = (float) Helpers::config('free_delivery_threshold', 25);
        if (self::subtotal() >= $threshold) {
            return 0.0;
        }
        return (float) ($f['fee'] ?? 0);
    }

    public static function total(): float
    {
        return round(self::subtotal() + self::deliveryFee(), 2);
    }

    public static function summary(): array
    {
        $threshold = (float) Helpers::config('free_delivery_threshold', 25);
        $sub = self::subtotal();
        return [
            'items'            => array_values(self::items()),
            'count'            => self::count(),
            'subtotal'         => $sub,
            'delivery_fee'     => self::deliveryFee(),
            'total'            => self::total(),
            'fulfillment'      => self::fulfillment(),
            'free_delivery_at' => $threshold,
            'free_delivery_remaining' => max(0, round($threshold - $sub, 2)),
        ];
    }
}
