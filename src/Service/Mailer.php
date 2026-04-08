<?php

declare(strict_types=1);

namespace App\Service;

final class Mailer
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $logDir = $this->projectRoot . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] TO=%s SUBJECT=%s\n%s\n---\n",
            (new \DateTimeImmutable('now'))->format('c'),
            $to,
            $subject,
            $body
        );
        file_put_contents($logDir . '/mail.log', $line, FILE_APPEND | LOCK_EX);

        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        return @mail($to, $subject, $body, $headers);
    }
}
