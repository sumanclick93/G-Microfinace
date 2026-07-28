<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $path, array $data = [], ?string $layout = null): void
    {
        Helpers::view($path, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        Helpers::redirect($path);
    }

    protected function json(mixed $data, int $code = 200): void
    {
        Helpers::json($data, $code);
    }
}
