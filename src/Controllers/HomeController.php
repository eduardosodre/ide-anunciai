<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\BrazilStates;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ProfessionalRepository;
use App\Security\Csrf;
use App\Session\SessionFacade;
use App\View\Html;

final class HomeController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly ProfessionalRepository $professionals
    ) {
    }

    public function home(Request $request): Response
    {
        $body = $this->flashMessages();
        if (SessionFacade::userId() !== null) {
            $body .= '<section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-primary-container px-6 py-10 text-white md:px-10">
<div class="grid gap-8 lg:grid-cols-12 lg:items-center">
<div class="lg:col-span-8">
<p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-white/80">Comunidade ativa</p>
<h1 class="font-headline text-4xl font-extrabold leading-tight md:text-6xl">Bem-vindo de volta</h1>
<p class="mt-4 max-w-2xl text-white/90">Siga para conversas, atualização de perfil e busca por ministros e igrejas próximos de você.</p>
<div class="mt-6 flex flex-wrap gap-3">
<a class="inline-flex items-center rounded-full bg-white px-6 py-3 font-semibold text-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="inline-flex items-center rounded-full border border-white/40 px-6 py-3 font-semibold text-white" href="' . Html::u('/chat') . '">Conversas</a>
<a class="inline-flex items-center rounded-full border border-white/40 px-6 py-3 font-semibold text-white" href="' . Html::u('/conta') . '">Minha conta</a>
</div>
</div>
<div class="lg:col-span-4">
<div class="rounded-xl bg-white/95 p-5 text-on-surface shadow-xl">
<h3 class="font-headline text-xl font-bold">Ações rápidas</h3>
<p class="mt-2 text-sm text-on-surface-variant">Gerencie seu perfil público e acompanhe solicitações de verificação.</p>
<a class="mt-4 inline-flex rounded-full border border-outline-variant px-5 py-2 text-sm font-semibold text-primary" href="' . Html::u('/verificacao') . '">Ir para verificação</a>
</div>
</div>
</div>
</section>';
        } else {
            $body .= '<section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-primary-container px-6 py-10 text-white md:px-10">
<div class="grid gap-8 lg:grid-cols-12 lg:items-center">
<div class="lg:col-span-7">
<p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-white/80">Digital Cathedral</p>
<h1 class="font-headline text-4xl font-extrabold leading-tight md:text-6xl">Conecte igrejas, ministros e profissionais</h1>
<p class="mt-4 max-w-2xl text-white/90">Encontre pessoas e comunidades por localização, com perfis verificados e primeiro contato por mensagem.</p>
<div class="mt-6 flex flex-wrap gap-3">
<a class="inline-flex items-center rounded-full bg-white px-6 py-3 font-semibold text-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="inline-flex items-center rounded-full bg-white px-6 py-3 font-semibold text-primary" href="' . Html::u('/cadastro') . '">Criar conta</a>
<a class="inline-flex items-center rounded-full border border-white/40 px-6 py-3 font-semibold text-white" href="' . Html::u('/login') . '">Entrar</a>
</div>
</div>
<div class="lg:col-span-5">
<div class="rounded-xl bg-white/95 p-5 text-on-surface shadow-xl">
<h3 class="font-headline text-xl font-bold">Busca inteligente</h3>
<p class="mt-2 text-sm text-on-surface-variant">Use CEP/cidade, raio em km e filtros para encontrar igrejas e ministros no contexto certo.</p>
</div>
</div>
</div>
</section>';
        }

        $body .= '<section class="mt-4 grid gap-4 md:grid-cols-3">
<article class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-sm"><h2 class="font-headline text-xl font-bold">Busca por raio</h2><p class="mt-2 text-sm text-on-surface-variant">CEP ou cidade + UF com ordenação por distância e filtros por tipo/habilidade.</p></article>
<article class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-sm"><h2 class="font-headline text-xl font-bold">Perfis confiáveis</h2><p class="mt-2 text-sm text-on-surface-variant">Fluxo de verificação com status e moderação para elevar a confiança da comunidade.</p></article>
<article class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-sm"><h2 class="font-headline text-xl font-bold">Contato contextual</h2><p class="mt-2 text-sm text-on-surface-variant">Converse com o perfil certo sem expor dados sensíveis para visitantes anônimos.</p></article>
</section>
<p class="mt-4 text-sm text-on-surface-variant"><a href="' . Html::u('/privacidade') . '">Política de Privacidade</a> · <a href="' . Html::u('/api/health') . '">Status da API</a></p>';

        return Response::html(Html::layout('Início', $body, $this->csrf->token()));
    }

    public function busca(Request $request): Response
    {
        $body = $this->flashMessages();
        $cep = trim((string) $request->query('cep', ''));
        $cidade = trim((string) $request->query('cidade', ''));
        $estado = strtoupper(trim((string) $request->query('estado', '')));
        if ($estado !== '' && !BrazilStates::isValidUf($estado)) {
            $estado = '';
        }
        $raio = (int) $request->query('raio_km', 20);
        $tipo = (string) $request->query('tipo', 'ambos');
        $habilidadeId = (int) $request->query('habilidade_id', 0);
        $skills = $this->professionals->allSkills();

        $body .= '<section class="mb-3">
<p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Busca geográfica</p>
<h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Encontre perfis por localização</h1>
<p class="mt-2 text-sm text-on-surface-variant">Informe <strong>CEP</strong> (usa cidade/UF do ViaCEP no centro da busca) ou <strong>cidade + estado (UF)</strong> para evitar homônimos. Resultados abaixo sem recarregar.</p>
</section>
<div class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<form id="form-busca" method="get" action="' . Html::u('/busca') . '">
  <div class="field">
    <label for="busca-cep">CEP</label>
    <input id="busca-cep" type="text" name="cep" maxlength="20" value="' . Html::escape($cep) . '" placeholder="ex.: 01310100" autocomplete="postal-code">
    <p class="field-hint">Somente números; com CEP válido, cidade e UF são obtidos automaticamente.</p>
  </div>
  <div class="field">
    <label for="busca-cidade">Cidade</label>
    <input id="busca-cidade" type="text" name="cidade" maxlength="255" value="' . Html::escape($cidade) . '" placeholder="ex.: Campinas" autocomplete="address-level2">
  </div>
  <div class="field">
    <label for="busca-estado">Estado (UF)</label>
    <select id="busca-estado" name="estado">
' . $this->ufSelectHtml($estado !== '' ? $estado : null) . '
    </select>
    <p class="field-hint">Recomendado ao buscar por cidade (ex.: há Campinas em SP e em PB). Com CEP preenchido, o centro da busca usa o UF do CEP.</p>
  </div>
  <div class="field">
    <label for="busca-raio">Raio (km)</label>
    <input id="busca-raio" type="text" name="raio_km" value="' . Html::escape((string) max(1, $raio)) . '">
  </div>
  <div class="field">
    <label for="busca-tipo">Tipo</label>
    <select id="busca-tipo" name="tipo">
      <option value="ambos"' . ($tipo === 'ambos' ? ' selected' : '') . '>Ambos</option>
      <option value="profissionais"' . ($tipo === 'profissionais' ? ' selected' : '') . '>Profissionais</option>
      <option value="igrejas"' . ($tipo === 'igrejas' ? ' selected' : '') . '>Igrejas</option>
    </select>
  </div>
  <div class="field">
    <label for="busca-hab">Habilidade (opcional)</label>
    <select id="busca-hab" name="habilidade_id">
      <option value="">Todas</option>'
      . $this->skillsOptionsHtml($skills, $habilidadeId) .
    '</select>
  </div>
  <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-6 py-3 font-semibold text-white">
    <span class="btn-spinner spinner" hidden aria-hidden="true"></span>
    <span class="btn-label">Buscar</span>
  </button>
</form>
</div>
<div id="busca-results" class="busca-results" aria-live="polite"></div>';

        return Response::html(Html::layout('Busca', $body, $this->csrf->token()));
    }

    public function privacidade(Request $request): Response
    {
        $body = '<h1>Política de Privacidade</h1>
<div class="card">
<h2 class="mt-0">Versão e aceite</h2>
<p>Versão <strong>1.0</strong> do documento de privacidade. O cadastro exige aceite explícito da política vigente (data e versão registradas na conta).</p>
<h2>Dados que tratamos</h2>
<p>Nome, e-mail, senha (armazenada apenas como hash), foto de perfil opcional, dados de perfis de ministro e igreja (incluindo localização quando informada), mensagens de chat e metadados necessários ao funcionamento do serviço.</p>
<h2>Finalidades</h2>
<p>Prestação da plataforma, autenticação, comunicação entre usuários, verificação de perfis quando aplicável, cumprimento de obrigações legais e melhoria da experiência (dentro do escopo do MVP).</p>
<h2>Seus direitos (LGPD)</h2>
<p>Você pode solicitar acesso aos dados vinculados à sua conta; o export em JSON está disponível para usuários autenticados via API documentada (<code>GET /api/privacy/export</code>).</p>
<h2>Texto legal completo</h2>
<p>Esta página resume o tratamento no MVP; o texto legal detalhado pode ser complementado pela equipe jurídica antes da produção.</p>
</div>';

        return Response::html(Html::layout('Política de Privacidade', $body, $this->csrf->token()));
    }

    private function flashMessages(): string
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

    private function skillsOptionsHtml(array $skills, int $selectedSkillId): string
    {
        $html = '';
        foreach ($skills as $skill) {
            $id = (int) ($skill['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $selected = $selectedSkillId === $id ? ' selected' : '';
            $label = Html::escape((string) ($skill['label'] ?? ''));
            $html .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
        }

        return $html;
    }

    private function ufSelectHtml(?string $selectedUf): string
    {
        $html = '<option value="">Selecione o UF (opcional)</option>';
        foreach (BrazilStates::map() as $uf => $nome) {
            $sel = ($selectedUf !== null && strtoupper($selectedUf) === $uf) ? ' selected' : '';
            $html .= '<option value="' . Html::escape($uf) . '"' . $sel . '>' . Html::escape($nome . ' (' . $uf . ')') . '</option>';
        }

        return $html;
    }
}
