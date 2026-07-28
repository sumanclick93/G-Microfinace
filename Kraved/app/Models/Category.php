<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

final class Category extends Model
{
    protected string $table = 'categories';

    public function activeOrdered(): array
    {
        $rows = $this->store->filter(static fn(array $r): bool => (int) ($r['status'] ?? 0) === 1);
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
            'name' => $data['name'],
            'slug' => $data['slug'],
            'image' => $data['image'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'image' => $data['image'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function updateOrder(array $ids): void
    {
        foreach ($ids as $order => $id) {
            $this->store->update((int) $id, ['display_order' => (int) $order + 1]);
        }
    }

    public function withSubCategories(): array
    {
        $cats = $this->activeOrdered();
        $subs = JsonStore::table('sub_categories')->all();
        foreach ($cats as &$cat) {
            $cid = (int) $cat['id'];
            $list = array_values(array_filter(
                $subs,
                static fn(array $s): bool => (int) ($s['category_id'] ?? 0) === $cid && (int) ($s['status'] ?? 0) === 1
            ));
            usort($list, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
            $cat['sub_categories'] = $list;
        }
        return $cats;
    }
}
