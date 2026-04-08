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
        $body .= '<section class="mb-3">
<p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Perfil</p>
<h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Meu perfil ministro</h1>
<p class="mt-2 text-sm text-on-surface-variant">Atualize o perfil exibido na busca pública.</p>
</section>
<div class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<p class="mt-1 text-sm text-on-surface-variant">Este é o seu perfil público de ministro na busca. Outros usuários (por exemplo, líderes de igreja) poderão encontrá-lo e <strong>convidá-lo a atuar</strong> com o ministério deles, de acordo com as habilidades que você informar.</p>
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
  <button type="submit" class="mt-2 inline-flex items-center justify-center rounded-xl bg-primary px-6 py-3 font-semibold text-white">Salvar perfil ministro</button>
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

        $viewerId = SessionFacade::userId();
        $body = $this->publicProfessionalStitchBodyHtml($profile, $professionalId, $viewerId);
        $extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />';
        $extraFooter = '<script>(function(){var b=document.getElementById("btn-profile-share");if(!b)return;b.addEventListener("click",function(){var u=location.href;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){alert("Link copiado.");}).catch(function(){prompt("Copie o link:",u);});}else{prompt("Copie o link:",u);}});})();</script>';

        return Response::html(Html::layout('Perfil profissional', $body, $this->csrf->token(), $extraHead, $extraFooter));
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

    /**
     * @param array<string, mixed> $profile
     */
    private function publicProfessionalStitchBodyHtml(array $profile, int $professionalId, ?int $viewerId): string
    {
        $nome = (string) $profile['nome_publico'];
        $nomeEsc = Html::escape($nome);
        $cidadeEsc = Html::escape((string) $profile['cidade']);
        $uf = isset($profile['estado']) && (string) $profile['estado'] !== ''
            ? strtoupper((string) $profile['estado'])
            : '';
        $ufEsc = Html::escape($uf);
        $locLine = $cidadeEsc . ($uf !== '' ? ', ' . $ufEsc : '');
        $skills = $profile['habilidades'] ?? [];
        $roleTag = $skills !== []
            ? Html::escape((string) ($skills[0]['label'] ?? 'Ministro'))
            : 'Ministro';
        $verified = (int) $profile['verificado'] === 1;
        $verifiedHtml = $verified
            ? '<span class="flex items-center gap-1 text-sm font-medium text-[#c6e7ff]">'
            . '<span class="material-symbols-outlined fill text-sm">verified</span>'
            . '<span>Ministro verificado</span></span>'
            : '';
        $subtitle = 'Ministro na plataforma — ' . $locLine . '.';
        $heroBg = 'https://lh3.googleusercontent.com/aida-public/AB6AXuC3GsT6GmhRFoPKa_f59qPsAMWQ7POkQwwjtYYLtCY0mTs6T98UTGxI5VbfQZr13Voy26FZI0CQd_Ssrbydpz38Xmw1XmHIfiQvuzFb6Wr7EYS8WOOgFvWfb_BAqPyLD9gLdWo7RX4TfaEsq-TulavVPWBVW5hDi1Nmc-mthX6rJ2BkAPpQoOFEhTg_z6ItgCBjh1gWdxFzOzu8GAjMyw-aWEVfGejer3lPL0gNEoA8jnq7VoU-o6_DU8d4nxddWtUGiwfGnBqXNxc';

        $ctaPrimary = '';
        if ($viewerId === null) {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/login') . '"><span class="material-symbols-outlined">login</span> Entrar para conversar</a>';
        } elseif ((int) $profile['usuario_id'] === $viewerId) {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/meu-perfil/ministro') . '"><span class="material-symbols-outlined">edit</span> Editar perfil</a>';
        } else {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/chat/iniciar') . '?tipo_entidade=ministro&entidade_id=' . $professionalId
                . '"><span class="material-symbols-outlined">chat</span> Iniciar conversa</a>';
        }

        $shareBtn = '<button type="button" id="btn-profile-share" class="bg-primary-container/20 backdrop-blur-xl border border-white/20 text-white hover:bg-white/10 transition-all p-4 rounded-xl" aria-label="Copiar link do perfil">'
            . '<span class="material-symbols-outlined">share</span></button>';

        $avatarBlock = $this->professionalAvatarHeroHtml($profile, $nome);

        $aboutP1 = $nomeEsc . ' é ministro cadastrado na plataforma, com localização em ' . $cidadeEsc
            . ($uf !== '' ? ' (' . $ufEsc . ').' : '.');
        $skillLabels = array_map(
            static fn (array $item): string => Html::escape((string) $item['label']),
            $skills
        );
        $aboutP2 = $skillLabels !== []
            ? 'As habilidades e dons declarados incluem: ' . implode(', ', $skillLabels) . '.'
            : 'As habilidades ministeriais serão exibidas aqui quando cadastradas.';

        $skillPills = '';
        foreach ($skills as $item) {
            $skillPills .= '<span class="inline-flex rounded-full bg-secondary-container px-2.5 py-1 text-xs font-semibold text-on-secondary-container">'
                . Html::escape((string) $item['label']) . '</span>';
        }
        if ($skillPills === '') {
            $skillPills = '<span class="text-sm text-on-surface-variant">Nenhuma habilidade listada ainda.</span>';
        }

        $connectBlock = $this->professionalConnectStitchHtml($viewerId, $profile);

        $denuncia = $viewerId !== null
            ? '<p class="mt-8 text-sm text-on-surface-variant"><a class="text-primary hover:underline" href="'
                . Html::u('/denunciar') . '?alvo=' . (int) $profile['usuario_id'] . '">Denunciar este perfil</a></p>'
            : '<p class="mt-8 text-sm text-on-surface-variant"><a class="text-primary hover:underline" href="' . Html::u('/login') . '">Entre</a> para denunciar conteúdo inadequado.</p>';

        return '<div class="profile-public-fullbleed bg-surface text-on-surface font-body">'
            . '<section class="relative w-full h-[400px] md:h-[500px] overflow-hidden">'
            . '<div class="absolute inset-0 bg-gradient-to-b from-primary/40 to-primary z-10"></div>'
            . '<img class="absolute inset-0 w-full h-full object-cover" src="' . Html::escape($heroBg) . '" alt="" loading="lazy" decoding="async">'
            . '<div class="relative z-20 h-full max-w-7xl mx-auto px-6 flex flex-col justify-end pb-12">'
            . '<div class="flex flex-col md:flex-row md:items-end gap-6 md:gap-10">'
            . '<div class="relative group">'
            . '<div class="absolute -inset-1 bg-gradient-to-tr from-tertiary-container to-primary rounded-xl blur opacity-25 group-hover:opacity-40 transition duration-1000"></div>'
            . $avatarBlock
            . '</div>'
            . '<div class="flex-1 text-white pb-2 min-w-0">'
            . '<div class="flex flex-wrap items-center gap-3 mb-2">'
            . '<span class="bg-tertiary-container/30 backdrop-blur-md text-[#ffdcc3] text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-[#ffdcc3]/20">'
            . $roleTag . '</span>'
            . $verifiedHtml
            . '</div>'
            . '<h1 class="text-5xl md:text-7xl font-extrabold tracking-tighter font-headline leading-none text-shadow-premium break-words">' . $nomeEsc . '</h1>'
            . '<p class="text-xl md:text-2xl font-light text-[#c6e7ff]/90 mt-4 max-w-2xl">' . Html::escape($subtitle) . '</p>'
            . '</div>'
            . '<div class="flex flex-wrap gap-3 mb-2 md:justify-end">' . $ctaPrimary . $shareBtn . '</div>'
            . '</div></div></section>'
            . '<section class="max-w-7xl mx-auto px-6 py-12">'
            . '<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">'
            . '<div class="lg:col-span-4 space-y-8">'
            . '<div class="bg-tertiary-container text-white p-8 rounded-xl shadow-lg relative overflow-hidden group">'
            . '<div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">'
            . '<span class="material-symbols-outlined text-6xl">format_quote</span></div>'
            . '<h3 class="text-[#ffdcc3] font-bold uppercase tracking-widest text-xs mb-4">Palavra</h3>'
            . '<p class="text-2xl font-headline font-bold leading-tight mb-4">«Os céus declaram a glória de Deus; o firmamento proclama a obra de suas mãos.»</p>'
            . '<p class="text-[#ffdcc3]/80 text-sm">— Salmos 19:1</p>'
            . '</div>'
            . $connectBlock
            . '</div>'
            . '<div class="lg:col-span-8 space-y-12">'
            . '<div class="space-y-6">'
            . '<h2 class="text-3xl font-headline font-extrabold text-primary">Sobre ' . $nomeEsc . '</h2>'
            . '<div class="max-w-none text-on-surface/80 leading-relaxed space-y-4 text-lg">'
            . '<p>' . $aboutP1 . '</p><p>' . $aboutP2 . '</p>'
            . '</div>'
            . '<div class="flex flex-wrap gap-2 pt-2" aria-label="Habilidades">' . $skillPills . '</div>'
            . $denuncia
            . '</div></div></div></section></div>';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function professionalAvatarHeroHtml(array $profile, string $nome): string
    {
        $raw = trim((string) ($profile['usuario_foto_url'] ?? ''));
        $url = '';
        if ($raw !== '') {
            $url = (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://'))
                ? $raw
                : Html::u($raw);
        }
        if ($url !== '') {
            return '<img class="relative w-32 h-32 md:w-48 md:h-48 rounded-xl object-cover border-4 border-surface-container-lowest shadow-2xl" src="'
                . Html::escape($url) . '" alt="' . Html::escape($nome) . '">';
        }

        $ini = $this->initialsFromPublicName($nome);

        return '<div class="relative w-32 h-32 md:w-48 md:h-48 rounded-xl border-4 border-surface-container-lowest shadow-2xl bg-primary-container flex items-center justify-center font-headline text-4xl md:text-5xl font-extrabold text-white" role="img" aria-label="'
            . Html::escape($nome) . '">' . Html::escape($ini) . '</div>';
    }

    private function initialsFromPublicName(string $nome): string
    {
        $parts = preg_split('/\s+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1, 'UTF-8') : '';
        $out = mb_strtoupper($first . $second, 'UTF-8');

        return $out !== '' ? $out : '?';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function professionalConnectStitchHtml(?int $viewerId, array $profile): string
    {
        $cidadeEsc = Html::escape((string) $profile['cidade']);
        $uf = isset($profile['estado']) && (string) $profile['estado'] !== ''
            ? strtoupper((string) $profile['estado'])
            : '';
        $locDetail = $cidadeEsc . ($uf !== '' ? ' — ' . Html::escape($uf) : '');

        $html = '<div class="bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10">'
            . '<h3 class="text-primary font-bold font-headline text-xl mb-6">Conexão e ministério</h3>'
            . '<div class="space-y-6">'
            . '<div class="flex items-start gap-4">'
            . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
            . '<span class="material-symbols-outlined">location_on</span></div>'
            . '<div><p class="font-bold text-on-surface">Local</p>'
            . '<p class="text-sm text-on-surface-variant">' . $locDetail . '</p></div></div>';

        if ($viewerId === null) {
            $html .= '<div class="flex items-start gap-4">'
                . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
                . '<span class="material-symbols-outlined">lock</span></div>'
                . '<div><p class="font-bold text-on-surface">Contato</p>'
                . '<p class="text-sm text-on-surface-variant">Telefone e e-mail não são exibidos no perfil público. <a class="text-primary font-semibold hover:underline" href="'
                . Html::u('/login') . '">Entre na conta</a> para ver dados de contato de ministros e usar a conversa na plataforma.</p></div></div>';
        } else {
            $html .= '<div class="flex items-start gap-4">'
                . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
                . '<span class="material-symbols-outlined">call</span></div>'
                . '<div><p class="font-bold text-on-surface">Telefone</p>';
            $tel = trim((string) ($profile['telefone'] ?? ''));
            if ($tel !== '') {
                $telDigits = preg_replace('/\D+/', '', $tel);
                $telHref = $telDigits !== '' ? 'tel:' . $telDigits : '#';
                $html .= '<p class="text-sm text-on-surface-variant"><a class="text-primary font-semibold hover:underline" href="' . Html::escape($telHref) . '">' . Html::escape($tel) . '</a></p>';
            } else {
                $html .= '<p class="text-sm text-on-surface-variant">Não informado.</p>';
            }
            $html .= '</div></div>';
            $html .= '<div class="flex items-start gap-4">'
                . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
                . '<span class="material-symbols-outlined">mail</span></div>'
                . '<div><p class="font-bold text-on-surface">E-mail</p>';
            $email = trim((string) ($profile['usuario_email'] ?? ''));
            if ($email !== '') {
                $html .= '<p class="text-sm text-on-surface-variant"><a class="text-primary font-semibold hover:underline" href="' . Html::escape('mailto:' . $email) . '">' . Html::escape($email) . '</a></p>';
            } else {
                $html .= '<p class="text-sm text-on-surface-variant">Não informado na conta.</p>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div></div>';

        return $html;
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
