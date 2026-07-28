<?php

declare(strict_types=1);

namespace App\Models;

final class Addon extends Model
{
    protected string $table = 'addons';

    public function byGroup(int $groupId): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['group_id'] ?? 0) === $groupId
        );
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
        return $rows;
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'group_id' => $data['group_id'],
            'name' => $data['name'],
            'price' => $data['price'] ?? 0,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'group_id' => $data['group_id'],
            'name' => $data['name'],
            'price' => $data['price'] ?? 0,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ids = array_map('intval', $ids);
        return $this->store->filter(
            static fn(array $r): bool => in_array((int) ($r['id'] ?? 0), $ids, true) && (int) ($r['status'] ?? 0) === 1
        );
    }
}
