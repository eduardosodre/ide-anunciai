<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Service\AuthService;
use App\Session\SessionFacade;
use App\View\Html;

final class AuthWebController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf
    ) {
    }

    public function cadastroGet(Request $request): Response
    {
        $body = $this->messagesHtml();
        $body .= '<div class="page-auth">
<div class="card form-card">
<h1 class="form-card-title">Criar conta</h1>
<p class="form-lead">Preencha os dados. As validações aparecem abaixo de cada campo.</p>
<div id="form-register-global" class="form-global-error" role="alert" aria-live="polite"></div>
<form id="form-register" method="post" action="' . Html::u('/cadastro') . '" novalidate>
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="reg-nome">Nome completo</label>
    <input id="reg-nome" type="text" name="nome" maxlength="255" autocomplete="name">
    <div class="field-error" data-error-for="nome"></div>
  </div>
  <div class="field">
    <label for="reg-email">E-mail</label>
    <input id="reg-email" type="email" name="email" maxlength="255" autocomplete="email">
    <div class="field-error" data-error-for="email"></div>
  </div>
  <div class="field">
    <label for="reg-senha">Senha</label>
    <input id="reg-senha" type="password" name="senha" autocomplete="new-password">
    <p class="field-hint">Mínimo 8 caracteres, com maiúscula, minúscula e número.</p>
    <div class="field-error" data-error-for="senha"></div>
  </div>
  <div class="field">
    <label for="reg-senha2">Confirmar senha</label>
    <input id="reg-senha2" type="password" name="senha_confirmacao" autocomplete="new-password">
    <div class="field-error" data-error-for="senha_confirmacao"></div>
  </div>
  <div class="field field-check">
    <label>
      <input type="checkbox" name="consentimento" value="1">
      Li e aceito a <a href="' . Html::u('/privacidade') . '" target="_blank" rel="noopener">Política de Privacidade</a>
    </label>
    <div class="field-error" data-error-for="consentimento"></div>
  </div>
  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem">
    <span class="btn-spinner spinner" hidden aria-hidden="true"></span>
    <span class="btn-label">Cadastrar</span>
  </button>
</form>
<p class="link-row">Já tem conta? <a href="' . Html::u('/login') . '">Entrar</a></p>
</div>
</div>';

        return Response::html(Html::layout('Cadastro', $body, $this->csrf->token()));
    }

    public function cadastroPost(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida. Tente novamente.');

            return Response::redirect('/cadastro', 302);
        }

        $consent = $request->input('consentimento') === '1' || $request->input('consentimento') === 1;
        $result = $this->auth->register(
            (string) $request->input('nome', ''),
            (string) $request->input('email', ''),
            (string) $request->input('senha', ''),
            (string) $request->input('senha_confirmacao', ''),
            $consent
        );

        if (!$result['ok']) {
            $errs = $result['errors'] ?? [];
            SessionFacade::flash('error', implode(' ', array_values($errs)));

            return Response::redirect('/cadastro', 302);
        }

        SessionFacade::login((int) $result['user_id'], false);
        SessionFacade::flash('success', 'Conta criada com sucesso.');

        return Response::redirect('/conta', 302);
    }

    public function loginGet(Request $request): Response
    {
        $body = $this->messagesHtml();
        $body .= '<div class="page-auth">
<div class="card form-card">
<h1 class="form-card-title">Entrar</h1>
<p class="form-lead">Use seu e-mail e senha cadastrados.</p>
<div id="form-login-global" class="form-global-error" role="alert" aria-live="polite"></div>
<form id="form-login" method="post" action="' . Html::u('/login') . '" novalidate>
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="login-email">E-mail</label>
    <input id="login-email" type="email" name="email" autocomplete="username">
  </div>
  <div class="field">
    <label for="login-senha">Senha</label>
    <input id="login-senha" type="password" name="senha" autocomplete="current-password">
  </div>
  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem">
    <span class="btn-spinner spinner" hidden aria-hidden="true"></span>
    <span class="btn-label">Entrar</span>
  </button>
</form>
<p class="link-row"><a href="' . Html::u('/recuperar-senha') . '">Esqueci minha senha</a></p>
<p class="link-row">Não tem conta? <a href="' . Html::u('/cadastro') . '">Cadastrar</a></p>
</div>
</div>';

        return Response::html(Html::layout('Login', $body, $this->csrf->token()));
    }

    public function loginPost(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida. Tente novamente.');

            return Response::redirect('/login', 302);
        }

        $result = $this->auth->login(
            (string) $request->input('email', ''),
            (string) $request->input('senha', '')
        );

        if (!$result['ok']) {
            SessionFacade::flash('error', (string) ($result['error'] ?? 'Falha no login.'));

            return Response::redirect('/login', 302);
        }

        SessionFacade::login((int) $result['user']['id'], (int) ($result['user']['admin'] ?? 0) === 1);
        SessionFacade::flash('success', 'Login realizado.');

        return Response::redirect('/conta', 302);
    }

    public function logoutPost(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('csrf_token'))) {
            return Response::redirect('/', 302);
        }
        SessionFacade::logout();
        SessionFacade::start();
        SessionFacade::flash('success', 'Você saiu da conta.');

        return Response::redirect('/', 302);
    }

    public function recuperarGet(Request $request): Response
    {
        $body = $this->messagesHtml();
        $body .= '<div class="page-auth">
<div class="card form-card">
<h1 class="form-card-title">Recuperar senha</h1>
<p class="form-lead">Informe seu e-mail. Se existir cadastro, enviaremos um link para redefinir a senha.</p>
<div id="form-recuperar-global" class="form-msg-success" role="status" aria-live="polite" style="display:none"></div>
<form id="form-recuperar" method="post" action="' . Html::u('/recuperar-senha') . '" novalidate>
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="rec-email">E-mail</label>
    <input id="rec-email" type="email" name="email" autocomplete="email">
    <div class="field-error" data-error-for="email"></div>
  </div>
  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem">
    <span class="btn-spinner spinner" hidden aria-hidden="true"></span>
    <span class="btn-label">Enviar link</span>
  </button>
</form>
<p class="link-row"><a href="' . Html::u('/login') . '">Voltar ao login</a></p>
</div>
</div>';

        return Response::html(Html::layout('Recuperar senha', $body, $this->csrf->token()));
    }

    public function recuperarPost(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/recuperar-senha', 302);
        }

        $result = $this->auth->requestPasswordReset((string) $request->input('email', ''));
        if (!$result['ok'] && ($result['error'] ?? '') === 'rate_limit') {
            SessionFacade::flash('error', 'Aguarde antes de solicitar novamente.');

            return Response::redirect('/recuperar-senha', 302);
        }

        SessionFacade::flash('success', 'Se o e-mail existir em nossa base, enviaremos instruções em instantes.');

        return Response::redirect('/login', 302);
    }

    public function redefinirGet(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $body = $this->messagesHtml();
        $body .= '<div class="page-auth">
<div class="card form-card">
<h1 class="form-card-title">Nova senha</h1>
<p class="form-lead">Defina uma senha forte para sua conta.</p>
<div id="form-redefinir-global" class="form-global-error" role="alert" aria-live="polite"></div>
<form id="form-redefinir" method="post" action="' . Html::u('/redefinir-senha') . '" novalidate>
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="token" value="' . Html::escape($token) . '">
  <div class="field">
    <label for="red-senha">Nova senha</label>
    <input id="red-senha" type="password" name="nova_senha" autocomplete="new-password">
    <p class="field-hint">Mínimo 8 caracteres, com maiúscula, minúscula e número.</p>
    <div class="field-error" data-error-for="nova_senha"></div>
  </div>
  <div class="field">
    <label for="red-senha2">Confirmar senha</label>
    <input id="red-senha2" type="password" name="nova_senha_confirmacao" autocomplete="new-password">
    <div class="field-error" data-error-for="nova_senha_confirmacao"></div>
  </div>
  <div class="field-error" data-error-for="token"></div>
  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem">
    <span class="btn-spinner spinner" hidden aria-hidden="true"></span>
    <span class="btn-label">Redefinir</span>
  </button>
</form>
</div>
</div>';

        return Response::html(Html::layout('Redefinir senha', $body, $this->csrf->token()));
    }

    public function redefinirPost(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/login', 302);
        }

        $result = $this->auth->resetPassword(
            (string) $request->input('token', ''),
            (string) $request->input('nova_senha', ''),
            (string) $request->input('nova_senha_confirmacao', '')
        );

        if (!$result['ok']) {
            $errs = $result['errors'] ?? [];
            SessionFacade::flash('error', implode(' ', $errs));

            return Response::redirect('/redefinir-senha?token=' . urlencode((string) $request->input('token', '')), 302);
        }

        SessionFacade::flash('success', 'Senha redefinida. Faça login com a nova senha.');

        return Response::redirect('/login', 302);
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
