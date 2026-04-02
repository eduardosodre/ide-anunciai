<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ProfessionalRepository;
use App\Security\Csrf;
use App\Service\SearchService;
use App\Session\SessionFacade;
use App\View\Html;

final class HomeController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly SearchService $search,
        private readonly ProfessionalRepository $professionals
    )
    {
    }

    public function home(Request $request): Response
    {
        $body = $this->flashMessages();
        if (SessionFacade::userId() !== null) {
            $body .= '<section class="hero hero-compact">
<h1>Olá de volta</h1>
<p class="hero-lead">Use o menu para conversas, perfis e busca. Atalhos rápidos:</p>
<div class="hero-actions">
<a class="btn btn-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="btn btn-secondary" href="' . Html::u('/chat') . '">Conversas</a>
<a class="btn btn-secondary" href="' . Html::u('/conta') . '">Minha conta</a>
</div>
</section>';
        } else {
            $body .= '<section class="hero">
<h1>Conecte igrejas, ministros e profissionais</h1>
<p class="hero-lead">Networking religioso: encontre quem precisa perto de você — busca por localização e habilidades, perfis verificados e primeiro contato por mensagem.</p>
<div class="hero-actions">
<a class="btn btn-primary" href="' . Html::u('/busca') . '">Buscar perfis</a>
<a class="btn btn-primary" href="' . Html::u('/cadastro') . '">Criar conta</a>
<a class="btn btn-secondary" href="' . Html::u('/login') . '">Entrar</a>
</div>
<p style="margin-top:1.25rem;font-size:0.9rem;color:var(--muted)"><a href="' . Html::u('/privacidade') . '">Política de Privacidade</a> · <a href="' . Html::u('/api/health') . '">Status da API</a></p>
</section>';
        }

        return Response::html(Html::layout('Início', $body, $this->csrf->token()));
    }

    public function busca(Request $request): Response
    {
        $body = $this->flashMessages();
        $cep = trim((string) $request->query('cep', ''));
        $cidade = trim((string) $request->query('cidade', ''));
        $raio = (int) $request->query('raio_km', 20);
        $tipo = (string) $request->query('tipo', 'ambos');
        $habilidadeId = (int) $request->query('habilidade_id', 0);
        $pagina = (int) $request->query('pagina', 1);
        $limite = (int) $request->query('limite', 20);
        $skills = $this->professionals->allSkills();

        $body .= '<h1>Busca</h1>
<div class="card">
<form method="get" action="' . Html::u('/busca') . '">
  <label>CEP <input type="text" name="cep" maxlength="20" value="' . Html::escape($cep) . '" placeholder="ex.: 01310100"></label>
  <label>Cidade <input type="text" name="cidade" maxlength="255" value="' . Html::escape($cidade) . '" placeholder="ex.: São Paulo"></label>
  <label>Raio (km) <input type="text" name="raio_km" value="' . Html::escape((string) max(1, $raio)) . '"></label>
  <label>Tipo
    <select name="tipo">
      <option value="ambos"' . ($tipo === 'ambos' ? ' selected' : '') . '>Ambos</option>
      <option value="profissionais"' . ($tipo === 'profissionais' ? ' selected' : '') . '>Profissionais</option>
      <option value="igrejas"' . ($tipo === 'igrejas' ? ' selected' : '') . '>Igrejas</option>
    </select>
  </label>
  <label>Habilidade (opcional)
    <select name="habilidade_id">
      <option value="">Todas</option>'
      . $this->skillsOptionsHtml($skills, $habilidadeId) .
    '</select>
  </label>
  <button type="submit">Buscar</button>
</form>
</div>';

        if ($cep !== '' || $cidade !== '') {
            $result = $this->search->search(
                $cep === '' ? null : $cep,
                $cidade === '' ? null : $cidade,
                $raio,
                $tipo,
                $habilidadeId > 0 ? $habilidadeId : null,
                $pagina,
                $limite
            );
            $body .= '<div class="card"><p><strong>' . Html::escape((string) ($result['meta']['message'] ?? '')) . '</strong></p>';
            $items = $result['items'] ?? [];
            if ($items === []) {
                $body .= '<p>Nenhum resultado.</p></div>';
            } else {
                $body .= '<ul class="result-list">';
                foreach ($items as $item) {
                    $badge = !empty($item['verificado']) ? ' <small>[Verificado]</small>' : '';
                    $dist = isset($item['distancia_km']) ? ' — ' . Html::escape((string) $item['distancia_km']) . ' km' : '';
                    $body .= '<li><a href="' . Html::escape((string) $item['url']) . '">'
                        . Html::escape((string) $item['nome']) . '</a> (' . Html::escape((string) $item['tipo']) . ')'
                        . $badge . ' — ' . Html::escape((string) $item['cidade']) . $dist . '</li>';
                }
                $body .= '</ul></div>';
            }
        } else {
            $body .= '<div class="card"><p>Informe CEP ou cidade para buscar perfis por raio.</p></div>';
        }

        return Response::html(Html::layout('Busca', $body, $this->csrf->token()));
    }

    public function privacidade(Request $request): Response
    {
        $body = '<h1>Política de Privacidade</h1>
<div class="card">
<h2 style="margin-top:0">Versão e aceite</h2>
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
}
