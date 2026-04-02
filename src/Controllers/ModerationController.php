<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\UserRepository;
use App\Security\Csrf;
use App\Service\ReportService;
use App\Session\SessionFacade;
use App\View\Html;

final class ModerationController
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    public function denunciarGet(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        $alvo = (int) $request->query('alvo', 0);
        if ($alvo <= 0) {
            SessionFacade::flash('error', 'Informe um perfil válido para denunciar.');

            return Response::redirect('/busca', 302);
        }

        $target = $this->users->findById($alvo);
        if ($target === null) {
            SessionFacade::flash('error', 'Usuário não encontrado.');

            return Response::redirect('/busca', 302);
        }

        if ($alvo === $userId) {
            SessionFacade::flash('error', 'Você não pode denunciar a si mesmo.');

            return Response::redirect('/conta', 302);
        }

        $body = $this->messagesHtml();
        $body .= '<h1>Denunciar usuário</h1>
<p>Denunciando: <strong>' . Html::escape((string) $target['nome']) . '</strong></p>
<form method="post" action="/denunciar">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="usuario_alvo_id" value="' . $alvo . '">
  <label>Descrição do motivo (obrigatório, mín. 10 caracteres)
    <textarea name="descricao" required minlength="10" maxlength="8000" rows="6" style="width:100%;max-width:32rem"></textarea>
  </label>
  <button type="submit">Enviar denúncia</button>
</form>
<p><a href="/">Voltar ao início</a></p>';

        return Response::html(Html::layout('Denunciar', $body, $this->csrf->token()));
    }

    public function denunciarPost(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/denunciar?alvo=' . (int) $request->input('usuario_alvo_id', 0), 302);
        }

        $alvo = (int) $request->input('usuario_alvo_id', 0);
        $descricao = (string) $request->input('descricao', '');

        $result = $this->reports->submit($userId, $alvo, $descricao);
        if (!$result['ok']) {
            SessionFacade::flash('error', $result['message']);

            return Response::redirect('/denunciar?alvo=' . $alvo, 302);
        }

        SessionFacade::flash('success', 'Denúncia registrada. A equipe analisará o caso.');

        return Response::redirect('/conta', 302);
    }

    public function adminDenunciasGet(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::redirect('/login', 302);
        }

        $rows = $this->reports->listForAdmin(200);
        $body = $this->messagesHtml();
        $body .= '<h1>Admin — denúncias</h1>
<p>Registros recentes (mais novos primeiro). Limite diário por par: 3; por denunciante: 10.</p>';

        if ($rows === []) {
            $body .= '<p>Nenhuma denúncia registrada.</p>';
        } else {
            $body .= '<ul style="list-style:none;padding:0">';
            foreach ($rows as $row) {
                $ativo = (int) ($row['denunciado_ativo'] ?? 0) === 1;
                $statusDenunciado = $ativo ? 'ativo' : 'inativo';
                $body .= '<li style="border:1px solid #ddd;padding:1rem;margin-bottom:1rem;border-radius:6px">
<p><strong>#' . (int) $row['id'] . '</strong> — ' . Html::escape((string) $row['criado_em']) . '</p>
<p><strong>Denunciante:</strong> ' . Html::escape((string) $row['denunciante_nome'])
                    . ' &lt;' . Html::escape((string) $row['denunciante_email']) . '&gt;</p>
<p><strong>Denunciado:</strong> ' . Html::escape((string) $row['denunciado_nome'])
                    . ' &lt;' . Html::escape((string) $row['denunciado_email']) . '&gt; (' . $statusDenunciado . ')</p>
<p><strong>Descrição:</strong></p>
<pre style="white-space:pre-wrap;background:#f5f5f5;padding:0.75rem">' . Html::escape((string) $row['descricao']) . '</pre>';
                if ($ativo && (int) $row['denunciado_id'] !== (int) $admin['id']) {
                    $body .= '<form method="post" action="/admin/usuarios/' . (int) $row['denunciado_id'] . '/inativar" style="margin-top:0.5rem">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <button type="submit">Inativar usuário denunciado</button>
</form>';
                }
                $body .= '</li>';
            }
            $body .= '</ul>';
        }

        $body .= '<p><a href="/">Início</a></p>';

        return Response::html(Html::layout('Admin denúncias', $body, $this->csrf->token()));
    }

    public function adminInativarPost(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/admin/denuncias', 302);
        }

        $targetId = (int) $request->route('id', 0);
        if ($targetId <= 0) {
            SessionFacade::flash('error', 'Usuário inválido.');

            return Response::redirect('/admin/denuncias', 302);
        }

        if ($targetId === (int) $admin['id']) {
            SessionFacade::flash('error', 'Não é possível inativar a própria conta.');

            return Response::redirect('/admin/denuncias', 302);
        }

        $user = $this->users->findByIdAnyStatus($targetId);
        if ($user === null) {
            SessionFacade::flash('error', 'Usuário não encontrado.');

            return Response::redirect('/admin/denuncias', 302);
        }

        if ((int) $user['ativo'] !== 1) {
            SessionFacade::flash('success', 'Usuário já estava inativo.');

            return Response::redirect('/admin/denuncias', 302);
        }

        $this->users->deactivateById($targetId);
        SessionFacade::flash('success', 'Usuário inativado.');

        return Response::redirect('/admin/denuncias', 302);
    }

    private function requireAdminUser(): ?array
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return null;
        }

        return ((int) ($user['admin'] ?? 0) === 1) ? $user : null;
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
