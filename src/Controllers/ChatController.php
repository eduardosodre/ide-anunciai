<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ChatRepository;
use App\Security\Csrf;
use App\Service\ChatService;
use App\Session\SessionFacade;
use App\View\Html;

final class ChatController
{
    public function __construct(
        private readonly ChatRepository $chat,
        private readonly ChatService $chatService,
        private readonly Csrf $csrf
    ) {
    }

    public function iniciarGet(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $tipoRaw = (string) $request->query('tipo_entidade', '');
        $entidadeId = (int) $request->query('entidade_id', 0);
        $tipo = ChatService::normalizeTipoEntidade($tipoRaw);

        $body = $this->messagesHtml();
        if ($tipo === null || $entidadeId <= 0) {
            $body .= '<section class="page-head-stitch"><p class="hero-kicker">Conversas</p><h1 class="page-title">Iniciar conversa</h1></section><div class="card"><p>Parâmetros inválidos. Use o link no perfil público.</p><p><a href="' . Html::u('/busca') . '">Buscar perfis</a></p></div>';

            return Response::html(Html::layout('Conversa', $body, $this->csrf->token()));
        }

        $body .= '<section class="page-head-stitch"><p class="hero-kicker">Conversas</p><h1 class="page-title">Iniciar conversa</h1></section>
<div class="card form-card account-shell-stitch">
<form method="post" action="' . Html::u('/chat/iniciar') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="' . Html::escape($tipo) . '">
  <input type="hidden" name="entidade_id" value="' . $entidadeId . '">
  <div class="field">
    <label>Mensagem inicial (obrigatória, mín. 10 caracteres)</label>
    <textarea name="mensagem" class="textarea-full" required minlength="10" maxlength="8000" rows="6"></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Enviar</button>
</form>
</div>';

        return Response::html(Html::layout('Iniciar conversa', $body, $this->csrf->token()));
    }

    public function iniciarPost(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/chat', 302);
        }

        $tipo = ChatService::normalizeTipoEntidade((string) $request->input('tipo_entidade', ''));
        $entidadeId = (int) $request->input('entidade_id', 0);
        $mensagem = (string) $request->input('mensagem', '');

        if ($tipo === null || $entidadeId <= 0) {
            SessionFacade::flash('error', 'Dados inválidos.');

            return Response::redirect('/busca', 302);
        }

        $result = $this->chatService->startOrAppendMessage($uid, $tipo, $entidadeId, $mensagem);
        if (!$result['ok']) {
            SessionFacade::flash('error', $result['message']);

            return Response::redirect(
                '/chat/iniciar?tipo_entidade=' . rawurlencode($tipo) . '&entidade_id=' . $entidadeId,
                302
            );
        }

        SessionFacade::flash('success', 'Mensagem enviada.');

        return Response::redirect('/chat/' . $result['conversa_id'], 302);
    }

    public function listGet(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $items = $this->chatService->listConversationsForUser($uid);
        $body = $this->messagesHtml();
        $body .= '<section class="page-head-stitch"><p class="hero-kicker">Conversas</p><h1 class="page-title">Conversas</h1></section>';
        if ($items === []) {
            $body .= '<div class="card"><p class="empty-state">Nenhuma conversa ainda.</p></div>';
        } else {
            $body .= '<ul class="moderation-list">';
            foreach ($items as $item) {
                $body .= '<li class="card moderation-item"><a href="' . Html::u('/chat/' . (int) $item['id']) . '">'
                    . Html::escape((string) $item['outro_nome']) . '</a>'
                    . ' <small>(' . Html::escape((string) $item['tipo_entidade']) . ' #' . (int) $item['entidade_id'] . ')</small>'
                    . '</li>';
            }
            $body .= '</ul>';
        }

        return Response::html(Html::layout('Conversas', $body, $this->csrf->token()));
    }

    public function showGet(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $conversaId = (int) $request->route('id', 0);
        if ($conversaId <= 0 || !$this->chat->userParticipates($conversaId, $uid)) {
            return Response::html('<h1>404</h1><p>Conversa não encontrada.</p>', 404);
        }

        $msgs = $this->chat->listMessages($conversaId);
        $body = $this->messagesHtml();
        $body .= '<section class="page-head-stitch"><p class="hero-kicker">Conversas</p><h1 class="page-title">Conversa</h1></section><div class="card moderation-item card-gap-y">';
        foreach ($msgs as $m) {
            $body .= '<p class="chat-message-line"><strong>'
                . Html::escape((string) $m['remetente_nome']) . '</strong> '
                . '<small>' . Html::escape((string) $m['criado_em']) . '</small><br>'
                . nl2br(Html::escape((string) $m['corpo'])) . '</p>';
        }
        $body .= '</div>
<div class="card form-card account-shell-stitch">
<form method="post" action="' . Html::u('/chat/' . $conversaId . '/mensagens') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label>Nova mensagem</label>
    <textarea name="corpo" class="textarea-full" required maxlength="8000" rows="4"></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Enviar</button>
</form>
</div>
<p><a href="' . Html::u('/chat') . '">Voltar às conversas</a></p>';

        return Response::html(Html::layout('Chat', $body, $this->csrf->token()));
    }

    public function messagePost(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/chat', 302);
        }

        $conversaId = (int) $request->route('id', 0);
        $corpo = (string) $request->input('corpo', '');
        $result = $this->chatService->appendMessage($conversaId, $uid, $corpo);
        if (!$result['ok']) {
            SessionFacade::flash('error', $result['message']);

            return Response::redirect($conversaId > 0 ? '/chat/' . $conversaId : '/chat', 302);
        }

        SessionFacade::flash('success', 'Mensagem enviada.');

        return Response::redirect('/chat/' . $conversaId, 302);
    }

    public function apiConversationsCreate(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.']], 401);
        }

        $tipoRaw = (string) $request->input('tipo_destino', '');
        $destinoId = (int) $request->input('destino_id', 0);
        $mensagem = (string) $request->input('mensagem_inicial', '');

        $tipo = ChatService::normalizeTipoEntidade($tipoRaw);
        if ($tipo === null || $destinoId <= 0) {
            return Response::json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'tipo_destino ou destino_id inválidos.']], 422);
        }

        $result = $this->chatService->startOrAppendMessage($uid, $tipo, $destinoId, $mensagem);
        if (!$result['ok']) {
            return Response::json(['error' => ['code' => $result['code'], 'message' => $result['message']]], 422);
        }

        return Response::json(['conversa_id' => $result['conversa_id'], 'novo' => $result['novo']], 201);
    }

    public function apiConversationsList(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.']], 401);
        }

        return Response::json(['items' => $this->chatService->listConversationsForUser($uid)]);
    }

    public function apiConversationGet(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.']], 401);
        }

        $conversaId = (int) $request->route('id', 0);
        if ($conversaId <= 0 || !$this->chat->userParticipates($conversaId, $uid)) {
            return Response::json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Conversa não encontrada.']], 404);
        }

        $conversa = $this->chat->findById($conversaId);
        $mensagens = $this->chat->listMessages($conversaId);

        return Response::json([
            'conversa' => $conversa,
            'mensagens' => $mensagens,
        ]);
    }

    public function apiMessagePost(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.']], 401);
        }

        $conversaId = (int) $request->route('id', 0);
        $corpo = (string) $request->input('corpo', '');
        $result = $this->chatService->appendMessage($conversaId, $uid, $corpo);
        if (!$result['ok']) {
            $status = ($result['code'] ?? '') === 'FORBIDDEN' ? 404 : 422;

            return Response::json(['error' => ['code' => $result['code'], 'message' => $result['message']]], $status);
        }

        return Response::json(['ok' => true], 201);
    }

    private function messagesHtml(): string
    {
        $ok = SessionFacade::flash('success');
        $err = SessionFacade::flash('error');
        $html = '';
        if ($ok !== null && $ok !== '') {
            $html .= '<div class="msg ok">' . Html::escape($ok) . '</div>';
        }
        if ($err !== null && $err !== '') {
            $html .= '<div class="msg err">' . Html::escape($err) . '</div>';
        }

        return $html;
    }
}
