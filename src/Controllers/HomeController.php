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
            $body .= '<section class="stitch-hero-shell">
<div class="stitch-hero-copy">
<p class="hero-kicker">Comunidade ativa</p>
<h1>Bem-vindo de volta</h1>
<p class="hero-lead">Siga para conversas, atualização de perfil e busca por ministros e igrejas próximos de você.</p>
<div class="hero-actions">
<a class="btn btn-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="btn btn-secondary" href="' . Html::u('/chat') . '">Conversas</a>
<a class="btn btn-secondary" href="' . Html::u('/conta') . '">Minha conta</a>
</div>
</div>
<div class="stitch-hero-side">
<div class="stitch-highlight-card">
<h3>Ações rápidas</h3>
<p>Gerencie seu perfil público e acompanhe solicitações de verificação em poucos cliques.</p>
<a class="btn btn-secondary" href="' . Html::u('/verificacao') . '">Verificação</a>
</div>
</div>
</section>
<section class="stitch-bento">
  <article class="stitch-card"><h2>Busca por raio</h2><p>CEP ou cidade + UF com ordenação por distância e filtros por tipo/habilidade.</p></article>
  <article class="stitch-card"><h2>Perfis públicos</h2><p>Visual moderno com dados essenciais para visitante e informações adicionais para usuário logado.</p></article>
  <article class="stitch-card"><h2>Conversa segura</h2><p>Contato inicial dentro da plataforma com rastreabilidade de mensagens.</p></article>
</section>';
        } else {
            $body .= '<section class="stitch-hero-shell">
<div class="stitch-hero-copy">
<p class="hero-kicker">Digital Cathedral</p>
<h1>Conecte igrejas, ministros e profissionais</h1>
<p class="hero-lead">Encontre pessoas e comunidades por localização, com perfis verificados e primeiro contato por mensagem.</p>
<div class="hero-actions">
<a class="btn btn-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="btn btn-primary" href="' . Html::u('/cadastro') . '">Criar conta</a>
<a class="btn btn-secondary" href="' . Html::u('/login') . '">Entrar</a>
</div>
</div>
<div class="stitch-hero-side">
<div class="stitch-highlight-card">
<h3>Busca inteligente</h3>
<p>Use CEP/cidade, raio em km e filtros para encontrar igrejas e ministros no contexto certo.</p>
</div>
</div>
</section>
<section class="stitch-bento">
  <article class="stitch-card"><h2>Perfis confiáveis</h2><p>Fluxo de verificação com status e moderação para elevar a confiança da comunidade.</p></article>
  <article class="stitch-card"><h2>Contato contextual</h2><p>Converse com o perfil certo sem expor dados sensíveis para visitantes anônimos.</p></article>
  <article class="stitch-card"><h2>Privacidade LGPD</h2><p><a href="' . Html::u('/privacidade') . '">Leia a política</a> e veja como tratamos dados no MVP.</p></article>
</section>
<p class="hero-footnote"><a href="' . Html::u('/privacidade') . '">Política de Privacidade</a> · <a href="' . Html::u('/api/health') . '">Status da API</a></p>';
        }

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

        $body .= '<section class="page-head-stitch">
<p class="hero-kicker">Busca geográfica</p>
<h1 class="page-title">Encontre perfis por localização</h1>
<p class="form-lead form-lead-left form-lead-topless">Informe <strong>CEP</strong> (usa cidade/UF do ViaCEP no centro da busca) ou <strong>cidade + estado (UF)</strong> para evitar homônimos. Resultados abaixo sem recarregar.</p>
</section>
<div class="card form-card search-shell-stitch search-shell-stitch-strong">
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
  <button type="submit" class="btn btn-primary btn-block btn-spacing-sm">
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
