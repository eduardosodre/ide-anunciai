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
        $fotoRaw = isset($user['foto_url']) ? (string) $user['foto_url'] : '';
        $foto = $fotoRaw !== ''
            ? '<p class="profile-muted" style="margin-top:0">Foto atual:</p><p><img src="' . Html::escape(
                (str_starts_with($fotoRaw, 'http://') || str_starts_with($fotoRaw, 'https://'))
                    ? $fotoRaw
                    : Html::u($fotoRaw)
            ) . '" alt="" class="avatar-current" width="120" height="120" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border)"></p>'
            : '';

        $body .= '<div class="card form-card">
<h1 class="page-title" style="margin-bottom:0.75rem">Minha conta</h1>
' . $foto . '
<form id="form-conta" method="post" action="' . Html::u('/conta') . '" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="conta-nome">Nome completo</label>
    <input id="conta-nome" type="text" name="nome" required maxlength="255" value="' . $nome . '">
  </div>
  <div class="field">
    <label for="conta-email">E-mail</label>
    <input id="conta-email" type="email" name="email" required maxlength="255" value="' . $email . '">
  </div>
  <div class="field">
    <label for="avatar-input">Nova foto de perfil</label>
    <p class="field-hint">JPEG, PNG ou WebP até 5 MB. Depois de escolher, ajuste o recorte abaixo e salve.</p>
    <input id="avatar-input" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
  </div>
  <div id="avatar-crop-wrap" class="avatar-crop-wrap" hidden>
    <p class="field-hint" style="margin-bottom:0.5rem">Pré-visualização e recorte (arraste para posicionar; use a roda para zoom se disponível)</p>
    <img id="avatar-crop-img" alt="Recorte da foto">
  </div>
  <button type="submit" class="btn btn-primary">Salvar dados e foto</button>
</form>
</div>';

        $extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous">';
        $extraFooter = '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous"></script>'
            . '<script src="' . Html::u('/js/avatar-crop.js') . '" defer></script>';

        return Response::html(Html::layout('Minha conta', $body, $this->csrf->token(), $extraHead, $extraFooter));
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
