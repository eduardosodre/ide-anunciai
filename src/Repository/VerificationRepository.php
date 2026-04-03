<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class VerificationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listOwnRequests(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tipo_entidade, tipo_fluxo, status, motivo_rejeicao, criado_em, atualizado_em
             FROM verificacao_solicitacao
             WHERE usuario_id = :usuario_id
             ORDER BY id DESC'
        );
        $stmt->execute(['usuario_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function hasPendingRequestForEntity(string $tipoEntidade, int $entidadeId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM verificacao_solicitacao
             WHERE tipo_entidade = :tipo_entidade
               AND entidade_id = :entidade_id
               AND status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'tipo_entidade' => $tipoEntidade,
            'entidade_id' => $entidadeId,
            'status' => 'pendente',
        ]);

        return $stmt->fetch() !== false;
    }

    public function createVerificationRequest(
        int $userId,
        string $tipoEntidade,
        int $entidadeId,
        ?string $rg,
        ?string $cpf,
        ?string $cnpj,
        array $documentos
    ): int {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO verificacao_solicitacao (
                    usuario_id,
                    tipo_entidade,
                    entidade_id,
                    tipo_fluxo,
                    status,
                    rg,
                    cpf,
                    cnpj,
                    criado_em,
                    atualizado_em
                ) VALUES (
                    :usuario_id,
                    :tipo_entidade,
                    :entidade_id,
                    :tipo_fluxo,
                    :status,
                    :rg,
                    :cpf,
                    :cnpj,
                    :criado_em,
                    :atualizado_em
                )'
            );
            $stmt->execute([
                'usuario_id' => $userId,
                'tipo_entidade' => $tipoEntidade,
                'entidade_id' => $entidadeId,
                'tipo_fluxo' => 'solicitacao',
                'status' => 'pendente',
                'rg' => $rg,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'criado_em' => $now,
                'atualizado_em' => $now,
            ]);
            $solicitacaoId = (int) $this->pdo->lastInsertId();

            $this->insertDocuments($solicitacaoId, $documentos, $now);
            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }

        return $solicitacaoId;
    }

    public function createRevisionRequestIfNeeded(int $userId, string $tipoEntidade, int $entidadeId): void
    {
        if ($this->hasPendingRequestForEntity($tipoEntidade, $entidadeId)) {
            return;
        }

        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO verificacao_solicitacao (
                usuario_id,
                tipo_entidade,
                entidade_id,
                tipo_fluxo,
                status,
                criado_em,
                atualizado_em
            ) VALUES (
                :usuario_id,
                :tipo_entidade,
                :entidade_id,
                :tipo_fluxo,
                :status,
                :criado_em,
                :atualizado_em
            )'
        );
        $stmt->execute([
            'usuario_id' => $userId,
            'tipo_entidade' => $tipoEntidade,
            'entidade_id' => $entidadeId,
            'tipo_fluxo' => 'revisao',
            'status' => 'pendente',
            'criado_em' => $now,
            'atualizado_em' => $now,
        ]);
    }

    public function listPendingForAdmin(string $tipoFluxo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT vs.*, u.nome AS usuario_nome, u.email AS usuario_email
             FROM verificacao_solicitacao vs
             INNER JOIN usuario u ON u.id = vs.usuario_id
             WHERE vs.status = :status
               AND vs.tipo_fluxo = :tipo_fluxo
             ORDER BY vs.id ASC'
        );
        $stmt->execute([
            'status' => 'pendente',
            'tipo_fluxo' => $tipoFluxo,
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['documentos'] = $this->findDocumentsByRequestId((int) $row['id']);
        }

        return $rows;
    }

    public function approveOrReject(
        int $solicitacaoId,
        int $adminUserId,
        string $acao,
        ?string $motivoRejeicao
    ): ?array {
        $solicitacao = $this->findRequestById($solicitacaoId);
        if ($solicitacao === null) {
            return null;
        }
        if ((string) $solicitacao['status'] !== 'pendente') {
            return $solicitacao;
        }

        $statusNovo = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE verificacao_solicitacao
                 SET status = :status,
                     motivo_rejeicao = :motivo_rejeicao,
                     analisado_por_usuario_id = :analisado_por_usuario_id,
                     analisado_em = :analisado_em,
                     atualizado_em = :atualizado_em
                 WHERE id = :id'
            );
            $stmt->execute([
                'status' => $statusNovo,
                'motivo_rejeicao' => $statusNovo === 'rejeitado' ? $motivoRejeicao : null,
                'analisado_por_usuario_id' => $adminUserId,
                'analisado_em' => $now,
                'atualizado_em' => $now,
                'id' => $solicitacaoId,
            ]);

            if ($statusNovo === 'aprovado') {
                $this->applyApproval((string) $solicitacao['tipo_entidade'], (int) $solicitacao['entidade_id'], $now);
            } else {
                $this->applyRejection((string) $solicitacao['tipo_entidade'], (int) $solicitacao['entidade_id'], $now);
            }

            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }

        return $this->findRequestById($solicitacaoId);
    }

    private function applyApproval(string $tipoEntidade, int $entidadeId, string $now): void
    {
        if ($tipoEntidade === 'ministro') {
            $profile = $this->findProfessionalById($entidadeId);
            if ($profile === null) {
                return;
            }

            $skillIds = $this->findProfessionalSkillIds($entidadeId);
            $skillsCsv = implode(',', $skillIds);
            $stmt = $this->pdo->prepare(
                'UPDATE profissional
                 SET verificado = 1,
                     pendente_revisao = 0,
                     public_nome_publico_aprovado = :public_nome_publico_aprovado,
                     public_cidade_aprovado = :public_cidade_aprovado,
                     public_habilidades_aprovado = :public_habilidades_aprovado,
                     atualizado_em = :atualizado_em
                 WHERE id = :id'
            );
            $stmt->execute([
                'public_nome_publico_aprovado' => $profile['nome_publico'],
                'public_cidade_aprovado' => $profile['cidade'],
                'public_habilidades_aprovado' => $skillsCsv,
                'atualizado_em' => $now,
                'id' => $entidadeId,
            ]);

            return;
        }

        $church = $this->findChurchById($entidadeId);
        if ($church === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE igreja
             SET verificado = 1,
                 pendente_revisao = 0,
                 public_nome_igreja_aprovado = :public_nome_igreja_aprovado,
                 public_cidade_aprovado = :public_cidade_aprovado,
                 atualizado_em = :atualizado_em
             WHERE id = :id'
        );
        $stmt->execute([
            'public_nome_igreja_aprovado' => $church['nome_igreja'],
            'public_cidade_aprovado' => $church['cidade'],
            'atualizado_em' => $now,
            'id' => $entidadeId,
        ]);
    }

    private function applyRejection(string $tipoEntidade, int $entidadeId, string $now): void
    {
        $table = $tipoEntidade === 'ministro' ? 'profissional' : 'igreja';
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $table . '
             SET pendente_revisao = 0,
                 atualizado_em = :atualizado_em
             WHERE id = :id'
        );
        $stmt->execute([
            'atualizado_em' => $now,
            'id' => $entidadeId,
        ]);
    }

    private function findRequestById(int $solicitacaoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verificacao_solicitacao WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $solicitacaoId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function insertDocuments(int $solicitacaoId, array $documentos, string $now): void
    {
        if ($documentos === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO verificacao_documento (
                verificacao_solicitacao_id,
                tipo_documento,
                nome_arquivo,
                caminho_arquivo,
                criado_em
            ) VALUES (
                :verificacao_solicitacao_id,
                :tipo_documento,
                :nome_arquivo,
                :caminho_arquivo,
                :criado_em
            )'
        );

        foreach ($documentos as $documento) {
            if (!isset($documento['tipo_documento']) || trim((string) $documento['tipo_documento']) === '') {
                continue;
            }
            $stmt->execute([
                'verificacao_solicitacao_id' => $solicitacaoId,
                'tipo_documento' => trim((string) $documento['tipo_documento']),
                'nome_arquivo' => isset($documento['nome_arquivo']) ? trim((string) $documento['nome_arquivo']) : null,
                'caminho_arquivo' => isset($documento['caminho_arquivo']) ? trim((string) $documento['caminho_arquivo']) : null,
                'criado_em' => $now,
            ]);
        }
    }

    private function findDocumentsByRequestId(int $solicitacaoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tipo_documento, nome_arquivo, caminho_arquivo
             FROM verificacao_documento
             WHERE verificacao_solicitacao_id = :verificacao_solicitacao_id
             ORDER BY id ASC'
        );
        $stmt->execute(['verificacao_solicitacao_id' => $solicitacaoId]);

        return $stmt->fetchAll();
    }

    private function findProfessionalById(int $professionalId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome_publico, cidade
             FROM profissional
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $professionalId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function findChurchById(int $churchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome_igreja, cidade
             FROM igreja
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $churchId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function findProfessionalSkillIds(int $professionalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT habilidade_id
             FROM profissional_habilidade
             WHERE profissional_id = :profissional_id
             ORDER BY habilidade_id ASC'
        );
        $stmt->execute(['profissional_id' => $professionalId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'habilidade_id'));
    }
}
