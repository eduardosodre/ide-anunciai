# 10 Etapas de desenvolvimento — ide-anunciai

Todo o desenvolvimento está dividido em **10 etapas**. No momento atual trabalha-se apenas na **documentação SDD** (Etapa 1). O código será implementado após validação dos specs, seguindo as etapas 2 a 10.

---

## Etapa 1 — Documentação SDD

**Status atual.** Especificação completa em formato Spec-Driven Development: visão, personas, requisitos funcionais, segurança e LGPD, modelo de dados, UX/navegação e contratos de interfaces. Documentos em `docs/` (00 a 07). Validação com o usuário antes de iniciar o código.

**Entregáveis:** docs/00 a 07 revisados e aprovados.

---

## Etapa 2 — Ambiente e fundação

- Estrutura de pastas do projeto (PHP sem framework).
- Configuração MySQL (charset utf8mb4).
- **database.php.example** versionado; **database.php** no .gitignore.
- Rotas básicas (front controller ou equivalente).
- .gitignore e README atualizados.
- Página estática de home (proposta + links) para validar ambiente.

**Entregáveis:** Projeto rodando localmente; home estática; banco criado (vazio ou só tabelas base).

---

## Etapa 3 — Autenticação

- Cadastro com **nome**, **e-mail**, **senha** (conta mínima) e escolha de tipo (conta mínima / igreja / ministro).
- Validação de e-mail único; política de senha segura; hash bcrypt.
- Checkbox de consentimento da Política de Privacidade; registro de data/hora e versão.
- Login (e-mail + senha); sessão **8 horas**; regenerar ID ao login; logout.
- **Recuperação de senha:** “Esqueci minha senha” → e-mail com link/token → tela de redefinir senha (token com validade curta e uso único).
- Proteção de rotas (exigir autenticação onde aplicável).

**Entregáveis:** Cadastro, login, logout e recuperação de senha funcionando; sessão 8h.

---

## Etapa 4 — Cadastro profissional (ministro)

- Formulário de cadastro de ministro: nome, e-mail, CPF ou CNPJ de pregador, telefone, cidade, **habilidades/dons** (múltipla escolha).
- Tabela (ou catálogo) de habilidades (ensinar, ministrar, orar, tocar instrumento, etc.).
- CRUD do próprio perfil (edição); se usuário for **verificado**, alterações geram pendência de re-verificação (fluxo na Etapa 6).
- Exibição de perfil público (card e página) com nome, habilidades, cidade, selo Verificado (quando houver).

**Entregáveis:** Cadastro e edição de ministro com habilidades; perfil público de profissional.

---

## Etapa 5 — Cadastro igreja

- Formulário de cadastro de igreja: **CNPJ**, nome da igreja, e-mail, telefone, CEP, cidade. Uso de API de CEP (Brasil) para preencher cidade; sem cache por hora.
- Status de aprovação (pendente até admin aprovar).
- CRUD da própria igreja (edição); alterações podem voltar para aprovação conforme regra.
- Exibição de perfil público da igreja (apenas aprovadas).

**Entregáveis:** Cadastro e edição de igreja; perfil público de igreja; fluxo de aprovação preparado.

---

## Etapa 6 — Aprovação e re-verificação

- Área admin: **aprovar/rejeitar** igrejas (lista de pendentes).
- **Selo Verificado** para profissionais (admin atribui/remove).
- **Re-verificação:** quando profissional verificado edita perfil, alteração fica pendente; admin tem fila de “revisão” e aprova para publicar; até lá perfil exibe dados antigos.
- Apenas igrejas aprovadas e profissionais ativos (e sem pendência de revisão publicada) aparecem na busca e vitrines.

**Entregáveis:** Fluxo completo de aprovação de igrejas; selo Verificado; re-verificação de alterações de verificados.

---

## Etapa 7 — Denúncias e moderação

- Usuário logado pode **denunciar** outro usuário (profissional ou igreja), com **descrição** (texto livre).
- Área admin: **listar usuários denunciados** (com quantidade de denúncias); **ver descrição** de cada denúncia.
- Admin pode **inativar** usuário (campo `ativo = 0`); usuário inativo não aparece na busca e não acessa o sistema (ou fluxo equivalente).

**Entregáveis:** Denúncia com descrição; painel admin de denúncias; inativação de usuário.

---

## Etapa 8 — Busca por localização

- Formulário de busca: **CEP** ou **cidade** + **raio em km** (definido pelo usuário). Apenas Brasil.
- Uso de API de CEP para obter coordenadas (e cidade quando buscar por CEP); por hora **sem cache**.
- Campos **latitude** e **longitude** em profissional e igreja (preenchidos no cadastro/edição via CEP/cidade).
- Cálculo de distância (ex.: Haversine) para filtrar resultados dentro do raio.
- Filtros: tipo (profissional/igreja); habilidade (para profissionais).
- Resultados: apenas aprovados e ativos; profissionais com alteração pendente de re-verificação exibem dados antigos.

**Entregáveis:** Busca por CEP/cidade + raio em km; resultados por proximidade; filtros básicos.

---

## Etapa 9 — Solicitações de contato

- Formulário de **solicitação** com **texto livre obrigatório** (evento/motivo do contato).
- Destino: profissional ou igreja (página de perfil com botão “Solicitar contato”).
- Exige usuário logado (conta mínima ou com perfil).
- Notificação por **e-mail** ao destinatário com dados do solicitante e **texto da mensagem**.
- Opcional no MVP: área “Minhas solicitações” para o destinatário ver no site.

**Entregáveis:** Envio de solicitação com mensagem obrigatória; e-mail ao destinatário; (opcional) listagem de solicitações recebidas.

---

## Etapa 10 — UX, responsivo e entrega

- **Home:** proposta do site bem destacada; links para Buscar, Cadastrar, Login; layout moderno e claro.
- **Layout responsivo** em todas as telas (desktop e mobile); menu adaptado (ex.: hambúrguer no mobile).
- Política de Privacidade (página /privacidade); link “Esqueci minha senha” no login; rodapé com links úteis.
- Revisão geral de usabilidade (feedback, estados de carregamento, mensagens de erro).
- Ajustes finais e preparação para deploy (HTTPS, config de produção, etc.).

**Entregáveis:** Home com proposta e navegação; site responsivo; revisão de UX e segurança para entrega.

---

## Ordem e dependências

- **1** → **2** → **3** → **4** e **5** (podem ser em paralelo após 3) → **6** → **7** → **8** → **9** → **10**.
- Etapa 1 (documentação SDD) é pré-requisito para todas as demais; a validação da documentação encerra a Etapa 1 e libera o início da Etapa 2.
