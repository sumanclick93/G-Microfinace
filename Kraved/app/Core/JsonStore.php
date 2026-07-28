<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple file-based JSON table store (no MySQL required).
 * Each table lives at storage/data/{table}.json
 */
final class JsonStore
{
    private string $table;
    private string $path;
    private static array $cache = [];

    private function __construct(string $table)
    {
        $this->table = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?: 'data';
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->path = $dir . DIRECTORY_SEPARATOR . $this->table . '.json';
        if (!is_file($this->path)) {
            $this->write(['auto_increment' => 1, 'rows' => []]);
        }
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    private function read(): array
    {
        if (isset(self::$cache[$this->table])) {
            return self::$cache[$this->table];
        }
        $raw = file_get_contents($this->path);
        $data = json_decode($raw ?: '{}', true);
        if (!is_array($data)) {
            $data = ['auto_increment' => 1, 'rows' => []];
        }
        $data['rows'] = array_values($data['rows'] ?? []);
        $data['auto_increment'] = (int) ($data['auto_increment'] ?? 1);
        self::$cache[$this->table] = $data;
        return $data;
    }

    private function write(array $data): void
    {
        $data['rows'] = array_values($data['rows'] ?? []);
        self::$cache[$this->table] = $data;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path, $json === false ? '{"auto_increment":1,"rows":[]}' : $json, LOCK_EX);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->read()['rows'];
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param callable(array):bool $predicate
     * @return list<array<string,mixed>>
     */
    public function filter(callable $predicate): array
    {
        return array_values(array_filter($this->all(), $predicate));
    }

    public function first(callable $predicate): ?array
    {
        foreach ($this->all() as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }
        return null;
    }

    public function insert(array $row, bool $withId = true): int
    {
        $data = $this->read();
        $id = 0;
        if ($withId) {
            $id = (int) $data['auto_increment'];
            $row['id'] = $id;
            $data['auto_increment'] = $id + 1;
        }
        if (!isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        $data['rows'][] = $row;
        $this->write($data);
        return $id;
    }

    public function update(int $id, array $changes): bool
    {
        $data = $this->read();
        $found = false;
        foreach ($data['rows'] as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $changes['updated_at'] = date('Y-m-d H:i:s');
                $data['rows'][$i] = array_merge($row, $changes);
                $data['rows'][$i]['id'] = $id;
                $found = true;
                break;
            }
        }
        if ($found) {
            $this->write($data);
        }
        return $found;
    }

    public function delete(int $id): bool
    {
        $data = $this->read();
        $before = count($data['rows']);
        $data['rows'] = array_values(array_filter(
            $data['rows'],
            static fn(array $r): bool => (int) ($r['id'] ?? 0) !== $id
        ));
        if (count($data['rows']) === $before) {
            return false;
        }
        $this->write($data);
        return true;
    }

    /** Replace entire rows array (for junction tables). */
    public function replaceAll(array $rows, ?int $autoIncrement = null): void
    {
        $data = $this->read();
        $data['rows'] = array_values($rows);
        if ($autoIncrement !== null) {
            $data['auto_increment'] = $autoIncrement;
        }
        $this->write($data);
    }

    public function maxId(): int
    {
        $max = 0;
        foreach ($this->all() as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }
        return $max;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
