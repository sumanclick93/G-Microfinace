<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Legacy stub — Kraved now uses JsonStore (no MySQL).
 */
final class Database
{
    public static function getInstance(): mixed
    {
        throw new \RuntimeException('MySQL is disabled. Kraved uses JSON storage via App\\Core\\JsonStore.');
    }
}
