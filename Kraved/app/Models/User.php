<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\JsonStore;

final class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->store->first(
            static fn(array $r): bool => strcasecmp((string) ($r['email'] ?? ''), $email) === 0
        );
    }

    public function create(array $data): int
    {
        return $this->store->insert([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password_hash' => $data['password_hash'],
            'role' => $data['role'] ?? 'customer',
            'address' => $data['address'] ?? null,
            'postcode' => $data['postcode'] ?? null,
        ]);
    }
}
