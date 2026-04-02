<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuario WHERE id = :id AND ativo = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByIdAnyStatus(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuario WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function deactivateById(int $id): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE usuario SET ativo = 0, atualizado_em = :atualizado_em WHERE id = :id');
        $stmt->execute(['atualizado_em' => $now, 'id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO usuario (nome, email, senha_hash, consentimento_em, versao_politica_aceita, criado_em, atualizado_em)
                VALUES (:nome, :email, :senha_hash, :consentimento_em, :versao_politica_aceita, :criado_em, :atualizado_em)';

        $now = $data['now'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nome' => $data['nome'],
            'email' => mb_strtolower(trim($data['email'])),
            'senha_hash' => $data['senha_hash'],
            'consentimento_em' => $now,
            'versao_politica_aceita' => $data['versao_politica_aceita'],
            'criado_em' => $now,
            'atualizado_em' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAccount(int $id, string $nome, string $email): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET nome = :nome, email = :email, atualizado_em = :atualizado_em WHERE id = :id AND ativo = 1'
        );
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt->execute([
            'nome' => $nome,
            'email' => mb_strtolower(trim($email)),
            'atualizado_em' => $now,
            'id' => $id,
        ]);
    }

    public function updateFotoUrl(int $id, ?string $fotoUrl): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuario SET foto_url = :foto_url, atualizado_em = :atualizado_em WHERE id = :id AND ativo = 1');
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt->execute([
            'foto_url' => $fotoUrl,
            'atualizado_em' => $now,
            'id' => $id,
        ]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET senha_hash = :senha_hash, atualizado_em = :atualizado_em, reset_token_hash = NULL, reset_expira_em = NULL WHERE id = :id'
        );
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt->execute([
            'senha_hash' => $hash,
            'atualizado_em' => $now,
            'id' => $id,
        ]);
    }

    public function setPasswordReset(int $userId, string $tokenHash, \DateTimeImmutable $expires, \DateTimeImmutable $requestedAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET reset_token_hash = :h, reset_expira_em = :e, ultimo_reset_solicitado_em = :u, atualizado_em = :a WHERE id = :id'
        );
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt->execute([
            'h' => $tokenHash,
            'e' => $expires->format('Y-m-d H:i:s'),
            'u' => $requestedAt->format('Y-m-d H:i:s'),
            'a' => $now,
            'id' => $userId,
        ]);
    }

    public function findByResetTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM usuario WHERE reset_token_hash = :h AND reset_expira_em > NOW() AND ativo = 1 LIMIT 1'
        );
        $stmt->execute(['h' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function clearPasswordReset(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET reset_token_hash = NULL, reset_expira_em = NULL, atualizado_em = :a WHERE id = :id'
        );
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt->execute(['a' => $now, 'id' => $userId]);
    }

    public function isDuplicateEmail(PDOException $e): bool
    {
        $code = (string) $e->getCode();

        return $code === '23000' || str_contains($e->getMessage(), 'Duplicate');
    }
}
