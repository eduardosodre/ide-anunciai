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
            $body .= '<h1>Iniciar conversa</h1><p>Parâmetros inválidos. Use o link no perfil público.</p><p><a href="' . Html::u('/busca') . '">Buscar perfis</a></p>';

            return Response::html(Html::layout('Conversa', $body, $this->csrf->token()));
        }

        $body .= '<h1>Iniciar conversa</h1>
<form method="post" action="' . Html::u('/chat/iniciar') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="' . Html::escape($tipo) . '">
  <input type="hidden" name="entidade_id" value="' . $entidadeId . '">
  <label>Mensagem inicial (obrigatória, mín. 10 caracteres)
    <textarea name="mensagem" required minlength="10" maxlength="8000" rows="6" style="width:100%;max-width:32rem"></textarea>
  </label>
  <button type="submit">Enviar</button>
</form>';

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
        $body .= '<h1>Conversas</h1>';
        if ($items === []) {
            $body .= '<p>Nenhuma conversa ainda.</p>';
        } else {
            $body .= '<ul>';
            foreach ($items as $item) {
                $body .= '<li><a href="' . Html::u('/chat/' . (int) $item['id']) . '">'
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
        $body .= '<h1>Conversa</h1><div style="margin:1rem 0">';
        foreach ($msgs as $m) {
            $body .= '<p style="border-bottom:1px solid #eee;padding:0.5rem 0"><strong>'
                . Html::escape((string) $m['remetente_nome']) . '</strong> '
                . '<small>' . Html::escape((string) $m['criado_em']) . '</small><br>'
                . nl2br(Html::escape((string) $m['corpo'])) . '</p>';
        }
        $body .= '</div>
<form method="post" action="' . Html::u('/chat/' . $conversaId . '/mensagens') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <label>Nova mensagem
    <textarea name="corpo" required maxlength="8000" rows="4" style="width:100%;max-width:32rem"></textarea>
  </label>
  <button type="submit">Enviar</button>
</form>
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
