# 10 Etapas de desenvolvimento — ide-anunciai

Todo o desenvolvimento está dividido em **10 etapas**. A **Etapa 1** documenta o SDD (docs 00–14); as **Etapas 2 a 10** estão implementadas no código do MVP conforme abaixo.

---

## Etapa 1 — Documentação SDD

**Status atual.** Especificação completa em formato Spec-Driven Development: visão, personas, requisitos funcionais, segurança e LGPD, modelo de dados, UX/navegação e contratos de interfaces. Documentos em `docs/` (00 a 08), mais clarificação OpenSpec em **09 a 14** (aceite, contrato HTTP, regras de negócio, bordas, NFRs, testes). Validação com o usuário antes de iniciar o código.

**Entregáveis:** docs/00 a 14 revisados e aprovados.

---

## Etapa 2 — Ambiente e fundação

- Estrutura de pastas do projeto (PHP sem framework).
- Configuração MySQL (charset utf8mb4).
- **database.php.example** versionado; **database.php** no .gitignore.
- Rotas básicas (front controller ou equivalente).
- .gitignore e README atualizados.
- Página estática de home (proposta + links) para validar ambiente.

**Entregáveis:** Projeto rodando localmente; home estática; banco criado (vazio ou só tabelas base).

**Status de implementação (MVP inicial):**

- Estrutura base criada (`public/` + `src/`) com front controller.
- Rotas HTML iniciais ativas: `/`, `/busca`, `/cadastro`, `/login`.
- Endpoints API mínimos ativos: `/api/health`, `/api/search` e placeholders de autenticação.
- `database.php.example` mantido versionado e `database.php` mantido no `.gitignore`.
- README atualizado com instruções de execução local.

---

## Etapa 3 — Autenticação

- Cadastro com **nome completo**, **e-mail** e **senha** (conta base).
- Validação de e-mail único; política de senha segura; hash bcrypt.
- Checkbox de consentimento da Política de Privacidade; registro de data/hora e versão.
- Login (e-mail + senha); sessão **8 horas**; regenerar ID ao login; logout.
- **Recuperação de senha:** “Esqueci minha senha” → e-mail com link/token → tela de redefinir senha (token com validade curta e uso único).
- Edição da conta base (incluindo **foto de perfil**).
- Proteção de rotas (exigir autenticação onde aplicável).
- Endpoints JSON: `/api/auth/*`, `/api/privacy/export` (exportação LGPD em JSON).

**Entregáveis:** Cadastro, login, logout e recuperação de senha funcionando; sessão 8h; tabela `usuario` e integração MySQL; e-mails de recuperação registrados em `storage/logs/mail.log` (e `mail()` quando disponível).

**Status:** implementado no código (aplicação + schema em `database/schema.sql`).

---

## Etapa 4 — Cadastro profissional (ministro)

- Formulário de perfil de ministro vinculado à conta base: nome público, telefone, cidade, **habilidades/dons** (múltipla escolha).
- Tabela (ou catálogo) de habilidades (ensinar, ministrar, orar, tocar instrumento, etc.).
- CRUD do próprio perfil (edição); se perfil for **verificado**, alterações geram pendência de re-verificação (fluxo na Etapa 6).
- Exibição de perfil público (card e página) com nome, habilidades, cidade, selo Verificado (quando houver).

**Entregáveis:** Cadastro e edição de ministro com habilidades; perfil público de profissional.

**Status:** implementado no código (schema em `database/schema.sql`, CRUD HTML em `/meu-perfil/ministro`, perfil público em `/perfil/profissional/{id}` e endpoint público `GET /api/profissionais/{id}`).

---

## Etapa 5 — Cadastro igreja

- Formulário de perfil de igreja vinculado à conta base: nome da igreja, e-mail, telefone, CEP, cidade. Uso de API de CEP (Brasil) para preencher cidade; sem cache por hora.
- CRUD da própria igreja (edição); alterações só voltam para aprovação quando o perfil de igreja já estiver verificado.
- Exibição de perfil público da igreja (apenas aprovadas).

**Entregáveis:** Cadastro e edição de igreja; perfil público de igreja; fluxo de aprovação preparado.

**Decisão de produto:** o e-mail de contato da igreja é o **e-mail da conta** (`usuario.email`); o campo `email_contato` no banco é gravado automaticamente com esse valor.

**Status:** implementado no código (tabela `igreja` em `database/schema.sql`, CRUD em `/meu-perfil/igreja`, ViaCEP para cidade, perfil público em `/perfil/igreja/{id}`, `GET /api/igrejas/{id}`). A exibição de snapshot aprovado enquanto `pendente_revisao=1` está na **Etapa 6** (verificação e re-verificação).

---

## Etapa 6 — Aprovação e re-verificação

- Fluxo de solicitação de verificação de **ministro** (RG, CPF e documentos comprobatórios).
- Fluxo de solicitação de verificação de **igreja** (CNPJ e documentos comprobatórios).
- Área admin: **aprovar/rejeitar** solicitações de verificação.
- **Re-verificação:** quando perfil verificado (ministro ou igreja) edita dados sensíveis, alteração fica pendente; admin aprova para publicar; até lá perfil exibe dados antigos.

**Entregáveis:** Fluxo completo de verificação para ministro e igreja; selo Verificado; re-verificação de alterações de verificados.

**Status:** implementado no código.

- Banco/modelo: `verificacao_solicitacao`, `verificacao_documento` e snapshot de dados públicos aprovados em `profissional` e `igreja`.
- Usuário: `GET/POST /verificacao` para solicitar verificação de ministro (RG/CPF/docs metadados) e igreja (CNPJ/docs metadados), com listagem de status das solicitações.
- Admin: `GET /admin/verificacoes` (fila pendente), `GET /admin/revisao` (re-verificação de perfis verificados com alteração pendente) e `POST /admin/verificacoes/decisao` para aprovar/rejeitar (motivo opcional).
- API mínima admin: `POST /api/admin/verificacoes/{id}/decisao` com sessão admin e payload `{ "acao": "aprovar|rejeitar", "motivo_rejeicao": "..." }`.
- Regra central aplicada: edição de perfil verificado gera `pendente_revisao=1` e cria solicitação de revisão; enquanto pendente, HTML/API pública exibem snapshot aprovado anterior; ao aprovar revisão, snapshot é atualizado e pendência limpa.

---

## Etapa 7 — Denúncias e moderação

- Fluxo de denúncia disponível para usuários logados (`/denunciar`) com descrição obrigatória.
- Painel admin para revisão de denúncias (`/admin/denuncias`) com detalhes de denunciante, denunciado e texto.
- Ação administrativa para inativar usuário denunciado (`POST /admin/usuarios/{id}/inativar`).
- Endpoint API para registrar denúncia (`POST /api/reports`).
- Anti-abuso aplicado: limite de 3 denúncias/dia por par (denunciante, alvo) e 10 denúncias/dia por denunciante.

**Entregáveis:** Denúncia com descrição; painel admin de denúncias; inativação de usuário.

**Status:** implementado no código.

- Banco/modelo: tabela `denuncia` com índices para moderação e rate limit.
- Web: link “Denunciar este perfil” em perfis públicos de ministro e igreja, com formulário dedicado.
- Admin: listagem de denúncias recentes e botão para inativar usuário denunciado.
- API: `POST /api/reports` validando autenticação, alvo e limites diários.

---

## Etapa 8 — Busca por localização

- Formulário de busca: **CEP** ou **cidade** + **raio em km** (definido pelo usuário). Apenas Brasil.
- Uso de API de CEP para obter coordenadas (e cidade quando buscar por CEP); por hora **sem cache**.
- Campos **latitude** e **longitude** em profissional e igreja (preenchidos no cadastro/edição via CEP/cidade).
- Cálculo de distância (ex.: Haversine) para filtrar resultados dentro do raio.
- Filtros: tipo (profissional/igreja); habilidade (para profissionais).
- Resultados: apenas perfis ativos; perfis verificados com alteração pendente de re-verificação exibem dados antigos.

**Entregáveis:** Busca por CEP/cidade + raio em km; resultados por proximidade; filtros básicos.

**Status:** implementado no código.

- Geolocalização: resolução de coordenadas via serviço de geocodificação (cidade/CEP) sem cache no MVP.
- Persistência de coordenadas: atualização de `latitude`/`longitude` em `profissional` e `igreja` durante salvamento de perfil.
- Busca por raio: consulta com fórmula Haversine em `profissional` e `igreja`, filtrando apenas usuários ativos.
- Filtros: tipo (`profissionais`, `igrejas`, `ambos`) e `habilidade_id` para profissionais.
- Interfaces: `GET /busca` com formulário e resultados HTML (incluindo seletor de habilidades por dropdown); `GET /api/search` com retorno JSON real (não stub).

---

## Etapa 9 — Primeiro contato por chat

- Fluxo de **início de conversa** com **texto livre obrigatório** (evento/motivo do contato).
- Destino: profissional ou igreja (página de perfil com botão “Iniciar conversa”).
- Exige usuário logado (conta base, com ou sem perfil).
- Notificação por **e-mail** ao destinatário com dados do solicitante e **texto da primeira mensagem**.
- Área de conversas (`/chat`) no MVP para remetente e destinatário acompanharem mensagens.

**Entregáveis:** Conversa iniciada com mensagem obrigatória; e-mail ao destinatário; listagem e detalhe de conversas no site.

**Status:** implementado no código.

- Banco/modelo: tabelas `conversa` e `mensagem`; coordenadas em `profissional` alinhadas ao restante do schema (latitude/longitude).
- Web: `/chat/iniciar`, `/chat`, `/chat/{id}` e envio de novas mensagens; link “Iniciar conversa” nos perfis públicos (visitante é orientado a fazer login).
- E-mail: cópia em `storage/logs/mail.log` e `mail()` quando disponível; envio na **primeira** mensagem de uma conversa nova.
- API: `POST/GET /api/chat/conversations`, `GET /api/chat/conversations/{id}`, `POST /api/chat/conversations/{id}/messages` (sessão obrigatória).

---

## Etapa 10 — UX, responsivo e entrega

- **Home:** proposta do site bem destacada; links para Buscar, Cadastrar, Login; layout moderno e claro.
- Perfis/resultados com **dados mínimos para visitante** e **dados adicionais para logado**.
- **Layout responsivo** em todas as telas (desktop e mobile); menu adaptado (ex.: hambúrguer no mobile).
- Política de Privacidade (página /privacidade); link “Esqueci minha senha” no login; rodapé com links úteis.
- Revisão geral de usabilidade (feedback, estados de carregamento, mensagens de erro).
- Ajustes finais e preparação para deploy (HTTPS, config de produção, etc.).

**Entregáveis:** Home com proposta e navegação; site responsivo; revisão de UX e segurança para entrega.

**Status:** implementado no código.

- Layout global em `Html::layout`: tokens CSS, tema claro/escuro (`prefers-color-scheme`), header fixo, menu principal com variante logado/visitante, menu mobile (controle por checkbox + rótulo), rodapé com links úteis.
- Home (`/`): hero com proposta de valor e CTAs para visitante; bloco compacto com atalhos para usuário autenticado.
- Busca (`/busca`): formulário e resultados agrupados em cards; lista de resultados com classe `result-list`.
- Privacidade (`/privacidade`): página estruturada em seções dentro de card.
- API: `GET /api/health` retorna `stage: 10` enquanto o conjunto da Etapa 10 estiver considerado entregue.
- Perfis públicos (ministro e igreja): dados mínimos para visitante (nome, cidade, habilidades quando aplicável, selo); telefone e e-mail **não** aparecem no público. **Logado** vê as mesmas informações públicas e pode **iniciar conversa** e **denunciar**; visitante vê orientação para entrar nessas ações.

---

## Ordem e dependências

- **1** → **2** → **3** → **4** e **5** (podem ser em paralelo após 3) → **6** → **7** → **8** → **9** → **10**.
- Etapa 1 (documentação SDD) é pré-requisito para todas as demais; a validação da documentação encerra a Etapa 1 e libera o início da Etapa 2.
