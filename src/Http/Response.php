<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        private string $content,
        private int $statusCode = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $content, int $statusCode = 200): self
    {
        return new self($content, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array $payload, int $statusCode = 200): self
    {
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($content === false ? '{}' : $content, $statusCode, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->content !== '') {
            echo $this->content;
        }
    }
}
