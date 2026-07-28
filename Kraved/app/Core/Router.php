<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable|array>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        // Strip /public if present when running under subdirectory
        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7) ?: '/';
        }

        $handler = $this->routes[$method][$path] ?? null;
        $params  = [];

        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $route => $h) {
                $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '([^/]+)', $route);
                $pattern = '#^' . $pattern . '$#';
                if (preg_match($pattern, $path, $matches)) {
                    array_shift($matches);
                    preg_match_all('#\{([a-zA-Z_]+)\}#', $route, $keys);
                    $params  = array_combine($keys[1], $matches) ?: [];
                    $handler = $h;
                    break;
                }
            }
        }

        if ($handler === null) {
            http_response_code(404);
            echo '404 — Page not found';
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action(...array_values($params));
            return;
        }

        $handler(...array_values($params));
    }
}
