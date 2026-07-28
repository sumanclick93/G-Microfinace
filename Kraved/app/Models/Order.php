<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

final class Order extends Model
{
    protected string $table = 'orders';

    public function create(array $data): int
    {
        return $this->store->insert([
            'order_number' => $data['order_number'],
            'customer_id' => $data['customer_id'] ?? null,
            'fulfillment_type' => $data['fulfillment_type'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'delivery_address' => $data['delivery_address'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'subtotal' => $data['subtotal'],
            'delivery_fee' => $data['delivery_fee'],
            'total_amount' => $data['total_amount'],
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_method' => $data['payment_method'] ?? 'cod',
            'order_status' => $data['order_status'] ?? 'received',
            'delivery_time_slot' => $data['delivery_time_slot'] ?? 'ASAP',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status_updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function addItem(int $orderId, array $item): void
    {
        JsonStore::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $item['product_id'] ?? null,
            'product_name' => $item['product_name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'addons_json' => $item['addons_json'] ?? null,
            'total_item_price' => $item['total_item_price'],
        ]);
    }

    public function findByNumber(string $number): ?array
    {
        return $this->store->first(
            static fn(array $r): bool => ($r['order_number'] ?? '') === $number
        );
    }

    public function items(int $orderId): array
    {
        return JsonStore::table('order_items')->filter(
            static fn(array $r): bool => (int) ($r['order_id'] ?? 0) === $orderId
        );
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->store->update($id, [
            'order_status' => $status,
            'status_updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recent(int $limit = 50): array
    {
        $rows = $this->store->all();
        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_slice($rows, 0, $limit);
    }

    public function pendingCount(): int
    {
        $pending = ['received', 'baking', 'ready', 'out_for_delivery'];
        return count($this->store->filter(
            static fn(array $r): bool => in_array((string) ($r['order_status'] ?? ''), $pending, true)
        ));
    }

    public function todayStats(): array
    {
        $today = date('Y-m-d');
        $sales = 0.0;
        $count = 0;
        foreach ($this->store->all() as $o) {
            $created = substr((string) ($o['created_at'] ?? ''), 0, 10);
            if ($created !== $today) {
                continue;
            }
            $count++;
            if (($o['order_status'] ?? '') !== 'cancelled') {
                $sales += (float) ($o['total_amount'] ?? 0);
            }
        }
        return [
            'sales_today'  => $sales,
            'orders_today' => $count,
            'pending'      => $this->pendingCount(),
        ];
    }

    public function latestId(): int
    {
        return $this->store->maxId();
    }

    public function sinceId(int $afterId): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['id'] ?? 0) > $afterId
        );
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
        return $rows;
    }
}
