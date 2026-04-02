<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ChurchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM igreja WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute(['usuario_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findPublicById(int $churchId): ?array
    {
        $sql = 'SELECT i.id, i.usuario_id, i.nome_igreja, i.cidade, i.verificado, i.pendente_revisao,
                       i.public_nome_igreja_aprovado, i.public_cidade_aprovado
                FROM igreja i
                INNER JOIN usuario u ON u.id = i.usuario_id
                WHERE i.id = :id AND u.ativo = 1
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $churchId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $useApprovedSnapshot = (int) $row['verificado'] === 1
            && (int) $row['pendente_revisao'] === 1
            && $row['public_nome_igreja_aprovado'] !== null
            && $row['public_cidade_aprovado'] !== null;
        if ($useApprovedSnapshot) {
            $row['nome_igreja'] = (string) $row['public_nome_igreja_aprovado'];
            $row['cidade'] = (string) $row['public_cidade_aprovado'];
        }

        return $row;
    }

    public function findUsuarioIdForIgrejaPublic(int $igrejaId): ?int
    {
        $sql = 'SELECT i.usuario_id FROM igreja i
                INNER JOIN usuario u ON u.id = i.usuario_id
                WHERE i.id = :id AND u.ativo = 1
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $igrejaId]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['usuario_id'];
    }

    public function saveOwnProfile(
        int $userId,
        string $nomeIgreja,
        string $emailContato,
        ?string $telefone,
        string $cep,
        string $cidade,
        ?string $cnpjDigits
    ): int {
        $profile = $this->findByUserId($userId);
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            if ($profile === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO igreja (usuario_id, nome_igreja, email_contato, telefone, cep, cidade, cnpj, verificado, pendente_revisao, criado_em, atualizado_em)
                     VALUES (:usuario_id, :nome_igreja, :email_contato, :telefone, :cep, :cidade, :cnpj, 0, 0, :criado_em, :atualizado_em)'
                );
                $stmt->execute([
                    'usuario_id' => $userId,
                    'nome_igreja' => $nomeIgreja,
                    'email_contato' => $emailContato,
                    'telefone' => $telefone,
                    'cep' => $cep,
                    'cidade' => $cidade,
                    'cnpj' => $cnpjDigits,
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $id = (int) $profile['id'];
                $hasMeaningfulChange = $this->hasMeaningfulProfileChange(
                    $profile,
                    $nomeIgreja,
                    $emailContato,
                    $telefone,
                    $cep,
                    $cidade,
                    $cnpjDigits
                );
                $pendente = ((int) $profile['verificado'] === 1 && $hasMeaningfulChange) ? 1 : (int) $profile['pendente_revisao'];
                $stmt = $this->pdo->prepare(
                    'UPDATE igreja SET
                        nome_igreja = :nome_igreja,
                        email_contato = :email_contato,
                        telefone = :telefone,
                        cep = :cep,
                        cidade = :cidade,
                        cnpj = :cnpj,
                        pendente_revisao = :pendente_revisao,
                        atualizado_em = :atualizado_em
                     WHERE id = :id AND usuario_id = :usuario_id'
                );
                $stmt->execute([
                    'nome_igreja' => $nomeIgreja,
                    'email_contato' => $emailContato,
                    'telefone' => $telefone,
                    'cep' => $cep,
                    'cidade' => $cidade,
                    'cnpj' => $cnpjDigits,
                    'pendente_revisao' => $pendente,
                    'atualizado_em' => $now,
                    'id' => $id,
                    'usuario_id' => $userId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    public function updateCoordinatesByUserId(int $userId, ?float $latitude, ?float $longitude): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE igreja
             SET latitude = :latitude, longitude = :longitude, atualizado_em = :atualizado_em
             WHERE usuario_id = :usuario_id'
        );
        $stmt->execute([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'atualizado_em' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'usuario_id' => $userId,
        ]);
    }

    public function searchByRadius(float $latitude, float $longitude, int $raioKm, int $offset, int $limit): array
    {
        $distanceFormula = '(6371 * ACOS(
            COS(RADIANS(:lat)) * COS(RADIANS(i.latitude)) * COS(RADIANS(i.longitude) - RADIANS(:lng))
            + SIN(RADIANS(:lat)) * SIN(RADIANS(i.latitude))
        ))';
        $sql = 'SELECT i.id, i.usuario_id, i.nome_igreja, i.cidade, i.verificado, i.pendente_revisao,
                       i.public_nome_igreja_aprovado, i.public_cidade_aprovado,
                       ' . $distanceFormula . ' AS distancia_km
                FROM igreja i
                INNER JOIN usuario u ON u.id = i.usuario_id
                WHERE u.ativo = 1
                  AND i.latitude IS NOT NULL
                  AND i.longitude IS NOT NULL
                HAVING distancia_km <= :raio_km
                ORDER BY distancia_km ASC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lat', $latitude);
        $stmt->bindValue(':lng', $longitude);
        $stmt->bindValue(':raio_km', $raioKm, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $useApprovedSnapshot = (int) $row['verificado'] === 1
                && (int) $row['pendente_revisao'] === 1
                && $row['public_nome_igreja_aprovado'] !== null
                && $row['public_cidade_aprovado'] !== null;
            if ($useApprovedSnapshot) {
                $row['nome_igreja'] = (string) $row['public_nome_igreja_aprovado'];
                $row['cidade'] = (string) $row['public_cidade_aprovado'];
            }
        }

        return $rows;
    }

    private function hasMeaningfulProfileChange(
        array $profile,
        string $nomeIgreja,
        string $emailContato,
        ?string $telefone,
        string $cep,
        string $cidade,
        ?string $cnpjDigits
    ): bool {
        return (string) $profile['nome_igreja'] !== $nomeIgreja
            || (string) $profile['email_contato'] !== $emailContato
            || (string) ($profile['telefone'] ?? '') !== (string) ($telefone ?? '')
            || (string) $profile['cep'] !== $cep
            || (string) $profile['cidade'] !== $cidade
            || (string) ($profile['cnpj'] ?? '') !== (string) ($cnpjDigits ?? '');
    }
}
