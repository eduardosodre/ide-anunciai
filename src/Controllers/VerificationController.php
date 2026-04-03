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
        $body .= '<h1>Verificação</h1>';
        $body .= '<h2>Solicitar verificação de ministro</h2>';
        $body .= $this->renderMinisterForm($professional);
        $body .= '<h2>Solicitar verificação de igreja</h2>';
        $body .= $this->renderChurchForm($church);
        $body .= '<h2>Minhas solicitações</h2>';
        $body .= $this->renderRequestsTable($requests);

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
        $body .= '<h1>Admin · Verificações pendentes</h1>';
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
        $body .= '<h1>Admin · Re-verificação</h1>';
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
            return '<p>Perfil de ministro ainda não criado. Cadastre em <a href="' . Html::u('/meu-perfil/ministro') . '">Meu perfil ministro</a>.</p>';
        }

        return '<form method="post" action="' . Html::u('/verificacao') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="ministro">
  <label>RG <input type="text" name="rg" maxlength="50"></label>
  <label>CPF <input type="text" name="cpf" maxlength="20" required></label>
  <label>Documento 1 - tipo <input type="text" name="documento_tipo[]" maxlength="100" placeholder="Ex.: selfie com documento"></label>
  <label>Documento 1 - nome arquivo <input type="text" name="documento_nome[]" maxlength="255"></label>
  <label>Documento 1 - caminho <input type="text" name="documento_caminho[]" maxlength="500" placeholder="/uploads/doc1.jpg"></label>
  <button type="submit">Solicitar verificação ministro</button>
</form>';
    }

    private function renderChurchForm(?array $church): string
    {
        if ($church === null) {
            return '<p>Perfil de igreja ainda não criado. Cadastre em <a href="' . Html::u('/meu-perfil/igreja') . '">Meu perfil igreja</a>.</p>';
        }

        return '<form method="post" action="' . Html::u('/verificacao') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="tipo_entidade" value="igreja">
  <label>CNPJ <input type="text" name="cnpj" maxlength="20" required></label>
  <label>Documento 1 - tipo <input type="text" name="documento_tipo[]" maxlength="100" placeholder="Ex.: estatuto"></label>
  <label>Documento 1 - nome arquivo <input type="text" name="documento_nome[]" maxlength="255"></label>
  <label>Documento 1 - caminho <input type="text" name="documento_caminho[]" maxlength="500" placeholder="/uploads/doc-igreja.pdf"></label>
  <button type="submit">Solicitar verificação igreja</button>
</form>';
    }

    private function renderRequestsTable(array $requests): string
    {
        if ($requests === []) {
            return '<p>Nenhuma solicitação registrada.</p>';
        }

        $lines = '';
        foreach ($requests as $row) {
            $lines .= '<tr>'
                . '<td>' . (int) $row['id'] . '</td>'
                . '<td>' . Html::escape((string) $row['tipo_entidade']) . '</td>'
                . '<td>' . Html::escape((string) $row['tipo_fluxo']) . '</td>'
                . '<td>' . Html::escape((string) $row['status']) . '</td>'
                . '<td>' . Html::escape((string) ($row['motivo_rejeicao'] ?? '')) . '</td>'
                . '<td>' . Html::escape((string) $row['criado_em']) . '</td>'
                . '</tr>';
        }

        return '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
<thead><tr><th>ID</th><th>Entidade</th><th>Fluxo</th><th>Status</th><th>Motivo</th><th>Criado em</th></tr></thead>
<tbody>' . $lines . '</tbody>
</table>';
    }

    private function renderAdminRequests(array $rows, string $contextPath): string
    {
        if ($rows === []) {
            return '<p>Sem itens pendentes.</p>';
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

            $html .= '<article style="border:1px solid #ddd;padding:1rem;margin-bottom:1rem;">
<h3>Solicitação #' . (int) $row['id'] . ' (' . Html::escape((string) $row['tipo_entidade']) . ')</h3>
<p><strong>Fluxo:</strong> ' . Html::escape((string) $row['tipo_fluxo']) . '</p>
<p><strong>Solicitante:</strong> ' . Html::escape((string) $row['usuario_nome']) . ' (' . Html::escape((string) $row['usuario_email']) . ')</p>
<p><strong>RG:</strong> ' . Html::escape((string) ($row['rg'] ?? '')) . ' | <strong>CPF:</strong> ' . Html::escape((string) ($row['cpf'] ?? '')) . ' | <strong>CNPJ:</strong> ' . Html::escape((string) ($row['cnpj'] ?? '')) . '</p>
<p><strong>Documentos:</strong></p>
<ul>' . $docsHtml . '</ul>
<form method="post" action="' . Html::u('/admin/verificacoes/decisao') . '">
  <input type="hidden" name="csrf_token" value="' . Html::escape($this->csrf->token()) . '">
  <input type="hidden" name="solicitacao_id" value="' . (int) $row['id'] . '">
  <input type="hidden" name="contexto" value="' . ($contextPath === '/admin/revisao' ? 'revisao' : 'solicitacao') . '">
  <label>Motivo rejeição (opcional) <input type="text" name="motivo_rejeicao" maxlength="500"></label>
  <button type="submit" name="acao" value="aprovar">Aprovar</button>
  <button type="submit" name="acao" value="rejeitar">Rejeitar</button>
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
