<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AppConfig;
use App\Repository\ChatRepository;
use App\Repository\ChurchRepository;
use App\Repository\ProfessionalRepository;
use App\Repository\UserRepository;

final class ChatService
{
    public function __construct(
        private readonly ChatRepository $chat,
        private readonly UserRepository $users,
        private readonly ProfessionalRepository $professionals,
        private readonly ChurchRepository $churches,
        private readonly Mailer $mailer,
        private readonly AppConfig $config
    ) {
    }

    /**
     * @return array{ok: true, conversa_id: int, novo: bool}|array{ok: false, code: string, message: string}
     */
    public function startOrAppendMessage(
        int $solicitanteId,
        string $tipoEntidade,
        int $entidadeId,
        string $mensagem
    ): array {
        $mensagem = trim($mensagem);
        $len = mb_strlen($mensagem);
        if ($len < 10) {
            return ['ok' => false, 'code' => 'MSG_TOO_SHORT', 'message' => 'Mensagem deve ter pelo menos 10 caracteres.'];
        }
        if ($len > 8000) {
            return ['ok' => false, 'code' => 'MSG_TOO_LONG', 'message' => 'Mensagem muito longa (máx. 8000 caracteres).'];
        }

        if ($tipoEntidade !== 'ministro' && $tipoEntidade !== 'igreja') {
            return ['ok' => false, 'code' => 'INVALID_TYPE', 'message' => 'Tipo de destino inválido.'];
        }

        $destinatarioId = $tipoEntidade === 'ministro'
            ? $this->professionals->findUsuarioIdForProfissionalPublic($entidadeId)
            : $this->churches->findUsuarioIdForIgrejaPublic($entidadeId);

        if ($destinatarioId === null) {
            return ['ok' => false, 'code' => 'DEST_NOT_FOUND', 'message' => 'Perfil não encontrado ou indisponível.'];
        }

        if ($destinatarioId === $solicitanteId) {
            return ['ok' => false, 'code' => 'SELF', 'message' => 'Você não pode iniciar conversa com o próprio perfil.'];
        }

        $solicitante = $this->users->findById($solicitanteId);
        $destinatario = $this->users->findById($destinatarioId);
        if ($solicitante === null || $destinatario === null) {
            return ['ok' => false, 'code' => 'USER_INACTIVE', 'message' => 'Usuário não disponível.'];
        }

        $existing = $this->chat->findBySolicitanteAndEntity($solicitanteId, $tipoEntidade, $entidadeId);
        $novo = false;
        if ($existing === null) {
            $conversaId = $this->chat->create($solicitanteId, $destinatarioId, $tipoEntidade, $entidadeId);
            $novo = true;
        } else {
            $conversaId = (int) $existing['id'];
        }

        $this->chat->insertMessage($conversaId, $solicitanteId, $mensagem);

        if ($novo) {
            $this->sendFirstMessageEmail(
                $conversaId,
                $destinatario,
                $solicitante,
                $mensagem,
                $tipoEntidade,
                $entidadeId
            );
        }

        return ['ok' => true, 'conversa_id' => $conversaId, 'novo' => $novo];
    }

    /**
     * @return array{ok: true}|array{ok: false, code: string, message: string}
     */
    public function appendMessage(int $conversaId, int $remetenteId, string $corpo): array
    {
        $corpo = trim($corpo);
        $len = mb_strlen($corpo);
        if ($len < 1) {
            return ['ok' => false, 'code' => 'MSG_EMPTY', 'message' => 'Mensagem não pode ser vazia.'];
        }
        if ($len > 8000) {
            return ['ok' => false, 'code' => 'MSG_TOO_LONG', 'message' => 'Mensagem muito longa (máx. 8000 caracteres).'];
        }

        if (!$this->chat->userParticipates($conversaId, $remetenteId)) {
            return ['ok' => false, 'code' => 'FORBIDDEN', 'message' => 'Conversa não encontrada.'];
        }

        $this->chat->insertMessage($conversaId, $remetenteId, $corpo);

        return ['ok' => true];
    }

    public static function normalizeTipoEntidade(string $raw): ?string
    {
        $r = strtolower(trim($raw));
        if (in_array($r, ['ministro', 'profissional', 'professional'], true)) {
            return 'ministro';
        }
        if (in_array($r, ['igreja', 'church'], true)) {
            return 'igreja';
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function listConversationsForUser(int $userId): array
    {
        $rows = $this->chat->listForUser($userId);
        $out = [];
        foreach ($rows as $row) {
            $outroId = (int) $row['solicitante_id'] === $userId
                ? (int) $row['destinatario_usuario_id']
                : (int) $row['solicitante_id'];
            $outroNome = (int) $row['solicitante_id'] === $userId
                ? (string) $row['destinatario_nome']
                : (string) $row['solicitante_nome'];
            $out[] = [
                'id' => (int) $row['id'],
                'tipo_entidade' => (string) $row['tipo_entidade'],
                'entidade_id' => (int) $row['entidade_id'],
                'outro_usuario_id' => $outroId,
                'outro_nome' => $outroNome,
                'atualizado_em' => (string) $row['atualizado_em'],
            ];
        }

        return $out;
    }

    private function sendFirstMessageEmail(
        int $conversaId,
        array $destinatario,
        array $solicitante,
        string $mensagem,
        string $tipoEntidade,
        int $entidadeId
    ): void {
        $to = (string) $destinatario['email'];
        $base = $this->config->baseUrl();
        $link = $base . '/chat/' . $conversaId;
        $tipoLabel = $tipoEntidade === 'ministro' ? 'ministro/profissional' : 'igreja';
        $body = "Olá,\n\n"
            . (($solicitante['nome'] ?? '') !== ''
                ? (string) $solicitante['nome'] . ' (' . (string) $solicitante['email'] . ')'
                : (string) $solicitante['email'])
            . " enviou uma mensagem sobre seu perfil ({$tipoLabel}, id {$entidadeId}).\n\n"
            . "Mensagem:\n---\n{$mensagem}\n---\n\n"
            . "Abrir conversa: {$link}\n";

        $this->mailer->send($to, 'Nova mensagem — ide-anunciai', $body);
        $this->chat->markPrimeiroEmailEnviado($conversaId);
    }
}
