<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

final class Product extends Model
{
    protected string $table = 'products';

    public function active(): array
    {
        $cats = [];
        foreach (JsonStore::table('categories')->all() as $c) {
            $cats[(int) $c['id']] = $c['name'] ?? '';
        }
        $subs = [];
        foreach (JsonStore::table('sub_categories')->all() as $s) {
            $subs[(int) $s['id']] = $s['name'] ?? '';
        }

        $rows = $this->store->filter(static fn(array $r): bool => (int) ($r['status'] ?? 0) === 1);
        foreach ($rows as &$p) {
            $p['category_name'] = $cats[(int) ($p['category_id'] ?? 0)] ?? '';
            $sid = $p['sub_category_id'] ?? null;
            $p['sub_category_name'] = $sid ? ($subs[(int) $sid] ?? null) : null;
        }
        usort($rows, static function (array $a, array $b): int {
            $cmp = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
            return $cmp !== 0 ? $cmp : strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });
        return $rows;
    }

    public function featured(int $limit = 8): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['status'] ?? 0) === 1 && (int) ($r['is_featured'] ?? 0) === 1
        );
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
        return array_slice($rows, 0, $limit);
    }

    public function byCategory(int $categoryId): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['category_id'] ?? 0) === $categoryId && (int) ($r['status'] ?? 0) === 1
        );
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
        return $rows;
    }

    public function bySubCategory(int $subCategoryId): array
    {
        $rows = $this->store->filter(
            static fn(array $r): bool => (int) ($r['sub_category_id'] ?? 0) === $subCategoryId && (int) ($r['status'] ?? 0) === 1
        );
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
        return $rows;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->store->first(static fn(array $r): bool => ($r['slug'] ?? '') === $slug);
    }

    public function withAddonGroups(int $productId): array
    {
        $links = JsonStore::table('product_addon_groups')->filter(
            static fn(array $r): bool => (int) ($r['product_id'] ?? 0) === $productId
        );
        $groupIds = array_map(static fn(array $r): int => (int) $r['group_id'], $links);

        $groups = JsonStore::table('addon_groups')->filter(
            static fn(array $g): bool => in_array((int) ($g['id'] ?? 0), $groupIds, true) && (int) ($g['status'] ?? 0) === 1
        );
        usort($groups, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));

        $addons = JsonStore::table('addons')->all();
        foreach ($groups as &$g) {
            $gid = (int) $g['id'];
            $list = array_values(array_filter(
                $addons,
                static fn(array $a): bool => (int) ($a['group_id'] ?? 0) === $gid && (int) ($a['status'] ?? 0) === 1
            ));
            usort($list, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
            $g['addons'] = $list;
        }
        return $groups;
    }

    public function boxChoices(int $boxProductId): array
    {
        $links = JsonStore::table('box_deal_products')->filter(
            static fn(array $r): bool => (int) ($r['box_product_id'] ?? 0) === $boxProductId
        );
        $choiceIds = array_map(static fn(array $r): int => (int) $r['choice_product_id'], $links);
        $rows = $this->store->filter(
            static fn(array $p): bool => in_array((int) ($p['id'] ?? 0), $choiceIds, true) && (int) ($p['status'] ?? 0) === 1
        );
        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));
        return $rows;
    }

    public function adminAll(): array
    {
        $cats = [];
        foreach (JsonStore::table('categories')->all() as $c) {
            $cats[(int) $c['id']] = $c['name'] ?? '';
        }
        $rows = $this->store->all();
        foreach ($rows as &$p) {
            $p['category_name'] = $cats[(int) ($p['category_id'] ?? 0)] ?? '';
        }
        usort($rows, static fn(array $a, array $b): int => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));
        return $rows;
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'category_id' => $data['category_id'],
            'sub_category_id' => $data['sub_category_id'] ?: null,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'] ?? null,
            'full_description' => $data['full_description'] ?? null,
            'base_price' => (float) $data['base_price'],
            'stock_qty' => $data['stock_qty'] ?? 0,
            'weight_label' => $data['weight_label'] ?? null,
            'is_featured' => $data['is_featured'] ?? 0,
            'is_box_deal' => $data['is_box_deal'] ?? 0,
            'box_max_items' => $data['box_max_items'] ?? null,
            'image' => $data['image'] ?? null,
            'status' => $data['status'] ?? 1,
            'display_order' => $data['display_order'] ?? 0,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'category_id' => $data['category_id'],
            'sub_category_id' => $data['sub_category_id'] ?: null,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'] ?? null,
            'full_description' => $data['full_description'] ?? null,
            'base_price' => (float) $data['base_price'],
            'stock_qty' => $data['stock_qty'] ?? 0,
            'weight_label' => $data['weight_label'] ?? null,
            'is_featured' => $data['is_featured'] ?? 0,
            'is_box_deal' => $data['is_box_deal'] ?? 0,
            'box_max_items' => $data['box_max_items'] ?? null,
            'image' => $data['image'] ?? null,
            'status' => $data['status'] ?? 1,
            'display_order' => $data['display_order'] ?? 0,
        ]);
    }

    public function syncAddonGroups(int $productId, array $groupIds): void
    {
        $store = JsonStore::table('product_addon_groups');
        $rows = $store->filter(static fn(array $r): bool => (int) ($r['product_id'] ?? 0) !== $productId);
        foreach ($groupIds as $gid) {
            $rows[] = ['product_id' => $productId, 'group_id' => (int) $gid];
        }
        $store->replaceAll($rows);
    }

    public function syncBoxChoices(int $boxId, array $choiceIds): void
    {
        $store = JsonStore::table('box_deal_products');
        $rows = $store->filter(static fn(array $r): bool => (int) ($r['box_product_id'] ?? 0) !== $boxId);
        foreach ($choiceIds as $cid) {
            $rows[] = ['box_product_id' => $boxId, 'choice_product_id' => (int) $cid];
        }
        $store->replaceAll($rows);
    }

    public function addonGroupIds(int $productId): array
    {
        $rows = JsonStore::table('product_addon_groups')->filter(
            static fn(array $r): bool => (int) ($r['product_id'] ?? 0) === $productId
        );
        return array_map(static fn(array $r): int => (int) $r['group_id'], $rows);
    }

    public function boxChoiceIds(int $boxId): array
    {
        $rows = JsonStore::table('box_deal_products')->filter(
            static fn(array $r): bool => (int) ($r['box_product_id'] ?? 0) === $boxId
        );
        return array_map(static fn(array $r): int => (int) $r['choice_product_id'], $rows);
    }
}
