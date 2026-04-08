<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countTodayPair(int $denuncianteId, int $denunciadoId): int
    {
        $sql = 'SELECT COUNT(*) FROM denuncia
                WHERE denunciante_id = :d1 AND denunciado_id = :d2
                  AND criado_em >= CURDATE()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['d1' => $denuncianteId, 'd2' => $denunciadoId]);

        return (int) $stmt->fetchColumn();
    }

    public function countTodayByDenunciante(int $denuncianteId): int
    {
        $sql = 'SELECT COUNT(*) FROM denuncia
                WHERE denunciante_id = :id AND criado_em >= CURDATE()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $denuncianteId]);

        return (int) $stmt->fetchColumn();
    }

    public function insert(int $denuncianteId, int $denunciadoId, string $descricao): int
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO denuncia (denunciante_id, denunciado_id, descricao, criado_em)
             VALUES (:d1, :d2, :descricao, :criado_em)'
        );
        $stmt->execute([
            'd1' => $denuncianteId,
            'd2' => $denunciadoId,
            'descricao' => $descricao,
            'criado_em' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listRecentForAdmin(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $sql = 'SELECT d.id, d.denunciante_id, d.denunciado_id, d.descricao, d.criado_em,
                       u1.nome AS denunciante_nome, u1.email AS denunciante_email,
                       u2.nome AS denunciado_nome, u2.email AS denunciado_email, u2.ativo AS denunciado_ativo
                FROM denuncia d
                INNER JOIN usuario u1 ON u1.id = d.denunciante_id
                INNER JOIN usuario u2 ON u2.id = d.denunciado_id
                ORDER BY d.id DESC
                LIMIT ' . $lim;
        $stmt = $this->pdo->query($sql);

        return $stmt === false ? [] : $stmt->fetchAll();
    }
}
