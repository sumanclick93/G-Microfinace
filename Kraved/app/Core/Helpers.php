<?php

declare(strict_types=1);

namespace App\Core;

final class Helpers
{
    private static ?array $appConfig = null;

    public static function config(string $key = null, mixed $default = null): mixed
    {
        if (self::$appConfig === null) {
            self::$appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        }
        if ($key === null) {
            return self::$appConfig;
        }
        return self::$appConfig[$key] ?? $default;
    }

    public static function baseUrl(string $path = ''): string
    {
        $base = rtrim((string) self::config('url'), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }

    public static function asset(string $path): string
    {
        return self::baseUrl('assets/' . ltrim($path, '/'));
    }

    public static function upload(string $path): string
    {
        return self::baseUrl('uploads/' . ltrim($path, '/'));
    }

    public static function redirect(string $path): void
    {
        $url = str_starts_with($path, 'http') ? $path : self::baseUrl(ltrim($path, '/'));
        header('Location: ' . $url);
        exit;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function money(float|string|null $amount): string
    {
        $sym = (string) self::config('currency_symbol', '£');
        return $sym . number_format((float) $amount, 2);
    }

    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'item';
    }

    public static function csrfToken(): string
    {
        if (!Session::has('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('_csrf');
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        $session = Session::get('_csrf');
        return is_string($token) && is_string($session) && hash_equals($session, $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function view(string $path, array $data = [], ?string $layout = null): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $path) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            exit('View not found: ' . $path);
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout) {
            $layoutFile = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $layout) . '.php';
            require $layoutFile;
            return;
        }

        echo $content;
    }

    public static function orderNumber(): string
    {
        $prefix = (string) self::config('order_prefix', 'KRV');
        return $prefix . strtoupper(bin2hex(random_bytes(3))) . date('His');
    }

    public static function postcodePrefix(string $postcode): string
    {
        $pc = strtoupper(preg_replace('/\s+/', '', $postcode) ?? '');
        if (preg_match('/^([A-Z]{1,2}\d{1,2}[A-Z]?)/', $pc, $m)) {
            // Prefer longer known prefixes later; return outward code without inward
            $outward = preg_replace('/\d[A-Z]{2}$/', '', $pc) ?? $pc;
            // Extract letter+digit prefix (e.g. E1, SW1, EC1, W1)
            if (preg_match('/^([A-Z]{1,2}\d{1,2})/', $outward, $m2)) {
                return $m2[1];
            }
            return $m[1];
        }
        return substr($pc, 0, 3);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'received'         => 'Order Received',
            'baking'           => 'In the Oven',
            'ready'            => 'Ready for Pickup',
            'out_for_delivery' => 'Out for Delivery',
            'completed'        => 'Completed',
            'cancelled'        => 'Cancelled',
            default            => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
