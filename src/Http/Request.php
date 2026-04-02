<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $queryParams = [],
        private array $bodyParams = [],
        private array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = $path === false || $path === null ? '/' : $path;

        $bodyParams = $_POST;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            if (is_string($ct) && str_contains(strtolower($ct), 'application/json')) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $bodyParams = $decoded;
                    }
                }
            }
        }

        return new self($method, $path, $_GET, $bodyParams);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $default;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function withRouteParams(array $routeParams): self
    {
        return new self($this->method, $this->path, $this->queryParams, $this->bodyParams, $routeParams);
    }

    /** @return array<string, mixed> */
    public function allBody(): array
    {
        return $this->bodyParams;
    }
}
