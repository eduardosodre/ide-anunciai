# Requisitos funcionais (SDD) — ide-anunciai

Especificação por fase para desenvolvimento guiado por spec. Cada item é um requisito testável. Alinhado às decisões em [00-perguntas-descoberta.md](00-perguntas-descoberta.md).

---

## Fase 1 — Fundação

### RF1.1 — Cadastro de usuário (conta)

- **RF1.1.1** O sistema permite cadastro com **nome**, **e-mail** e **senha** (conta mínima para solicitações).
- **RF1.1.2** Para ser **Igreja** ou **Ministro**, o usuário escolhe **um** tipo no cadastro e informa **CNPJ** (igreja) ou **CPF/CNPJ de pregador** (ministro). Uma conta não acumula os dois papéis.
- **RF1.1.3** Senha segue política segura (ver 04-seguranca-lgpd.md) e é armazenada com hash (bcrypt).
- **RF1.1.4** E-mail é único no sistema.
- **RF1.1.5** Checkbox obrigatório de aceite da Política de Privacidade; data/hora do consentimento registrada.

### RF1.2 — Cadastro de profissional (ministro)

- **RF1.2.1** Usuário que escolheu tipo “Ministro” informa: nome, e-mail, telefone, cidade, **CPF ou CNPJ de pregador**.
- **RF1.2.2** Deve selecionar **uma ou mais habilidades/dons** (ex.: ensinar, dar aula, ministrar, orar, tocar instrumento — lista definida no sistema).
- **RF1.2.3** Perfil vinculado à conta; edição pelo próprio usuário. Se for **verificado**, toda alteração entra em re-verificação (RF2.3).

### RF1.3 — Cadastro de igreja

- **RF1.3.1** Usuário que escolheu tipo “Igreja” informa: **CNPJ da igreja**, nome da igreja, e-mail do contato, telefone, CEP, cidade.
- **RF1.3.2** CEP pode ser usado para obter cidade via API (Brasil; sem cache por hora).
- **RF1.3.3** Uma conta = uma igreja; status pendente até aprovação (Fase 2).

### RF1.4 — Autenticação

- **RF1.4.1** Login por e-mail e senha.
- **RF1.4.2** Sessão com duração de **8 horas**; regenerar ID após login; logout invalida sessão.
- **RF1.4.3** Rotas protegidas exigem usuário autenticado; redirecionamento para login quando não autenticado.
- **RF1.4.4** **Recuperação de senha** (“lembrete de senha”): fluxo por e-mail (link ou token) para redefinir senha.

### RF1.5 — CRUD de profissional (próprio perfil)

- **RF1.5.1** Usuário ministro pode visualizar e editar seu próprio perfil (nome, telefone, cidade, habilidades/dons). Se verificado, alterações vão para re-verificação.

### RF1.6 — CRUD de igreja (própria)

- **RF1.6.1** Usuário igreja pode visualizar e editar os dados da sua igreja (nome, telefone, CEP, cidade, e-mail). Alterações de igreja pendentes de aprovação conforme regra da Fase 2.

---

## Fase 2 — Aprovação, verificação e denúncias

### RF2.1 — Aprovação de igrejas

- **RF2.1.1** Novos cadastros de igreja ficam com status “pendente” até aprovação de um administrador.
- **RF2.1.2** Alterações em igreja podem voltar para aprovação (conforme regra de negócio definida).
- **RF2.1.3** Apenas igrejas aprovadas aparecem na busca e em vitrines.
- **RF2.1.4** Administrador pode aprovar ou rejeitar (com motivo opcional).

### RF2.2 — Selo Verificado (profissionais)

- **RF2.2.1** Administrador pode atribuir ou remover selo “Verificado” em perfil de profissional.
- **RF2.2.2** Perfis públicos exibem o selo quando atribuído.

### RF2.3 — Re-verificação (usuário verificado)

- **RF2.3.1** Quando um profissional **verificado** altera o perfil, a alteração fica **pendente de re-verificação** até o admin aprovar; o perfil público continua exibindo os dados antigos até a aprovação.

### RF2.4 — Denúncias e moderação

- **RF2.4.1** Qualquer usuário logado pode **denunciar** outro usuário (profissional ou igreja), informando uma **descrição** (texto livre) do motivo.
- **RF2.4.2** Na área administrativa deve ser possível **listar usuários denunciados**, com quantidade de denúncias e acesso à **descrição** de cada denúncia.
- **RF2.4.3** Administrador pode **inativar** um usuário denunciado (ex.: caso de falsa identidade). Usuário inativo não aparece na busca e não pode fazer login (ou fluxo equivalente).

---

## Fase 3 — Busca e solicitações

### RF3.1 — Busca por localização

- **RF3.1.1** Usuário informa **CEP** ou **cidade** e **raio em km** (ex.: 10 km). Apenas Brasil; API de CEP; sem cache por hora.
- **RF3.1.2** Sistema retorna profissionais e/ou igrejas dentro do raio, considerando apenas cadastros aprovados e ativos.
- **RF3.1.3** Filtro por tipo (profissional/igreja) e, para profissionais, por habilidade/dons (conforme implementação).

### RF3.2 — Solicitação de contato

- **RF3.2.1** Usuário logado (conta mínima ou com perfil) pode enviar solicitação a um profissional ou igreja.
- **RF3.2.2** Solicitação inclui **texto livre obrigatório** explicando o evento/motivo do contato, para o destinatário entender e decidir se responde.
- **RF3.2.3** O destinatário recebe notificação por e-mail com dados do solicitante (nome, e-mail, etc.) e o **texto da mensagem**.
- **RF3.2.4** Opcional no MVP: área “Minhas solicitações” para o destinatário ver no site.

---

## Fora do MVP

- Blog.
- Cache de CEP.
- Notificações in-app.

---

## Acesso e incentivo ao cadastro

- Visitante pode ver proposta do site e acessar busca e cadastro (home com links).
- Conta mínima (nome, e-mail, senha) permite enviar solicitações.
- Cadastro como ministro ou igreja permite ser encontrado na busca e receber solicitações.
