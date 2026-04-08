<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BasePath;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ChurchRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\Csrf;
use App\Service\GeoLocationService;
use App\Service\ViaCepClient;
use App\Session\SessionFacade;
use App\Util\PerfilUuid;
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
        $body .= '<section class="mb-3">
<p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Perfil</p>
<h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Meu perfil igreja</h1>
<p class="mt-2 text-sm text-on-surface-variant">Atualize o perfil público da igreja para aparecer corretamente na busca.</p>
</section>
<div class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<p class="mt-1 text-sm text-on-surface-variant">Você está criando o <strong>perfil público da sua igreja</strong>: ele será exibido na busca para outras pessoas encontrarem e entrarem em contato. Revise os dados com cuidado; o que salvar aqui é o que representa a igreja no site.</p>
<p class="field-hint hint-account-contact"><strong>E-mail de contato:</strong> ' . $emailConta . ' — vem da sua conta; para alterar, use <a href="' . Html::u('/conta') . '">Minha conta</a>.</p>
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
  <button type="submit" class="mt-2 inline-flex items-center justify-center rounded-xl bg-primary px-6 py-3 font-semibold text-white">Salvar perfil igreja</button>
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
        $param = trim((string) $request->route('uuid', ''));
        $row = $this->resolvePublicChurchProfile($param);
        if ($row === null) {
            return Response::html('<h1>404</h1><p>Perfil não encontrado.</p>', 404);
        }

        if ($param !== '' && ctype_digit($param)) {
            $canonical = (string) ($row['perfil_uuid'] ?? '');
            if ($canonical !== '') {
                return Response::redirect(
                    BasePath::url('/perfil/igreja/' . rawurlencode($canonical)),
                    301
                );
            }
        }

        $id = (int) $row['id'];
        $viewerId = SessionFacade::userId();
        $body = $this->publicChurchStitchBodyHtml($row, $id, $viewerId);
        $extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />';
        $extraFooter = '<script>(function(){var b=document.getElementById("btn-church-profile-share");if(!b)return;b.addEventListener("click",function(){var u=location.href;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){alert("Link copiado.");}).catch(function(){prompt("Copie o link:",u);});}else{prompt("Copie o link:",u);}});})();</script>';

        return Response::html(Html::layout('Igreja', $body, $this->csrf->token(), $extraHead, $extraFooter));
    }

    public function publicApiGet(Request $request): Response
    {
        $row = $this->resolvePublicChurchProfile(trim((string) $request->route('uuid', '')));
        if ($row === null) {
            return Response::json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Igreja não encontrada.'],
            ], 404);
        }

        return Response::json([
            'id' => (int) $row['id'],
            'perfil_uuid' => (string) ($row['perfil_uuid'] ?? ''),
            'nome_igreja' => (string) $row['nome_igreja'],
            'cidade' => (string) $row['cidade'],
            'estado' => isset($row['estado']) && (string) $row['estado'] !== ''
                ? strtoupper((string) $row['estado'])
                : null,
            'verificado' => (int) $row['verificado'] === 1,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function publicChurchStitchBodyHtml(array $row, int $churchId, ?int $viewerId): string
    {
        $nome = (string) $row['nome_igreja'];
        $nomeEsc = Html::escape($nome);
        $cidadeEsc = Html::escape((string) $row['cidade']);
        $uf = isset($row['estado']) && (string) $row['estado'] !== ''
            ? strtoupper((string) $row['estado'])
            : '';
        $ufEsc = Html::escape($uf);
        $locLine = $cidadeEsc . ($uf !== '' ? ', ' . $ufEsc : '');
        $verified = (int) $row['verificado'] === 1;
        $verifiedHtml = $verified
            ? '<span class="flex items-center gap-1 text-sm font-medium text-[#c6e7ff]">'
            . '<span class="material-symbols-outlined fill text-sm">verified</span>'
            . '<span>Igreja verificada</span></span>'
            : '';
        $subtitle = 'Igreja na plataforma — ' . $locLine . '.';
        $heroBg = 'https://lh3.googleusercontent.com/aida-public/AB6AXuC3GsT6GmhRFoPKa_f59qPsAMWQ7POkQwwjtYYLtCY0mTs6T98UTGxI5VbfQZr13Voy26FZI0CQd_Ssrbydpz38Xmw1XmHIfiQvuzFb6Wr7EYS8WOOgFvWfb_BAqPyLD9gLdWo7RX4TfaEsq-TulavVPWBVW5hDi1Nmc-mthX6rJ2BkAPpQoOFEhTg_z6ItgCBjh1gWdxFzOzu8GAjMyw-aWEVfGejer3lPL0gNEoA8jnq7VoU-o6_DU8d4nxddWtUGiwfGnBqXNxc';

        $ctaPrimary = '';
        if ($viewerId === null) {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/login') . '"><span class="material-symbols-outlined">login</span> Entrar para conversar</a>';
        } elseif ((int) $row['usuario_id'] === $viewerId) {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/meu-perfil/igreja') . '"><span class="material-symbols-outlined">edit</span> Editar perfil</a>';
        } else {
            $ctaPrimary = '<a class="bg-white text-primary hover:bg-[#c6e7ff] transition-all px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2 shadow-xl" href="'
                . Html::u('/chat/iniciar') . '?tipo_entidade=igreja&entidade_id=' . $churchId
                . '"><span class="material-symbols-outlined">chat</span> Iniciar conversa</a>';
        }

        $shareBtn = '<button type="button" id="btn-church-profile-share" class="bg-primary-container/20 backdrop-blur-xl border border-white/20 text-white hover:bg-white/10 transition-all p-4 rounded-xl" aria-label="Copiar link do perfil">'
            . '<span class="material-symbols-outlined">share</span></button>';

        $avatarBlock = $this->churchAvatarHeroHtml($row, $nome);

        $aboutP1 = $nomeEsc . ' é uma igreja cadastrada na plataforma, com sede em ' . $cidadeEsc
            . ($uf !== '' ? ' (' . $ufEsc . ').' : '.');
        $aboutP2 = 'Líderes e ministros podem localizar esta igreja na busca e iniciar uma conversa na plataforma para alinhar oportunidades de ministério e contato.';

        $instPill = '<span class="inline-flex rounded-full bg-secondary-container px-2.5 py-1 text-xs font-semibold text-on-secondary-container">Perfil institucional</span>';

        $connectBlock = $this->churchConnectStitchHtml($viewerId, $row);

        $denuncia = $viewerId !== null
            ? '<p class="mt-8 text-sm text-on-surface-variant"><a class="text-primary hover:underline" href="'
                . Html::u('/denunciar') . '?alvo=' . (int) $row['usuario_id'] . '">Denunciar este perfil</a></p>'
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
            . '<span class="bg-tertiary-container/30 backdrop-blur-md text-[#ffdcc3] text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-[#ffdcc3]/20">Igreja</span>'
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
            . '<p class="text-2xl font-headline font-bold leading-tight mb-4">«Porque, onde estiverem dois ou três reunidos em meu nome, ali estou eu no meio deles.»</p>'
            . '<p class="text-[#ffdcc3]/80 text-sm">— Mateus 18:20</p>'
            . '</div>'
            . $connectBlock
            . '</div>'
            . '<div class="lg:col-span-8 space-y-12">'
            . '<div class="space-y-6">'
            . '<h2 class="text-3xl font-headline font-extrabold text-primary">Sobre ' . $nomeEsc . '</h2>'
            . '<div class="max-w-none text-on-surface/80 leading-relaxed space-y-4 text-lg">'
            . '<p>' . $aboutP1 . '</p><p>' . $aboutP2 . '</p>'
            . '</div>'
            . '<div class="flex flex-wrap gap-2 pt-2" aria-label="Tipo de perfil">' . $instPill . '</div>'
            . $denuncia
            . '</div></div></div></section></div>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function churchAvatarHeroHtml(array $row, string $nome): string
    {
        $raw = trim((string) ($row['usuario_foto_url'] ?? ''));
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

        $ini = $this->initialsFromChurchName($nome);

        return '<div class="relative w-32 h-32 md:w-48 md:h-48 rounded-xl border-4 border-surface-container-lowest shadow-2xl bg-primary-container flex items-center justify-center font-headline text-4xl md:text-5xl font-extrabold text-white" role="img" aria-label="'
            . Html::escape($nome) . '">' . Html::escape($ini) . '</div>';
    }

    private function initialsFromChurchName(string $nome): string
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
     * @param array<string, mixed> $row
     */
    private function churchConnectStitchHtml(?int $viewerId, array $row): string
    {
        $cidadeEsc = Html::escape((string) $row['cidade']);
        $uf = isset($row['estado']) && (string) $row['estado'] !== ''
            ? strtoupper((string) $row['estado'])
            : '';
        $locDetail = $cidadeEsc . ($uf !== '' ? ' — ' . Html::escape($uf) : '');

        $html = '<div class="bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10">'
            . '<h3 class="text-primary font-bold font-headline text-xl mb-6">Conexão e localização</h3>'
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
                . '<div><p class="font-bold text-on-surface">Contato e CEP</p>'
                . '<p class="text-sm text-on-surface-variant">Telefone, e-mail e CEP não são exibidos no perfil público. <a class="text-primary font-semibold hover:underline" href="'
                . Html::u('/login') . '">Entre na conta</a> para ver esses dados e usar a conversa na plataforma.</p></div></div>';
        } else {
            $html .= '<div class="flex items-start gap-4">'
                . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
                . '<span class="material-symbols-outlined">call</span></div>'
                . '<div><p class="font-bold text-on-surface">Telefone</p>';
            $tel = trim((string) ($row['telefone'] ?? ''));
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
                . '<div><p class="font-bold text-on-surface">E-mail de contato</p>';
            $email = trim((string) ($row['email_contato'] ?? ''));
            if ($email !== '') {
                $html .= '<p class="text-sm text-on-surface-variant"><a class="text-primary font-semibold hover:underline" href="' . Html::escape('mailto:' . $email) . '">' . Html::escape($email) . '</a></p>';
            } else {
                $html .= '<p class="text-sm text-on-surface-variant">Não informado.</p>';
            }
            $html .= '</div></div>';
            $cepRaw = trim((string) ($row['cep'] ?? ''));
            $cepDigits = preg_replace('/\D+/', '', $cepRaw);
            $cepDisplay = strlen($cepDigits) === 8
                ? substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5, 3)
                : $cepRaw;
            $html .= '<div class="flex items-start gap-4">'
                . '<div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-on-secondary-container shrink-0">'
                . '<span class="material-symbols-outlined">map</span></div>'
                . '<div><p class="font-bold text-on-surface">CEP</p>';
            if ($cepDisplay !== '') {
                $html .= '<p class="text-sm text-on-surface-variant">' . Html::escape($cepDisplay) . '</p>';
            } else {
                $html .= '<p class="text-sm text-on-surface-variant">Não informado.</p>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    private function resolvePublicChurchProfile(string $param): ?array
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

            return $this->churches->findPublicById($id);
        }
        if (!PerfilUuid::isValid($param)) {
            return null;
        }

        return $this->churches->findPublicByPerfilUuid($param);
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
