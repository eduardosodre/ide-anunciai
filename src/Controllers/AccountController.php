<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\UserRepository;
use App\Security\Csrf;
use App\Service\AuthService;
use App\Session\SessionFacade;
use App\View\Html;

final class AccountController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    public function contaGet(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $user = $this->users->findById($uid);
        if ($user === null) {
            SessionFacade::logout();

            return Response::redirect('/login', 302);
        }

        $body = $this->messagesHtml();
        $nome = Html::escape((string) $user['nome']);
        $email = Html::escape((string) $user['email']);
        $foto = isset($user['foto_url']) && $user['foto_url'] !== null && $user['foto_url'] !== ''
            ? '<p><img src="' . Html::escape((string) $user['foto_url']) . '" alt="" style="max-width:120px;border-radius:8px"></p>'
            : '';

        $body .= '<h1>Minha conta</h1>' . $foto . '
<form method="post" action="/conta" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <label>Nome completo <input type="text" name="nome" required maxlength="255" value="' . $nome . '"></label>
  <label>E-mail <input type="email" name="email" required maxlength="255" value="' . $email . '"></label>
  <label>Foto de perfil (JPEG, PNG ou WebP, máx. 5 MB) <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></label>
  <button type="submit">Salvar</button>
</form>';

        return Response::html(Html::layout('Minha conta', $body, $this->csrf->token()));
    }

    public function contaPost(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/conta', 302);
        }

        $result = $this->auth->updateAccount(
            $uid,
            (string) $request->input('nome', ''),
            (string) $request->input('email', '')
        );

        if (!$result['ok']) {
            $errs = $result['errors'] ?? [];
            SessionFacade::flash('error', implode(' ', array_values($errs)));

            return Response::redirect('/conta', 302);
        }

        $uploadErr = null;
        if (!empty($_FILES['foto']) && is_array($_FILES['foto'])) {
            $up = $this->auth->saveAvatar($uid, $_FILES['foto']);
            if (!$up['ok']) {
                $uploadErr = (string) ($up['error'] ?? 'Erro no upload.');
            }
        }

        if ($uploadErr !== null) {
            SessionFacade::flash('error', $uploadErr);
        } else {
            SessionFacade::flash('success', 'Dados atualizados.');
        }

        return Response::redirect('/conta', 302);
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
