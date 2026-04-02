# Requisitos funcionais (SDD) — ide-anunciai

Especificação por fase para desenvolvimento guiado por spec. Cada item é um requisito testável. Alinhado às decisões em [00-perguntas-descoberta.md](00-perguntas-descoberta.md).

---

## Fase 1 — Fundação

### RF1.1 — Cadastro de usuário (conta)

- **RF1.1.1** O sistema permite cadastro inicial com **nome completo**, **e-mail** e **senha**.
- **RF1.1.2** Após cadastro e login, o usuário já acessa a plataforma sem necessidade de preencher documentos.
- **RF1.1.3** O usuário pode definir **foto de perfil** na área logada.
- **RF1.1.4** Senha segue política segura (ver 04-seguranca-lgpd.md) e é armazenada com hash (bcrypt).
- **RF1.1.5** E-mail é único no sistema.
- **RF1.1.6** Checkbox obrigatório de aceite da Política de Privacidade; data/hora do consentimento registrada.

### RF1.2 — Cadastro de profissional (ministro)

- **RF1.2.1** Usuário logado pode criar/editar perfil “Ministro/Profissional” vinculado à conta base.
- **RF1.2.2** Perfil de ministro inclui nome público, telefone, cidade e **uma ou mais habilidades/dons**.
- **RF1.2.3** Cadastro de ministro permite divulgação na plataforma sem selo verificado.

### RF1.3 — Cadastro de igreja

- **RF1.3.1** Usuário logado pode criar/editar perfil “Igreja” vinculado à conta base.
- **RF1.3.2** Perfil de igreja inclui nome da igreja, e-mail do contato, telefone, CEP e cidade.
- **RF1.3.3** CEP pode ser usado para obter cidade via API (Brasil; sem cache por hora).

### RF1.4 — Autenticação

- **RF1.4.1** Login por e-mail e senha.
- **RF1.4.2** Sessão com duração de **8 horas**; regenerar ID após login; logout invalida sessão.
- **RF1.4.3** Rotas protegidas exigem usuário autenticado; redirecionamento para login quando não autenticado.
- **RF1.4.4** **Recuperação de senha** (“lembrete de senha”): fluxo por e-mail (link ou token) para redefinir senha.

### RF1.5 — CRUD de profissional (próprio perfil)

- **RF1.5.1** Usuário ministro pode visualizar e editar seu próprio perfil (nome, telefone, cidade, habilidades/dons). Se verificado, alterações vão para re-verificação.

### RF1.6 — CRUD de igreja (própria)

- **RF1.6.1** Usuário igreja pode visualizar e editar os dados da sua igreja (nome, telefone, CEP, cidade, e-mail). Alterações de igreja **verificada** ficam pendentes de re-verificação na Fase 2.

---

## Fase 2 — Aprovação, verificação e denúncias

### RF2.1 — Verificação de ministro/profissional

- **RF2.1.1** Usuário pode solicitar selo verificado para perfil de ministro.
- **RF2.1.2** Para solicitação, deve informar **RG**, **CPF** e anexar documentos comprobatórios.
- **RF2.1.3** Administrador aprova ou rejeita a solicitação (com motivo opcional).
- **RF2.1.4** Perfil aprovado exibe selo “Verificado”.

### RF2.2 — Verificação de igreja

- **RF2.2.1** Usuário pode solicitar selo verificado para perfil de igreja.
- **RF2.2.2** Para solicitação, deve informar **CNPJ** e anexar documentos comprobatórios.
- **RF2.2.3** Administrador aprova ou rejeita a solicitação (com motivo opcional).
- **RF2.2.4** Perfil de igreja aprovado exibe selo “Verificado”.

### RF2.3 — Re-verificação em alterações

- **RF2.3.1** Quando perfil de ministro ou igreja **verificado** é alterado, a alteração fica pendente de re-verificação.
- **RF2.3.2** Até aprovação, o perfil público mantém os últimos dados aprovados.

### RF2.4 — Denúncias e moderação

- **RF2.4.1** Qualquer usuário logado pode **denunciar** outro usuário (profissional ou igreja), informando uma **descrição** (texto livre) do motivo.
- **RF2.4.2** Na área administrativa deve ser possível **listar usuários denunciados**, com quantidade de denúncias e acesso à **descrição** de cada denúncia.
- **RF2.4.3** Administrador pode **inativar** um usuário denunciado (ex.: caso de falsa identidade). Usuário inativo não aparece na busca e não pode fazer login (ou fluxo equivalente).

---

## Fase 3 — Busca e solicitações

### RF3.1 — Busca por localização

- **RF3.1.1** Usuário informa **CEP** ou **cidade** e **raio em km** (ex.: 10 km). Apenas Brasil; API de CEP; sem cache por hora.
- **RF3.1.2** Sistema retorna ministros e/ou igrejas dentro do raio, considerando perfis ativos.
- **RF3.1.3** Filtro por tipo (profissional/igreja) e, para profissionais, por habilidade/dons (conforme implementação).
- **RF3.1.4** Visitante (não logado) visualiza apenas dados mínimos dos resultados e perfis.
- **RF3.1.5** Usuário logado visualiza informações adicionais permitidas pela plataforma.

### RF3.2 — Primeiro contato por mensagem/chat

- **RF3.2.1** Usuário logado (conta base, com ou sem perfil) pode iniciar contato com profissional ou igreja por **mensagem/chat**.
- **RF3.2.2** A primeira mensagem inclui **texto livre obrigatório** explicando o evento/motivo do contato.
- **RF3.2.3** O destinatário recebe notificação por e-mail com dados do solicitante (nome, e-mail etc.) e o **texto da primeira mensagem**.
- **RF3.2.4** O MVP possui área de conversas para acompanhar mensagens do contato iniciado.

---

## Fora do MVP

- Blog.
- Cache de CEP.
- Notificações in-app.

---

## Acesso e incentivo ao cadastro

- Visitante pode ver proposta do site e acessar busca e cadastro (home com links).
- Conta base (nome completo, e-mail, senha) permite acesso imediato à plataforma.
- Cadastro de perfil ministro e/ou igreja permite ser encontrado na busca e receber solicitações.
