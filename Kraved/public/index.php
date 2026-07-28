<?php

declare(strict_types=1);

use App\Core\Helpers;
use App\Core\Session;

// Show errors while debugging on hosting (set debug=false in config/app.php later)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    exit('Kraved requires PHP 8.1 or newer. This server is running PHP ' . PHP_VERSION);
}

// Autoload App\* classes before anything else uses them
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

try {
    $config = Helpers::config();
    date_default_timezone_set($config['timezone'] ?? 'Europe/London');

    Session::start($config['session_name'] ?? 'kraved_session');

    // File-based JSON storage (no MySQL required)
    \App\Core\JsonSeeder::ensure();

    $router = require dirname(__DIR__) . '/app/routes.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // Support subdirectory installs: strip base path from APP_URL path
    $basePath = parse_url((string) $config['url'], PHP_URL_PATH) ?: '';
    $basePath = rtrim($basePath, '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath)) ?: '/';
        $uri = $path . (str_contains($uri, '?') ? '?' . (parse_url($uri, PHP_URL_QUERY) ?? '') : '');
    }

    $router->dispatch($method, $uri);
} catch (Throwable $e) {
    http_response_code(500);
    if (!empty($config['debug']) || true) {
        echo '<h1>Kraved Error</h1>';
        echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
        echo '<pre>' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . "\n\n";
        echo htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo 'Something went wrong.';
    }
}
