<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ReportRepository;
use App\Repository\UserRepository;

final class ReportService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ReportRepository $reports
    ) {
    }

    /**
     * @return array{ok: true, id: int}|array{ok: false, code: string, message: string}
     */
    public function submit(int $denuncianteId, int $denunciadoId, string $descricao): array
    {
        $descricao = trim($descricao);
        $len = mb_strlen($descricao);
        if ($len < 10) {
            return ['ok' => false, 'code' => 'DESC_TOO_SHORT', 'message' => 'Descrição deve ter pelo menos 10 caracteres.'];
        }
        if ($len > 8000) {
            return ['ok' => false, 'code' => 'DESC_TOO_LONG', 'message' => 'Descrição muito longa (máx. 8000 caracteres).'];
        }

        if ($denunciadoId <= 0) {
            return ['ok' => false, 'code' => 'INVALID_TARGET', 'message' => 'Usuário alvo inválido.'];
        }

        if ($denunciadoId === $denuncianteId) {
            return ['ok' => false, 'code' => 'SELF', 'message' => 'Você não pode denunciar a si mesmo.'];
        }

        $target = $this->users->findById($denunciadoId);
        if ($target === null) {
            return ['ok' => false, 'code' => 'TARGET_NOT_FOUND', 'message' => 'Usuário não encontrado ou inativo.'];
        }

        if ($this->reports->countTodayPair($denuncianteId, $denunciadoId) >= 3) {
            return ['ok' => false, 'code' => 'RATE_LIMIT_PAIR', 'message' => 'Limite de 3 denúncias por dia para este usuário.'];
        }

        if ($this->reports->countTodayByDenunciante($denuncianteId) >= 10) {
            return ['ok' => false, 'code' => 'RATE_LIMIT_DAY', 'message' => 'Limite de 10 denúncias por dia.'];
        }

        $id = $this->reports->insert($denuncianteId, $denunciadoId, $descricao);

        return ['ok' => true, 'id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(int $limit = 200): array
    {
        return $this->reports->listRecentForAdmin($limit);
    }
}
