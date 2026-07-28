<?php

declare(strict_types=1);

namespace App\Models;

final class SubCategory extends Model
{
    protected string $table = 'sub_categories';

    public function byCategory(int $categoryId): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['category_id'] ?? 0) === $categoryId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
            return $cmp !== 0 ? $cmp : strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        return $rows;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->store->first(
            static fn(array $r): bool => ($r['slug'] ?? '') === $slug
        );
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }
}
