<?php

declare(strict_types=1);

namespace App\Config;

final class AppConfig
{
    public function __construct(
        private readonly array $database,
        private readonly string $baseUrl,
        private readonly string $policyVersion
    ) {
    }

    public static function load(string $projectRoot): self
    {
        $dbFile = $projectRoot . '/database.php';
        if (!is_file($dbFile)) {
            throw new \RuntimeException('Arquivo database.php não encontrado. Copie database.php.example para database.php.');
        }

        /** @var array $db */
        $db = require $dbFile;

        $baseUrl = rtrim((string) ($db['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = self::detectBaseUrl();
        }

        $policyVersion = (string) ($db['policy_version'] ?? '1.0');

        return new self($db, $baseUrl, $policyVersion);
    }

    public function database(): array
    {
        return $this->database;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function policyVersion(): string
    {
        return $this->policyVersion;
    }

    private static function detectBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
