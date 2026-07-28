<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

final class AddonGroup extends Model
{
    protected string $table = 'addon_groups';

    public function allWithAddons(): array
    {
        $groups = $this->store->filter(static fn(array $r): bool => (int) ($r['status'] ?? 0) === 1);
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

    public function adminAll(): array
    {
        $rows = $this->store->all();
        usort($rows, static fn(array $a, array $b): int => ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0)));
        return $rows;
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'title' => $data['title'],
            'min_selection' => $data['min_selection'] ?? 0,
            'max_selection' => $data['max_selection'] ?? 1,
            'is_required' => $data['is_required'] ?? 0,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->update($id, [
            'title' => $data['title'],
            'min_selection' => $data['min_selection'] ?? 0,
            'max_selection' => $data['max_selection'] ?? 1,
            'is_required' => $data['is_required'] ?? 0,
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 1,
        ]);
    }
}
