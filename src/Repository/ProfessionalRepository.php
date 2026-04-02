<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ProfessionalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profissional WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute(['usuario_id' => $userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $row['habilidades'] = $this->findSkillIdsByProfessionalId((int) $row['id']);

        return $row;
    }

    public function findPublicById(int $professionalId): ?array
    {
        $sql = 'SELECT p.id, p.usuario_id, p.nome_publico, p.cidade, p.verificado, p.pendente_revisao,
                       p.public_nome_publico_aprovado, p.public_cidade_aprovado, p.public_habilidades_aprovado
                FROM profissional p
                INNER JOIN usuario u ON u.id = p.usuario_id
                WHERE p.id = :id AND u.ativo = 1
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $professionalId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $useApprovedSnapshot = (int) $row['verificado'] === 1
            && (int) $row['pendente_revisao'] === 1
            && $row['public_nome_publico_aprovado'] !== null
            && $row['public_cidade_aprovado'] !== null;
        if ($useApprovedSnapshot) {
            $row['nome_publico'] = (string) $row['public_nome_publico_aprovado'];
            $row['cidade'] = (string) $row['public_cidade_aprovado'];
            $row['habilidades'] = $this->findPublicSkillsFromApprovedSnapshot((string) ($row['public_habilidades_aprovado'] ?? ''));
        } else {
            $row['habilidades'] = $this->findPublicSkillsByProfessionalId((int) $row['id']);
        }

        return $row;
    }

    public function findUsuarioIdForProfissionalPublic(int $profissionalId): ?int
    {
        $sql = 'SELECT p.usuario_id FROM profissional p
                INNER JOIN usuario u ON u.id = p.usuario_id
                WHERE p.id = :id AND u.ativo = 1
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $profissionalId]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['usuario_id'];
    }

    public function allSkills(): array
    {
        $stmt = $this->pdo->query('SELECT id, codigo, label FROM habilidade ORDER BY label ASC');

        return $stmt->fetchAll();
    }

    public function saveOwnProfile(int $userId, string $nomePublico, ?string $telefone, string $cidade, array $skillIds): int
    {
        $profile = $this->findByUserId($userId);
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            if ($profile === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO profissional (usuario_id, nome_publico, telefone, cidade, verificado, pendente_revisao, criado_em, atualizado_em)
                     VALUES (:usuario_id, :nome_publico, :telefone, :cidade, 0, 0, :criado_em, :atualizado_em)'
                );
                $stmt->execute([
                    'usuario_id' => $userId,
                    'nome_publico' => $nomePublico,
                    'telefone' => $telefone,
                    'cidade' => $cidade,
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                ]);
                $professionalId = (int) $this->pdo->lastInsertId();
            } else {
                $professionalId = (int) $profile['id'];
                $hasMeaningfulChange = $this->hasMeaningfulProfileChange($profile, $nomePublico, $telefone, $cidade, $skillIds);
                $pendenteRevisao = ((int) $profile['verificado'] === 1 && $hasMeaningfulChange)
                    ? 1
                    : (int) $profile['pendente_revisao'];
                $stmt = $this->pdo->prepare(
                    'UPDATE profissional
                     SET nome_publico = :nome_publico,
                         telefone = :telefone,
                         cidade = :cidade,
                         pendente_revisao = :pendente_revisao,
                         atualizado_em = :atualizado_em
                     WHERE id = :id AND usuario_id = :usuario_id'
                );
                $stmt->execute([
                    'nome_publico' => $nomePublico,
                    'telefone' => $telefone,
                    'cidade' => $cidade,
                    'pendente_revisao' => $pendenteRevisao,
                    'atualizado_em' => $now,
                    'id' => $professionalId,
                    'usuario_id' => $userId,
                ]);
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM profissional_habilidade WHERE profissional_id = :profissional_id');
            $deleteStmt->execute(['profissional_id' => $professionalId]);

            $insertStmt = $this->pdo->prepare(
                'INSERT INTO profissional_habilidade (profissional_id, habilidade_id) VALUES (:profissional_id, :habilidade_id)'
            );
            foreach ($skillIds as $skillId) {
                $insertStmt->execute([
                    'profissional_id' => $professionalId,
                    'habilidade_id' => $skillId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }

        return $professionalId;
    }

    public function updateCoordinatesByUserId(int $userId, ?float $latitude, ?float $longitude): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE profissional
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

    public function searchByRadius(
        float $latitude,
        float $longitude,
        int $raioKm,
        ?int $habilidadeId,
        int $offset,
        int $limit
    ): array {
        $distanceFormula = '(6371 * ACOS(
            COS(RADIANS(:lat)) * COS(RADIANS(p.latitude)) * COS(RADIANS(p.longitude) - RADIANS(:lng))
            + SIN(RADIANS(:lat)) * SIN(RADIANS(p.latitude))
        ))';
        $whereSkill = $habilidadeId !== null ? ' AND EXISTS (
                SELECT 1 FROM profissional_habilidade ph2
                WHERE ph2.profissional_id = p.id AND ph2.habilidade_id = :habilidade_id
            )' : '';
        $sql = 'SELECT p.id, p.usuario_id, p.nome_publico, p.cidade, p.verificado, p.pendente_revisao,
                       p.public_nome_publico_aprovado, p.public_cidade_aprovado, p.public_habilidades_aprovado,
                       ' . $distanceFormula . ' AS distancia_km
                FROM profissional p
                INNER JOIN usuario u ON u.id = p.usuario_id
                WHERE u.ativo = 1
                  AND p.latitude IS NOT NULL
                  AND p.longitude IS NOT NULL'
                . $whereSkill . '
                HAVING distancia_km <= :raio_km
                ORDER BY distancia_km ASC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lat', $latitude);
        $stmt->bindValue(':lng', $longitude);
        $stmt->bindValue(':raio_km', $raioKm, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($habilidadeId !== null) {
            $stmt->bindValue(':habilidade_id', $habilidadeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $useApprovedSnapshot = (int) $row['verificado'] === 1
                && (int) $row['pendente_revisao'] === 1
                && $row['public_nome_publico_aprovado'] !== null
                && $row['public_cidade_aprovado'] !== null;
            if ($useApprovedSnapshot) {
                $row['nome_publico'] = (string) $row['public_nome_publico_aprovado'];
                $row['cidade'] = (string) $row['public_cidade_aprovado'];
            }
            $row['habilidades'] = $this->findPublicSkillsByProfessionalId((int) $row['id']);
        }

        return $rows;
    }

    public function validSkillIds(array $skillIds): array
    {
        if ($skillIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
        $stmt = $this->pdo->prepare('SELECT id FROM habilidade WHERE id IN (' . $placeholders . ')');
        $stmt->execute($skillIds);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function findSkillIdsByProfessionalId(int $professionalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT habilidade_id FROM profissional_habilidade WHERE profissional_id = :profissional_id ORDER BY habilidade_id ASC'
        );
        $stmt->execute(['profissional_id' => $professionalId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'habilidade_id'));
    }

    private function findPublicSkillsByProfessionalId(int $professionalId): array
    {
        $sql = 'SELECT h.id, h.codigo, h.label
                FROM profissional_habilidade ph
                INNER JOIN habilidade h ON h.id = ph.habilidade_id
                WHERE ph.profissional_id = :profissional_id
                ORDER BY h.label ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['profissional_id' => $professionalId]);

        return $stmt->fetchAll();
    }

    private function findPublicSkillsFromApprovedSnapshot(string $skillIdsCsv): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $skillIdsCsv)), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, codigo, label
             FROM habilidade
             WHERE id IN (' . $placeholders . ')
             ORDER BY label ASC'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    private function hasMeaningfulProfileChange(array $profile, string $nomePublico, ?string $telefone, string $cidade, array $skillIds): bool
    {
        $currentSkillIds = $this->findSkillIdsByProfessionalId((int) $profile['id']);
        sort($currentSkillIds);
        $newSkillIds = array_map('intval', $skillIds);
        sort($newSkillIds);

        return (string) $profile['nome_publico'] !== $nomePublico
            || (string) ($profile['telefone'] ?? '') !== (string) ($telefone ?? '')
            || (string) $profile['cidade'] !== $cidade
            || $currentSkillIds !== $newSkillIds;
    }
}
