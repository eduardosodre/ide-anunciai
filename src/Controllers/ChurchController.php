<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ChurchRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\Csrf;
use App\Service\GeoLocationService;
use App\Service\ViaCepClient;
use App\Session\SessionFacade;
use App\View\Html;

final class ChurchController
{
    public function __construct(
        private readonly ChurchRepository $churches,
        private readonly UserRepository $users,
        private readonly VerificationRepository $verifications,
        private readonly GeoLocationService $geo,
        private readonly ViaCepClient $viaCep,
        private readonly Csrf $csrf
    ) {
    }

    public function meGet(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return Response::redirect('/login', 302);
        }

        $row = $this->churches->findByUserId($userId);

        $nome = Html::escape((string) ($row['nome_igreja'] ?? ''));
        $telefone = Html::escape((string) ($row['telefone'] ?? ''));
        $cep = Html::escape((string) ($row['cep'] ?? ''));
        $cidade = Html::escape((string) ($row['cidade'] ?? ''));
        $cnpj = Html::escape((string) ($row['cnpj'] ?? ''));
        $emailConta = Html::escape((string) $user['email']);

        $body = $this->messagesHtml();
        $body .= '<div class="card form-card">
<h1 class="page-title">Meu perfil igreja</h1>
<p class="form-lead" style="text-align:left">Você está criando o <strong>perfil público da sua igreja</strong>: ele será exibido na busca para outras pessoas encontrarem e entrarem em contato. Revise os dados com cuidado; o que salvar aqui é o que representa a igreja no site.</p>
<p class="field-hint" style="margin-top:-0.5rem;margin-bottom:1rem"><strong>E-mail de contato:</strong> ' . $emailConta . ' — vem da sua conta; para alterar, use <a href="' . Html::u('/conta') . '">Minha conta</a>.</p>
<form method="post" action="' . Html::u('/meu-perfil/igreja') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="nome_igreja">Nome da igreja</label>
    <input id="nome_igreja" type="text" name="nome_igreja" required maxlength="255" value="' . $nome . '">
  </div>
  <div class="field">
    <label for="telefone_igreja">Telefone (uso interno; não exibido publicamente como preferência de contato)</label>
    <input id="telefone_igreja" type="text" name="telefone" maxlength="50" value="' . $telefone . '">
  </div>
  <div class="field">
    <label for="cep">CEP</label>
    <input id="cep" type="text" name="cep" required maxlength="20" placeholder="00000-000" value="' . $cep . '">
  </div>
  <div class="field">
    <label for="cidade_igreja">Cidade</label>
    <input id="cidade_igreja" type="text" name="cidade" maxlength="255" value="' . $cidade . '" placeholder="Preenchida pelo CEP ou manualmente">
    <p class="field-hint">Com CEP válido, cidade e UF são obtidos pelo ViaCEP e gravados para a busca. Se falhar, informe a cidade manualmente.</p>
  </div>
  <div class="field">
    <label for="cnpj">CNPJ (opcional; pode ser exigido em verificações futuras)</label>
    <input id="cnpj" type="text" name="cnpj" maxlength="20" value="' . $cnpj . '">
  </div>
  <button type="submit" class="btn btn-primary">Salvar perfil igreja</button>
</form>
</div>';

        return Response::html(Html::layout('Meu perfil igreja', $body, $this->csrf->token()));
    }

    public function mePost(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/meu-perfil/igreja', 302);
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return Response::redirect('/login', 302);
        }

        $nomeIgreja = trim((string) $request->input('nome_igreja', ''));
        $telefone = trim((string) $request->input('telefone', ''));
        $cepRaw = trim((string) $request->input('cep', ''));
        $cidadeInput = trim((string) $request->input('cidade', ''));
        $cnpjRaw = trim((string) $request->input('cnpj', ''));

        $cepDigits = preg_replace('/\D/u', '', $cepRaw) ?? '';
        $cepFormatted = $cepDigits;
        if (strlen($cepDigits) === 8) {
            $cepFormatted = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5, 3);
        }

        $errors = [];
        if ($nomeIgreja === '') {
            $errors[] = 'Nome da igreja é obrigatório.';
        }
        if (strlen($cepDigits) !== 8) {
            $errors[] = 'Informe um CEP válido (8 dígitos).';
        }

        $cnpjDigits = null;
        if ($cnpjRaw !== '') {
            $cnpjDigits = preg_replace('/\D/u', '', $cnpjRaw) ?? '';
            if (strlen($cnpjDigits) !== 14) {
                $errors[] = 'CNPJ inválido (deve ter 14 dígitos).';
            }
        }

        $via = strlen($cepDigits) === 8 ? $this->viaCep->consultar($cepDigits) : null;
        $cidadeFinal = $cidadeInput;
        $estadoUf = null;
        if ($via !== null) {
            $loc = trim((string) ($via['localidade'] ?? ''));
            if ($loc !== '') {
                $cidadeFinal = $loc;
            }
            if (isset($via['uf']) && (string) $via['uf'] !== '') {
                $estadoUf = strtoupper(trim((string) $via['uf']));
            }
        }
        if (trim($cidadeFinal) === '') {
            $errors[] = 'Não foi possível obter a cidade pelo CEP; informe a cidade manualmente ou corrija o CEP.';
        }

        if ($errors !== []) {
            SessionFacade::flash('error', implode(' ', $errors));

            return Response::redirect('/meu-perfil/igreja', 302);
        }

        $emailContato = mb_strtolower(trim((string) $user['email']));

        $this->churches->saveOwnProfile(
            $userId,
            $nomeIgreja,
            $emailContato,
            $telefone === '' ? null : $telefone,
            $cepFormatted,
            $cidadeFinal,
            $estadoUf,
            $cnpjDigits
        );
        $coords = $this->geo->resolveFromCepOrCity($cepFormatted, $cidadeFinal, $estadoUf);
        $this->churches->updateCoordinatesByUserId(
            $userId,
            $coords !== null ? (float) $coords['latitude'] : null,
            $coords !== null ? (float) $coords['longitude'] : null
        );
        $updatedProfile = $this->churches->findByUserId($userId);
        if ($updatedProfile !== null && (int) $updatedProfile['pendente_revisao'] === 1) {
            $this->verifications->createRevisionRequestIfNeeded($userId, 'igreja', (int) $updatedProfile['id']);
        }

        SessionFacade::flash('success', 'Perfil igreja salvo com sucesso.');

        return Response::redirect('/meu-perfil/igreja', 302);
    }

    public function publicGet(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id <= 0) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        $row = $this->churches->findPublicById($id);
        if ($row === null) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        $badge = ((int) $row['verificado'] === 1)
            ? ' <span class="badge-verified">Verificado</span>'
            : '';

        $viewerId = SessionFacade::userId();
        $chatBlock = '';
        if ($viewerId === null) {
            $chatBlock = '<p class="profile-muted">Visitante: <a href="' . Html::u('/login') . '">Entre</a> para iniciar conversa.</p>';
        } elseif ((int) $row['usuario_id'] === $viewerId) {
            $chatBlock = '<p><em>Este é o seu perfil público.</em></p>';
        } else {
            $chatBlock = '<p><a class="btn btn-primary" href="' . Html::u('/chat/iniciar') . '?tipo_entidade=igreja&entidade_id=' . $id . '">Iniciar conversa</a></p>';
        }

        $denunciaBlock = $viewerId !== null
            ? '<p class="profile-muted"><a href="' . Html::u('/denunciar') . '?alvo=' . (int) $row['usuario_id'] . '">Denunciar este perfil</a></p>'
            : '<p class="profile-muted"><a href="' . Html::u('/login') . '">Entre</a> para denunciar.</p>';

        $locI = Html::escape((string) $row['cidade']);
        if (isset($row['estado']) && (string) $row['estado'] !== '') {
            $locI .= ' — ' . Html::escape(strtoupper((string) $row['estado']));
        }
        $body = '<div class="card">
<h1>' . Html::escape((string) $row['nome_igreja']) . $badge . '</h1>
<p><strong>Local:</strong> ' . $locI . '</p>
<p class="profile-muted">Telefone e e-mail não são exibidos no perfil público; contato via conversa.</p>
<div class="profile-actions" aria-label="Contato e moderação">
' . $chatBlock . $denunciaBlock . '
</div>
</div>';

        return Response::html(Html::layout('Igreja', $body, $this->csrf->token()));
    }

    public function publicApiGet(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id <= 0) {
            return Response::json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Igreja não encontrada.'],
            ], 404);
        }

        $row = $this->churches->findPublicById($id);
        if ($row === null) {
            return Response::json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Igreja não encontrada.'],
            ], 404);
        }

        return Response::json([
            'id' => (int) $row['id'],
            'nome_igreja' => (string) $row['nome_igreja'],
            'cidade' => (string) $row['cidade'],
            'estado' => isset($row['estado']) && (string) $row['estado'] !== ''
                ? strtoupper((string) $row['estado'])
                : null,
            'verificado' => (int) $row['verificado'] === 1,
        ]);
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
