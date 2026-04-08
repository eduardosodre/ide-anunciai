<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ChatRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findBySolicitanteAndEntity(int $solicitanteId, string $tipoEntidade, int $entidadeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM conversa
             WHERE solicitante_id = :s AND tipo_entidade = :t AND entidade_id = :e
             LIMIT 1'
        );
        $stmt->execute(['s' => $solicitanteId, 't' => $tipoEntidade, 'e' => $entidadeId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $conversaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM conversa WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $conversaId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function userParticipates(int $conversaId, int $userId): bool
    {
        $row = $this->findById($conversaId);
        if ($row === null) {
            return false;
        }

        return (int) $row['solicitante_id'] === $userId || (int) $row['destinatario_usuario_id'] === $userId;
    }

    public function create(
        int $solicitanteId,
        int $destinatarioUsuarioId,
        string $tipoEntidade,
        int $entidadeId
    ): int {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO conversa (solicitante_id, destinatario_usuario_id, tipo_entidade, entidade_id, criado_em, atualizado_em)
             VALUES (:s, :d, :t, :e, :c, :a)'
        );
        $stmt->execute([
            's' => $solicitanteId,
            'd' => $destinatarioUsuarioId,
            't' => $tipoEntidade,
            'e' => $entidadeId,
            'c' => $now,
            'a' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function touchUpdatedAt(int $conversaId): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE conversa SET atualizado_em = :a WHERE id = :id');
        $stmt->execute(['a' => $now, 'id' => $conversaId]);
    }

    public function markPrimeiroEmailEnviado(int $conversaId): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE conversa SET primeiro_email_enviado_em = :p WHERE id = :id AND primeiro_email_enviado_em IS NULL'
        );
        $stmt->execute(['p' => $now, 'id' => $conversaId]);
    }

    public function insertMessage(int $conversaId, int $remetenteId, string $corpo): int
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO mensagem (conversa_id, remetente_id, corpo, criado_em)
             VALUES (:c, :r, :corpo, :criado_em)'
        );
        $stmt->execute([
            'c' => $conversaId,
            'r' => $remetenteId,
            'corpo' => $corpo,
            'criado_em' => $now,
        ]);
        $this->touchUpdatedAt($conversaId);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listMessages(int $conversaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.remetente_id, m.corpo, m.criado_em, u.nome AS remetente_nome
             FROM mensagem m
             INNER JOIN usuario u ON u.id = m.remetente_id
             WHERE m.conversa_id = :c
             ORDER BY m.id ASC'
        );
        $stmt->execute(['c' => $conversaId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $sql = 'SELECT c.id, c.solicitante_id, c.destinatario_usuario_id, c.tipo_entidade, c.entidade_id,
                       c.criado_em, c.atualizado_em,
                       u1.nome AS solicitante_nome,
                       u2.nome AS destinatario_nome
                FROM conversa c
                INNER JOIN usuario u1 ON u1.id = c.solicitante_id
                INNER JOIN usuario u2 ON u2.id = c.destinatario_usuario_id
                WHERE c.solicitante_id = :uid OR c.destinatario_usuario_id = :uid
                ORDER BY c.atualizado_em DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll();
    }
}
