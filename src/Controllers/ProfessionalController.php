<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ProfessionalRepository;
use App\Repository\VerificationRepository;
use App\Security\Csrf;
use App\Service\GeoLocationService;
use App\Session\SessionFacade;
use App\View\Html;

final class ProfessionalController
{
    public function __construct(
        private readonly ProfessionalRepository $professionals,
        private readonly VerificationRepository $verifications,
        private readonly GeoLocationService $geo,
        private readonly Csrf $csrf
    ) {
    }

    public function meGet(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        $profile = $this->professionals->findByUserId($userId);
        $skills = $this->professionals->allSkills();

        $nomePublico = Html::escape((string) ($profile['nome_publico'] ?? ''));
        $telefone = Html::escape((string) ($profile['telefone'] ?? ''));
        $cidade = Html::escape((string) ($profile['cidade'] ?? ''));
        $selectedSkills = $profile['habilidades'] ?? [];

        $body = $this->messagesHtml();
        $body .= '<div class="card form-card">
<h1 class="page-title">Meu perfil ministro</h1>
<p class="form-lead" style="text-align:left">Este é o seu perfil público de ministro na busca. Outros usuários (por exemplo, líderes de igreja) poderão encontrá-lo e <strong>convidá-lo a atuar</strong> com o ministério deles, de acordo com as habilidades que você informar.</p>
<form method="post" action="' . Html::u('/meu-perfil/ministro') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <div class="field">
    <label for="nome_publico">Nome público</label>
    <input id="nome_publico" type="text" name="nome_publico" required maxlength="255" value="' . $nomePublico . '">
  </div>
  <div class="field">
    <label for="telefone">Telefone</label>
    <input id="telefone" type="text" name="telefone" maxlength="50" value="' . $telefone . '">
  </div>
  <div class="field">
    <label for="cidade">Cidade</label>
    <input id="cidade" type="text" name="cidade" required maxlength="255" value="' . $cidade . '">
  </div>
  <div class="field">
    <label for="habilidades">Habilidades e dons</label>
    <select id="habilidades" name="habilidades[]" multiple required size="6" style="width:100%;max-width:none;padding:0.4rem;">'
            . $this->skillsOptionsHtml($skills, $selectedSkills) .
        '</select>
    <p class="field-hint">Segure Ctrl (Windows) ou Cmd (Mac) para selecionar mais de uma opção.</p>
  </div>
  <button type="submit" class="btn btn-primary">Salvar perfil ministro</button>
</form>
</div>';

        return Response::html(Html::layout('Meu perfil ministro', $body, $this->csrf->token()));
    }

    public function mePost(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/meu-perfil/ministro', 302);
        }

        $nomePublico = trim((string) $request->input('nome_publico', ''));
        $telefone = trim((string) $request->input('telefone', ''));
        $cidade = trim((string) $request->input('cidade', ''));
        $skillIds = $this->extractSkillIds($request->input('habilidades', []));

        $errors = [];
        if ($nomePublico === '') {
            $errors[] = 'Nome público é obrigatório.';
        }
        if ($cidade === '') {
            $errors[] = 'Cidade é obrigatória.';
        }
        if ($skillIds === []) {
            $errors[] = 'Selecione ao menos uma habilidade.';
        }

        $validSkillIds = $this->professionals->validSkillIds($skillIds);
        if ($skillIds !== [] && count($validSkillIds) !== count(array_unique($skillIds))) {
            $errors[] = 'Uma ou mais habilidades selecionadas são inválidas.';
        }

        if ($errors !== []) {
            SessionFacade::flash('error', implode(' ', $errors));

            return Response::redirect('/meu-perfil/ministro', 302);
        }

        $this->professionals->saveOwnProfile(
            $userId,
            $nomePublico,
            $telefone === '' ? null : $telefone,
            $cidade,
            $validSkillIds
        );
        $coords = $this->geo->resolveFromCity($cidade);
        $this->professionals->updateCoordinatesByUserId(
            $userId,
            $coords !== null ? (float) $coords['latitude'] : null,
            $coords !== null ? (float) $coords['longitude'] : null
        );
        $updatedProfile = $this->professionals->findByUserId($userId);
        if ($updatedProfile !== null && (int) $updatedProfile['pendente_revisao'] === 1) {
            $this->verifications->createRevisionRequestIfNeeded($userId, 'ministro', (int) $updatedProfile['id']);
        }
        SessionFacade::flash('success', 'Perfil ministro salvo com sucesso.');

        return Response::redirect('/meu-perfil/ministro', 302);
    }

    public function publicGet(Request $request): Response
    {
        $professionalId = (int) $request->route('id', 0);
        if ($professionalId <= 0) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        $profile = $this->professionals->findPublicById($professionalId);
        if ($profile === null) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        $verifiedBadge = ((int) $profile['verificado'] === 1)
            ? ' <span class="badge-verified">Verificado</span>'
            : '';
        $skills = $profile['habilidades'] ?? [];
        $skillsHtml = $skills === []
            ? '<li>Sem habilidades cadastradas.</li>'
            : implode('', array_map(
                static fn (array $item): string => '<li>' . Html::escape((string) $item['label']) . '</li>',
                $skills
            ));

        $viewerId = SessionFacade::userId();
        $chatBlock = '';
        if ($viewerId === null) {
            $chatBlock = '<p class="profile-muted">Visitante: <a href="' . Html::u('/login') . '">Entre</a> para iniciar conversa.</p>';
        } elseif ((int) $profile['usuario_id'] === $viewerId) {
            $chatBlock = '<p><em>Este é o seu perfil público.</em></p>';
        } else {
            $chatBlock = '<p><a class="btn btn-primary" href="' . Html::u('/chat/iniciar') . '?tipo_entidade=ministro&entidade_id=' . $professionalId . '">Iniciar conversa</a></p>';
        }

        $denunciaBlock = $viewerId !== null
            ? '<p class="profile-muted"><a href="' . Html::u('/denunciar') . '?alvo=' . (int) $profile['usuario_id'] . '">Denunciar este perfil</a></p>'
            : '<p class="profile-muted"><a href="' . Html::u('/login') . '">Entre</a> para denunciar.</p>';

        $body = '<div class="card">
<h1>' . Html::escape((string) $profile['nome_publico']) . $verifiedBadge . '</h1>
<p><strong>Cidade:</strong> ' . Html::escape((string) $profile['cidade']) . '</p>
<p class="profile-muted">Telefone e e-mail não são exibidos no perfil público; contato via conversa.</p>
<h2>Habilidades</h2>
<ul class="skills-list">' . $skillsHtml . '</ul>
<div class="profile-actions" aria-label="Contato e moderação">
' . $chatBlock . $denunciaBlock . '
</div>
</div>';

        return Response::html(Html::layout('Perfil profissional', $body, $this->csrf->token()));
    }

    public function publicApiGet(Request $request): Response
    {
        $professionalId = (int) $request->route('id', 0);
        if ($professionalId <= 0) {
            return Response::json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Profissional não encontrado.',
                ],
            ], 404);
        }

        $profile = $this->professionals->findPublicById($professionalId);
        if ($profile === null) {
            return Response::json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Profissional não encontrado.',
                ],
            ], 404);
        }

        return Response::json([
            'id' => (int) $profile['id'],
            'nome_publico' => (string) $profile['nome_publico'],
            'cidade' => (string) $profile['cidade'],
            'verificado' => (int) $profile['verificado'] === 1,
            'habilidades' => array_map(
                static fn (array $item): array => [
                    'id' => (int) $item['id'],
                    'codigo' => (string) $item['codigo'],
                    'label' => (string) $item['label'],
                ],
                $profile['habilidades'] ?? []
            ),
        ]);
    }

    private function skillsOptionsHtml(array $skills, array $selected): string
    {
        $selectedMap = array_flip(array_map('intval', $selected));
        $html = '';

        foreach ($skills as $skill) {
            $skillId = (int) $skill['id'];
            $isSelected = isset($selectedMap[$skillId]) ? ' selected' : '';
            $html .= '<option value="' . $skillId . '"' . $isSelected . '>'
                . Html::escape((string) $skill['label']) . '</option>';
        }

        return $html;
    }

    private function extractSkillIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
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
