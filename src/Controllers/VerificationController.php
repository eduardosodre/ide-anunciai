<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ChurchRepository;
use App\Repository\ProfessionalRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\Csrf;
use App\Session\SessionFacade;
use App\View\Html;

final class VerificationController
{
    public function __construct(
        private readonly VerificationRepository $verifications,
        private readonly ProfessionalRepository $professionals,
        private readonly ChurchRepository $churches,
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    public function userGet(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        $professional = $this->professionals->findByUserId($userId);
        $church = $this->churches->findByUserId($userId);
        $requests = $this->verifications->listOwnRequests($userId);

        $body = $this->messagesHtml();
        $body .= '<section class="mb-3">
<p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Confiança</p>
<h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Verificação</h1>
<p class="mt-2 text-sm text-on-surface-variant">Envie sua solicitação para receber o selo de perfil verificado.</p>
</section>
<div class="mb-4 rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<p class="text-sm text-on-surface-variant">O <strong>selo verificado</strong> mostra que a equipe conferiu os dados do seu perfil. Informe apenas dados verdadeiros. CPF (ministro) e CNPJ (igreja) são necessários para análise; documentos extras ajudam a agilizar o processo.</p>
</div>';
        $body .= '<div class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<h2 class="font-headline text-2xl font-bold text-primary">Solicitar verificação de ministro</h2>
' . $this->renderMinisterForm($professional) . '
</div>';
        $body .= '<div class="mt-4 rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<h2 class="font-headline text-2xl font-bold text-primary">Solicitar verificação de igreja</h2>
' . $this->renderChurchForm($church) . '
</div>';
        $body .= '<div class="mt-4 rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-sm">
<h2 class="font-headline text-2xl font-bold text-primary">Minhas solicitações</h2>
' . $this->renderRequestsTable($requests) . '
</div>';

        return Response::html(Html::layout('Verificação', $body, $this->csrf->token()));
    }

    public function userPost(Request $request): Response
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/verificacao', 302);
        }

        $tipoEntidade = (string) $request->input('tipo_entidade', '');
        if ($tipoEntidade !== 'ministro' && $tipoEntidade !== 'igreja') {
            SessionFacade::flash('error', 'Tipo de solicitação inválido.');

            return Response::redirect('/verificacao', 302);
        }

        if ($tipoEntidade === 'ministro') {
            $professional = $this->professionals->findByUserId($userId);
            if ($professional === null) {
                SessionFacade::flash('error', 'Crie primeiro o perfil de ministro.');

                return Response::redirect('/verificacao', 302);
            }
            $entidadeId = (int) $professional['id'];
            $rg = $this->nullableTrim((string) $request->input('rg', ''));
            $cpf = $this->onlyDigitsOrNull((string) $request->input('cpf', ''));
            if ($cpf === null) {
                SessionFacade::flash('error', 'CPF é obrigatório para verificação de ministro.');

                return Response::redirect('/verificacao', 302);
            }

            if ($this->verifications->hasPendingRequestForEntity('ministro', $entidadeId)) {
                SessionFacade::flash('error', 'Já existe solicitação pendente para este perfil.');

                return Response::redirect('/verificacao', 302);
            }

            $this->verifications->createVerificationRequest(
                $userId,
                'ministro',
                $entidadeId,
                $rg,
                $cpf,
                null,
                $this->extractDocuments($request)
            );
            SessionFacade::flash('success', 'Solicitação de verificação de ministro enviada.');

            return Response::redirect('/verificacao', 302);
        }

        $church = $this->churches->findByUserId($userId);
        if ($church === null) {
            SessionFacade::flash('error', 'Crie primeiro o perfil de igreja.');

            return Response::redirect('/verificacao', 302);
        }
        $entidadeId = (int) $church['id'];
        $cnpj = $this->onlyDigitsOrNull((string) $request->input('cnpj', ''));
        if ($cnpj === null) {
            SessionFacade::flash('error', 'CNPJ é obrigatório para verificação de igreja.');

            return Response::redirect('/verificacao', 302);
        }

        if ($this->verifications->hasPendingRequestForEntity('igreja', $entidadeId)) {
            SessionFacade::flash('error', 'Já existe solicitação pendente para este perfil.');

            return Response::redirect('/verificacao', 302);
        }

        $this->verifications->createVerificationRequest(
            $userId,
            'igreja',
            $entidadeId,
            null,
            null,
            $cnpj,
            $this->extractDocuments($request)
        );
        SessionFacade::flash('success', 'Solicitação de verificação de igreja enviada.');

        return Response::redirect('/verificacao', 302);
    }

    public function adminQueueGet(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::redirect('/login', 302);
        }

        $rows = $this->verifications->listPendingForAdmin('solicitacao');
        $body = $this->messagesHtml();
        $body .= '<section class="mb-3"><p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Admin</p><h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Verificações pendentes</h1></section>';
        $body .= $this->renderAdminRequests($rows, '/admin/verificacoes');

        return Response::html(Html::layout('Admin verificações', $body, $this->csrf->token()));
    }

    public function adminReviewGet(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::redirect('/login', 302);
        }

        $rows = $this->verifications->listPendingForAdmin('revisao');
        $body = $this->messagesHtml();
        $body .= '<section class="mb-3"><p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary">Admin</p><h1 class="font-headline text-3xl font-extrabold text-primary md:text-5xl">Re-verificação</h1></section>';
        $body .= $this->renderAdminRequests($rows, '/admin/revisao');

        return Response::html(Html::layout('Admin revisão', $body, $this->csrf->token()));
    }

    public function adminDecisionPost(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::redirect('/login', 302);
        }

        if (!$this->csrf->validate($request->input('csrf_token'))) {
            SessionFacade::flash('error', 'Sessão inválida.');

            return Response::redirect('/admin/verificacoes', 302);
        }

        $solicitacaoId = (int) $request->input('solicitacao_id', 0);
        $acao = (string) $request->input('acao', '');
        $contexto = (string) $request->input('contexto', 'solicitacao');
        if ($solicitacaoId <= 0 || ($acao !== 'aprovar' && $acao !== 'rejeitar')) {
            SessionFacade::flash('error', 'Dados inválidos para decisão.');

            return Response::redirect($contexto === 'revisao' ? '/admin/revisao' : '/admin/verificacoes', 302);
        }

        $motivo = $this->nullableTrim((string) $request->input('motivo_rejeicao', ''));
        $result = $this->verifications->approveOrReject($solicitacaoId, (int) $admin['id'], $acao, $motivo);
        if ($result === null) {
            SessionFacade::flash('error', 'Solicitação não encontrada.');
        } else {
            SessionFacade::flash('success', 'Solicitação atualizada com sucesso.');
        }

        return Response::redirect($contexto === 'revisao' ? '/admin/revisao' : '/admin/verificacoes', 302);
    }

    public function adminDecisionApiPost(Request $request): Response
    {
        $admin = $this->requireAdminUser();
        if ($admin === null) {
            return Response::json([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Acesso restrito a administradores.'],
            ], 401);
        }

        $solicitacaoId = (int) $request->route('id', 0);
        $acao = (string) $request->input('acao', '');
        if ($solicitacaoId <= 0 || ($acao !== 'aprovar' && $acao !== 'rejeitar')) {
            return Response::json([
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Dados inválidos.'],
            ], 422);
        }

        $motivo = $this->nullableTrim((string) $request->input('motivo_rejeicao', ''));
        $result = $this->verifications->approveOrReject($solicitacaoId, (int) $admin['id'], $acao, $motivo);
        if ($result === null) {
            return Response::json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Solicitação não encontrada.'],
            ], 404);
        }

        return Response::json([
            'id' => (int) $result['id'],
            'status' => (string) $result['status'],
            'tipo_entidade' => (string) $result['tipo_entidade'],
            'tipo_fluxo' => (string) $result['tipo_fluxo'],
        ]);
    }

    private function renderMinisterForm(?array $professional): string
    {
        if ($professional === null) {
            return '<p class="empty-state">Perfil de ministro ainda não criado. Crie o perfil em <a href="' . Html::u('/meu-perfil/ministro') . '">Meu perfil ministro</a> e volte aqui para solicitar o selo.</p>';
        }

        return '<p class="mb-4 mt-1 text-sm text-on-surface-variant">Preencha o CPF (somente números ou com pontuação). O RG é opcional, mas pode acelerar a conferência. Se enviar um documento comprobatório, descreva o tipo e onde o arquivo pode ser acessado (caminho público ou link acordado com o suporte).</p>
<form method="post" action="' . Html::u('/verificacao') . '" class="verification-form">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="ministro">
  <div class="field">
    <label for="verif-ministro-cpf">CPF</label>
    <input id="verif-ministro-cpf" type="text" name="cpf" maxlength="20" required inputmode="numeric" autocomplete="off" placeholder="000.000.000-00">
    <p class="field-hint">Obrigatório. Usado apenas para verificação interna.</p>
  </div>
  <div class="field">
    <label for="verif-ministro-rg">RG (opcional)</label>
    <input id="verif-ministro-rg" type="text" name="rg" maxlength="50" autocomplete="off" placeholder="Número do documento de identidade">
  </div>
  <fieldset class="field verification-docs-fieldset">
    <legend class="field-hint verification-docs-legend">Documento comprobatório (opcional)</legend>
    <p class="field-hint verification-docs-help">Ex.: foto do rosto com documento, comprovante vinculado ao ministério. Indique tipo, nome do arquivo e URL ou caminho acessível pela equipe.</p>
    <div class="field verification-doc-row">
      <label for="verif-doc-m-tipo">Tipo do documento</label>
      <input id="verif-doc-m-tipo" type="text" name="documento_tipo[]" maxlength="100" placeholder="Ex.: selfie com documento em mãos">
    </div>
    <div class="field verification-doc-row">
      <label for="verif-doc-m-nome">Nome do arquivo</label>
      <input id="verif-doc-m-nome" type="text" name="documento_nome[]" maxlength="255" placeholder="Ex.: verificacao-ministro.jpg">
    </div>
    <div class="field verification-doc-row-last">
      <label for="verif-doc-m-path">URL ou caminho do arquivo</label>
      <input id="verif-doc-m-path" type="text" name="documento_caminho[]" maxlength="500" placeholder="https://… ou /uploads/…">
    </div>
  </fieldset>
  <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-primary px-6 py-3 font-semibold text-white">Enviar solicitação de verificação (ministro)</button>
</form>';
    }

    private function renderChurchForm(?array $church): string
    {
        if ($church === null) {
            return '<p class="empty-state">Perfil de igreja ainda não criado. Crie o perfil em <a href="' . Html::u('/meu-perfil/igreja') . '">Meu perfil igreja</a> e volte aqui para solicitar o selo.</p>';
        }

        return '<p class="mb-4 mt-1 text-sm text-on-surface-variant">Informe o <strong>CNPJ</strong> da entidade (14 dígitos). Documentos como estatuto ou ata podem ser referenciados abaixo para agilizar a análise.</p>
<form method="post" action="' . Html::u('/verificacao') . '" class="verification-form">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="igreja">
  <div class="field">
    <label for="verif-igreja-cnpj">CNPJ</label>
    <input id="verif-igreja-cnpj" type="text" name="cnpj" maxlength="20" required inputmode="numeric" autocomplete="off" placeholder="00.000.000/0001-00">
    <p class="field-hint">Obrigatório para verificação de igreja. Confira se coincide com o cadastro do perfil público.</p>
  </div>
  <fieldset class="field verification-docs-fieldset">
    <legend class="field-hint verification-docs-legend">Documento comprobatório (opcional)</legend>
    <p class="field-hint verification-docs-help">Ex.: estatuto, ata de constituição ou carta de reconhecimento. Indique tipo, nome do arquivo e URL ou caminho acessível.</p>
    <div class="field verification-doc-row">
      <label for="verif-doc-i-tipo">Tipo do documento</label>
      <input id="verif-doc-i-tipo" type="text" name="documento_tipo[]" maxlength="100" placeholder="Ex.: estatuto social">
    </div>
    <div class="field verification-doc-row">
      <label for="verif-doc-i-nome">Nome do arquivo</label>
      <input id="verif-doc-i-nome" type="text" name="documento_nome[]" maxlength="255" placeholder="Ex.: estatuto-igreja.pdf">
    </div>
    <div class="field verification-doc-row-last">
      <label for="verif-doc-i-path">URL ou caminho do arquivo</label>
      <input id="verif-doc-i-path" type="text" name="documento_caminho[]" maxlength="500" placeholder="https://… ou /uploads/…">
    </div>
  </fieldset>
  <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-primary px-6 py-3 font-semibold text-white">Enviar solicitação de verificação (igreja)</button>
</form>';
    }

    private function renderRequestsTable(array $requests): string
    {
        if ($requests === []) {
            return '<p class="empty-state mt-0">Nenhuma solicitação registrada ainda. Use os formulários acima quando o perfil estiver pronto.</p>';
        }

        $lines = '';
        foreach ($requests as $row) {
            $motivo = (string) ($row['motivo_rejeicao'] ?? '');
            $lines .= '<tr>'
                . '<td>' . (int) $row['id'] . '</td>'
                . '<td>' . Html::escape((string) $row['tipo_entidade']) . '</td>'
                . '<td>' . Html::escape((string) $row['tipo_fluxo']) . '</td>'
                . '<td><strong>' . Html::escape((string) $row['status']) . '</strong></td>'
                . '<td>' . Html::escape($motivo !== '' ? $motivo : '—') . '</td>'
                . '<td>' . Html::escape((string) $row['criado_em']) . '</td>'
                . '</tr>';
        }

        return '<p class="field-hint hint-table-head">Acompanhe o status das suas solicitações. Em caso de rejeição, o motivo aparece na coluna indicada.</p>
<div class="data-table-wrap">
<table class="data-table">
<thead><tr><th scope="col">ID</th><th scope="col">Entidade</th><th scope="col">Fluxo</th><th scope="col">Status</th><th scope="col">Motivo</th><th scope="col">Criado em</th></tr></thead>
<tbody>' . $lines . '</tbody>
</table>
</div>';
    }

    private function renderAdminRequests(array $rows, string $contextPath): string
    {
        if ($rows === []) {
            return '<div class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm"><p class="empty-state">Sem itens pendentes.</p></div>';
        }

        $html = '';
        foreach ($rows as $row) {
            $docsHtml = '';
            foreach ($row['documentos'] as $documento) {
                $docsHtml .= '<li>'
                    . Html::escape((string) $documento['tipo_documento'])
                    . ' — ' . Html::escape((string) ($documento['nome_arquivo'] ?? ''))
                    . ' — ' . Html::escape((string) ($documento['caminho_arquivo'] ?? ''))
                    . '</li>';
            }
            if ($docsHtml === '') {
                $docsHtml = '<li>Sem documentos registrados.</li>';
            }

            $html .= '<article class="mb-3 rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 shadow-sm">
<h3 class="font-headline text-xl font-bold text-primary">Solicitação #' . (int) $row['id'] . ' (' . Html::escape((string) $row['tipo_entidade']) . ')</h3>
<p><strong>Fluxo:</strong> ' . Html::escape((string) $row['tipo_fluxo']) . '</p>
<p><strong>Solicitante:</strong> ' . Html::escape((string) $row['usuario_nome']) . ' (' . Html::escape((string) $row['usuario_email']) . ')</p>
<p><strong>RG:</strong> ' . Html::escape((string) ($row['rg'] ?? '')) . ' | <strong>CPF:</strong> ' . Html::escape((string) ($row['cpf'] ?? '')) . ' | <strong>CNPJ:</strong> ' . Html::escape((string) ($row['cnpj'] ?? '')) . '</p>
<p><strong>Documentos:</strong></p>
<ul class="moderation-doc-list">' . $docsHtml . '</ul>
<form method="post" action="' . Html::u('/admin/verificacoes/decisao') . '" class="moderation-actions-form">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="solicitacao_id" value="' . (int) $row['id'] . '">
  <input type="hidden" name="contexto" value="' . ($contextPath === '/admin/revisao' ? 'revisao' : 'solicitacao') . '">
  <div class="field">
    <label>Motivo rejeição (opcional)</label>
    <input type="text" name="motivo_rejeicao" maxlength="500">
  </div>
  <button type="submit" class="btn btn-primary" name="acao" value="aprovar">Aprovar</button>
  <button type="submit" class="btn btn-secondary" name="acao" value="rejeitar">Rejeitar</button>
</form>
</article>';
        }

        return $html;
    }

    private function requireAdminUser(): ?array
    {
        $userId = SessionFacade::userId();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return null;
        }

        return ((int) ($user['admin'] ?? 0) === 1) ? $user : null;
    }

    private function extractDocuments(Request $request): array
    {
        $tipos = $request->input('documento_tipo', []);
        $nomes = $request->input('documento_nome', []);
        $caminhos = $request->input('documento_caminho', []);
        if (!is_array($tipos) || !is_array($nomes) || !is_array($caminhos)) {
            return [];
        }

        $count = max(count($tipos), count($nomes), count($caminhos));
        $documents = [];
        for ($i = 0; $i < $count; $i++) {
            $tipo = isset($tipos[$i]) ? trim((string) $tipos[$i]) : '';
            $nome = isset($nomes[$i]) ? trim((string) $nomes[$i]) : '';
            $caminho = isset($caminhos[$i]) ? trim((string) $caminhos[$i]) : '';
            if ($tipo === '' && $nome === '' && $caminho === '') {
                continue;
            }
            $documents[] = [
                'tipo_documento' => $tipo,
                'nome_arquivo' => $nome === '' ? null : $nome,
                'caminho_arquivo' => $caminho === '' ? null : $caminho,
            ];
        }

        return $documents;
    }

    private function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function onlyDigitsOrNull(string $value): ?string
    {
        $digits = preg_replace('/\D/u', '', $value) ?? '';

        return $digits === '' ? null : $digits;
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
