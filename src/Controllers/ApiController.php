<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\ReportService;
use App\Service\SearchService;
use App\Session\SessionFacade;

final class ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepository $users,
        private readonly ReportService $reports,
        private readonly SearchService $search
    ) {
    }

    public function health(Request $request): Response
    {
        return Response::json([
            'status' => 'ok',
            'project' => 'ide-anunciai',
            'stage' => 10,
        ]);
    }

    public function search(Request $request): Response
    {
        $result = $this->search->search(
            $this->nullableString($request->query('cep')),
            $this->nullableString($request->query('cidade')),
            $this->nullableString($request->query('estado')),
            (int) $request->query('raio_km', 20),
            (string) $request->query('tipo', 'ambos'),
            $this->nullableInt($request->query('habilidade_id')),
            (int) $request->query('pagina', 1),
            (int) $request->query('limite', 20)
        );

        return Response::json($result);
    }

    public function register(Request $request): Response
    {
        $consent = $request->input('consentimento') === true
            || $request->input('consentimento') === 1
            || $request->input('consentimento') === '1';

        $result = $this->auth->register(
            (string) $request->input('nome', ''),
            (string) $request->input('email', ''),
            (string) $request->input('senha', ''),
            (string) $request->input('senha_confirmacao', ''),
            $consent
        );

        if (!$result['ok']) {
            return Response::json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Dados inválidos.',
                    'fields' => $result['errors'] ?? [],
                ],
            ], 422);
        }

        SessionFacade::login((int) $result['user_id'], false);

        return Response::json(['id' => $result['user_id']], 201);
    }

    public function login(Request $request): Response
    {
        $result = $this->auth->login(
            (string) $request->input('email', ''),
            (string) $request->input('senha', '')
        );

        if (!$result['ok']) {
            return Response::json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => $result['error'] ?? 'Credenciais inválidas.',
                ],
            ], 401);
        }

        SessionFacade::login((int) $result['user']['id'], (int) ($result['user']['admin'] ?? 0) === 1);

        return Response::json([
            'id' => (int) $result['user']['id'],
            'nome' => $result['user']['nome'],
            'email' => $result['user']['email'],
        ]);
    }

    public function logout(Request $request): Response
    {
        SessionFacade::logout();

        return Response::json(['ok' => true]);
    }

    public function passwordForgot(Request $request): Response
    {
        $this->auth->requestPasswordReset((string) $request->input('email', ''));

        return Response::json(['ok' => true]);
    }

    public function passwordReset(Request $request): Response
    {
        $result = $this->auth->resetPassword(
            (string) $request->input('token', ''),
            (string) $request->input('nova_senha', ''),
            (string) $request->input('nova_senha_confirmacao', '')
        );

        if (!$result['ok']) {
            return Response::json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Não foi possível redefinir.',
                    'fields' => $result['errors'] ?? [],
                ],
            ], 422);
        }

        return Response::json(['ok' => true]);
    }

    public function privacyExport(Request $request): Response
    {
        $uid = SessionFacade::userId();
        if ($uid === null) {
            return Response::json([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.'],
            ], 401);
        }

        $user = $this->users->findById($uid);
        if ($user === null) {
            return Response::json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Usuário não encontrado.'],
            ], 404);
        }

        unset($user['senha_hash'], $user['reset_token_hash']);

        return Response::json(['usuario' => $user]);
    }

    public function reportsPost(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::json([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Não autenticado.'],
            ], 401);
        }

        $targetId = (int) $request->input('usuario_alvo_id', 0);
        $descricao = (string) $request->input('descricao', '');
        $result = $this->reports->submit($userId, $targetId, $descricao);
        if (!$result['ok']) {
            return Response::json([
                'error' => [
                    'code' => $result['code'],
                    'message' => $result['message'],
                ],
            ], 422);
        }

        return Response::json(['id' => $result['id']], 201);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (!is_scalar($value) || (string) $value === '') {
            return null;
        }
        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }
}
