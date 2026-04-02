<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';
        $match = $this->match($method, $path);
        if ($match === null) {
            if (str_starts_with($path, '/api')) {
                return Response::json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Route not found',
                    ],
                ], 404);
            }

            return Response::html('<h1>404</h1><p>Página não encontrada.</p>', 404);
        }

        $handler = $match['handler'];
        $response = $handler($request->withRouteParams($match['params']));

        return $response instanceof Response
            ? $response
            : Response::html((string) $response);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $params = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $normalizedPath
        );
        $regex = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
        ];
    }

    private function match(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $param) {
                $params[$param] = $matches[$param] ?? null;
            }

            return [
                'handler' => $route['handler'],
                'params' => $params,
            ];
        }

        return null;
    }
}
