<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\BrazilStates;
use App\Http\BasePath;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ProfessionalRepository;
use App\Repository\VerificationRepository;
use App\Security\Csrf;
use App\Service\GeoLocationService;
use App\Session\SessionFacade;
use App\Util\PerfilUuid;
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
        $p = $profile ?? [];

        $nomePublico = Html::escape((string) ($p['nome_publico'] ?? ''));
        $telefone = Html::escape((string) ($p['telefone'] ?? ''));
        $cidade = Html::escape((string) ($p['cidade'] ?? ''));
        $estadoSel = isset($p['estado']) && (string) $p['estado'] !== ''
            ? strtoupper((string) $p['estado'])
            : '';
        $selectedSkills = $p['habilidades'] ?? [];

        $body = $this->messagesHtml();
        $body .= '<section class="page-head-stitch">
<p class="hero-kicker">Perfil</p>
<h1 class="page-title">Meu perfil ministro</h1>
<p class="form-lead form-lead-left form-lead-topless">Atualize o perfil exibido na busca pública.</p>
</section>
<div class="card form-card account-shell-stitch">
<p class="form-lead form-lead-left">Este é o seu perfil público de ministro na busca. Outros usuários (por exemplo, líderes de igreja) poderão encontrá-lo e <strong>convidá-lo a atuar</strong> com o ministério deles, de acordo com as habilidades que você informar.</p>
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
    <p class="field-hint">Somente o município (sem UF no campo; o estado é selecionado abaixo).</p>
  </div>
  <div class="field">
    <label for="estado_ministro">Estado (UF)</label>
    <select id="estado_ministro" name="estado" required>
' . $this->ufSelectHtml($estadoSel !== '' ? $estadoSel : null) . '
    </select>
    <p class="field-hint">Evita ambiguidade na busca (cidades homônimas em estados diferentes).</p>
  </div>
  <div class="field">
    <label for="habilidades">Habilidades e dons</label>
    <select id="habilidades" class="select-multiple-stitch" name="habilidades[]" multiple required size="6">'
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
        $estadoRaw = strtoupper(trim((string) $request->input('estado', '')));
        $skillIds = $this->extractSkillIds($request->input('habilidades', []));

        $errors = [];
        if ($nomePublico === '') {
            $errors[] = 'Nome público é obrigatório.';
        }
        if ($cidade === '') {
            $errors[] = 'Cidade é obrigatória.';
        }
        if ($estadoRaw === '' || !BrazilStates::isValidUf($estadoRaw)) {
            $errors[] = 'Selecione um estado (UF) válido.';
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
            $estadoRaw,
            $validSkillIds
        );
        $coords = $this->geo->resolveFromCity($cidade, $estadoRaw);
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
        $param = trim((string) $request->route('uuid', ''));
        $profile = $this->resolvePublicProfessionalProfile($param);
        if ($profile === null) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        if ($param !== '' && ctype_digit($param)) {
            $canonical = (string) ($profile['perfil_uuid'] ?? '');
            if ($canonical !== '') {
                return Response::redirect(
                    BasePath::url('/perfil/profissional/' . rawurlencode($canonical)),
                    301
                );
            }
        }

        $professionalId = (int) $profile['id'];

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

        $loc = Html::escape((string) $profile['cidade']);
        if (isset($profile['estado']) && (string) $profile['estado'] !== '') {
            $loc .= ' — ' . Html::escape(strtoupper((string) $profile['estado']));
        }
        $privacyContact = $this->professionalPrivacyAndContactHtml($viewerId, $profile);
        $body = '<section class="rounded-2xl bg-gradient-to-br from-primary to-primary-container px-6 py-8 text-white">
<p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-white/80">Perfil público</p>
<h1 class="font-headline text-4xl font-extrabold md:text-6xl">' . Html::escape((string) $profile['nome_publico']) . $verifiedBadge . '</h1>
<p class="mt-3 text-white/90"><strong>Local:</strong> ' . $loc . '</p>
</section>
<div class="mt-4 rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm">
' . $privacyContact . '
<h2 class="font-headline text-2xl font-bold text-primary">Habilidades</h2>
<ul class="skills-list">' . $skillsHtml . '</ul>
<div class="profile-actions" aria-label="Contato e moderação">
' . $chatBlock . $denunciaBlock . '
</div>
</div>';

        return Response::html(Html::layout('Perfil profissional', $body, $this->csrf->token()));
    }

    public function publicApiGet(Request $request): Response
    {
        $profile = $this->resolvePublicProfessionalProfile(trim((string) $request->route('uuid', '')));
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
            'perfil_uuid' => (string) ($profile['perfil_uuid'] ?? ''),
            'nome_publico' => (string) $profile['nome_publico'],
            'cidade' => (string) $profile['cidade'],
            'estado' => isset($profile['estado']) && (string) $profile['estado'] !== ''
                ? strtoupper((string) $profile['estado'])
                : null,
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

    private function resolvePublicProfessionalProfile(string $param): ?array
    {
        $param = trim($param);
        if ($param === '') {
            return null;
        }
        if (ctype_digit($param)) {
            $id = (int) $param;
            if ($id <= 0) {
                return null;
            }

            return $this->professionals->findPublicById($id);
        }
        if (!PerfilUuid::isValid($param)) {
            return null;
        }

        return $this->professionals->findPublicByPerfilUuid($param);
    }

    private function ufSelectHtml(?string $selectedUf): string
    {
        $html = '<option value="">Selecione o estado</option>';
        foreach (BrazilStates::map() as $uf => $nome) {
            $sel = ($selectedUf !== null && strtoupper($selectedUf) === $uf) ? ' selected' : '';
            $html .= '<option value="' . Html::escape($uf) . '"' . $sel . '>' . Html::escape($nome . ' (' . $uf . ')') . '</option>';
        }

        return $html;
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

    private function professionalPrivacyAndContactHtml(?int $viewerId, array $profile): string
    {
        if ($viewerId === null) {
            return '<p class="profile-muted">Telefone e e-mail não são exibidos no perfil público; contato via conversa.</p>';
        }

        $html = '<p class="profile-muted">Telefone e e-mail abaixo são visíveis apenas para quem está logado. Prefira também a conversa na plataforma.</p>';
        $html .= $this->professionalMemberContactHtml($profile);

        return $html;
    }

    /** @param array<string, mixed> $profile */
    private function professionalMemberContactHtml(array $profile): string
    {
        $tel = trim((string) ($profile['telefone'] ?? ''));
        $email = trim((string) ($profile['usuario_email'] ?? ''));
        $parts = ['<div class="profile-contact-logged">', '<h2 class="profile-contact-title">Contato</h2>'];
        if ($tel !== '') {
            $telDigits = preg_replace('/\D+/', '', $tel);
            $telHref = $telDigits !== '' ? 'tel:' . $telDigits : '#';
            $parts[] = '<p><strong>Telefone:</strong> <a href="' . Html::escape($telHref) . '">' . Html::escape($tel) . '</a></p>';
        } else {
            $parts[] = '<p class="profile-muted"><strong>Telefone:</strong> não informado.</p>';
        }
        if ($email !== '') {
            $parts[] = '<p><strong>E-mail:</strong> <a href="' . Html::escape('mailto:' . $email) . '">' . Html::escape($email) . '</a></p>';
        } else {
            $parts[] = '<p class="profile-muted"><strong>E-mail:</strong> não informado na conta.</p>';
        }
        $parts[] = '</div>';

        return implode('', $parts);
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
