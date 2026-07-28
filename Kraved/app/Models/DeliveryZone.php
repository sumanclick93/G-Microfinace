<?php

declare(strict_types=1);

namespace App\Models;

final class DeliveryZone extends Model
{
    protected string $table = 'delivery_zones';

    public function findByPrefix(string $prefix): ?array
    {
        $prefix = strtoupper(trim($prefix));
        $row = $this->store->first(
            static fn(array $r): bool => strtoupper((string) ($r['postcode_prefix'] ?? '')) === $prefix
                && (int) ($r['status'] ?? 0) === 1
        );
        if ($row) {
            return $row;
        }

        for ($len = strlen($prefix) - 1; $len >= 2; $len--) {
            $try = substr($prefix, 0, $len);
            $row = $this->store->first(
                static fn(array $r): bool => strtoupper((string) ($r['postcode_prefix'] ?? '')) === $try
                    && (int) ($r['status'] ?? 0) === 1
            );
            if ($row) {
                return $row;
            }
        }
        return null;
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'postcode_prefix' => strtoupper($data['postcode_prefix']),
            'delivery_fee' => $data['delivery_fee'],
            'min_order_amount' => $data['min_order_amount'],
            'estimated_mins' => $data['estimated_mins'] ?? 45,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'postcode_prefix' => strtoupper($data['postcode_prefix']),
            'delivery_fee' => $data['delivery_fee'],
            'min_order_amount' => $data['min_order_amount'],
            'estimated_mins' => $data['estimated_mins'] ?? 45,
            'status' => $data['status'] ?? 1,
        ]);
    }
}
