<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

abstract class Model
{
    protected JsonStore $store;
    protected string $table = '';

    public function __construct()
    {
        $this->store = JsonStore::table($this->table);
    }

    public function find(int $id): ?array
    {
        return $this->store->find($id);
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        $rows = $this->store->all();
        return $this->sortRows($rows, $orderBy);
    }

    public function delete(int $id): bool
    {
        return $this->store->delete($id);
    }

    /** @param list<array<string,mixed>> $rows */
    protected function sortRows(array $rows, string $orderBy): array
    {
        $parts = preg_split('/\s+/', trim($orderBy)) ?: ['id', 'DESC'];
        $field = $parts[0] ?? 'id';
        $dir = strtoupper($parts[1] ?? 'ASC');
        usort($rows, static function (array $a, array $b) use ($field, $dir): int {
            $av = $a[$field] ?? null;
            $bv = $b[$field] ?? null;
            if (is_numeric($av) && is_numeric($bv)) {
                $cmp = $av <=> $bv;
            } else {
                $cmp = strcmp((string) $av, (string) $bv);
            }
            return $dir === 'DESC' ? -$cmp : $cmp;
        });
        return $rows;
    }
}
