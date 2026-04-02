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
        $body .= '<h1>Criar conta</h1>
<form method="post" action="/cadastro">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <label>Nome completo <input type="text" name="nome" required maxlength="255"></label>
  <label>E-mail <input type="email" name="email" required maxlength="255"></label>
  <label>Senha <input type="password" name="senha" required autocomplete="new-password"></label>
  <label>Confirmar senha <input type="password" name="senha_confirmacao" required autocomplete="new-password"></label>
  <label><input type="checkbox" name="consentimento" value="1" required> Li e aceito a <a href="/privacidade" target="_blank">Política de Privacidade</a></label>
  <button type="submit">Cadastrar</button>
</form>
<p>Já tem conta? <a href="/login">Entrar</a></p>';

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
        $body .= '<h1>Entrar</h1>
<form method="post" action="/login">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <label>E-mail <input type="email" name="email" required autocomplete="username"></label>
  <label>Senha <input type="password" name="senha" required autocomplete="current-password"></label>
  <button type="submit">Entrar</button>
</form>
<p><a href="/recuperar-senha">Esqueci minha senha</a></p>
<p>Não tem conta? <a href="/cadastro">Cadastrar</a></p>';

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
        $body .= '<h1>Recuperar senha</h1>
<p>Informe seu e-mail. Se existir cadastro, enviaremos um link para redefinir a senha.</p>
<form method="post" action="/recuperar-senha">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <label>E-mail <input type="email" name="email" required></label>
  <button type="submit">Enviar link</button>
</form>
<p><a href="/login">Voltar ao login</a></p>';

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
        $body .= '<h1>Nova senha</h1>
<form method="post" action="/redefinir-senha">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="token" value="' . Html::escape($token) . '">
  <label>Nova senha <input type="password" name="nova_senha" required autocomplete="new-password"></label>
  <label>Confirmar senha <input type="password" name="nova_senha_confirmacao" required autocomplete="new-password"></label>
  <button type="submit">Redefinir</button>
</form>';

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
