<?php

namespace App\Core;

class Router
{
    private static array $routes = [];
    private static array $middlewareGroups = [];

    public static function get(string $path, callable $handler, array $middleware = []): void
    {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    public static function post(string $path, callable $handler, array $middleware = []): void
    {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    public static function put(string $path, callable $handler, array $middleware = []): void
    {
        self::addRoute('PUT', $path, $handler, $middleware);
    }

    public static function delete(string $path, callable $handler, array $middleware = []): void
    {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }

    public static function group(array $middleware, callable $callback): void
    {
        self::$middlewareGroups[] = $middleware;
        $callback();
        array_pop(self::$middlewareGroups);
    }

    private static function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $groupMiddleware = !empty(self::$middlewareGroups) ? array_merge(...self::$middlewareGroups) : [];
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware)
        ];
    }

    public static function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = Config::getInstance()->get('app.base_path', '');

        if (str_starts_with($requestUri, '/index.php/')) {
            $requestUri = '/' . ltrim(substr($requestUri, strlen('/index.php')), '/');
        } elseif ($requestUri === '/index.php') {
            $requestUri = '/';
        }

        if ($basePath && str_starts_with($requestUri, $basePath)) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        $requestUri = '/' . trim($requestUri, '/');

        foreach (self::$routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = self::convertToRegex($route['path']);
            if (preg_match($pattern, $requestUri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $mw) {
                    $middlewareClass = new $mw();
                    $result = $middlewareClass->handle();
                    if ($result === false) {
                        return;
                    }
                }

                $request = new Request($params);
                call_user_func($route['handler'], $request);
                return;
            }
        }

        Response::json(['error' => 'Route not found'], 404);
    }

    private static function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public static function getRoutes(): array
    {
        return self::$routes;
    }
}
