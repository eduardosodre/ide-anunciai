# Modelo de dados — ide-anunciai

Visão lógica das entidades para implementação em MySQL (utf8mb4). Alinhado a uma conta = um tipo (igreja ou ministro) e a conta mínima (nome, e-mail, senha).

---

## Entidades principais

### usuario

Conta de acesso (login). Pode ser conta mínima (só nome, e-mail, senha), igreja ou ministro.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| nome | VARCHAR(255) | sim | Para conta mínima e exibição; ministro/igreja também têm nome ou nome_igreja |
| email | VARCHAR(255) UNIQUE | sim | |
| senha_hash | VARCHAR(255) | sim | bcrypt |
| tipo_conta | ENUM('minima','igreja','ministro') | sim | Uma conta = um tipo |
| ativo | TINYINT(1) DEFAULT 1 | sim | 0 = inativado (ex.: por denúncia) |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |
| consentimento_em | DATETIME | sim | Aceite da política |
| versao_politica_aceita | VARCHAR(50) | não | Versão da política |
| admin | TINYINT(1) DEFAULT 0 | sim | 1 = administrador (aprovações, verificado, denúncias) |

### profissional (ministro)

Um usuário com tipo_conta = 'ministro' tem um registro aqui.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| usuario_id | INT FK → usuario.id | sim | UNIQUE (1:1) |
| nome | VARCHAR(255) | sim | |
| telefone | VARCHAR(50) | não | |
| cidade | VARCHAR(255) | sim | |
| cpf_ou_cnpj | VARCHAR(20) | sim | CPF ou CNPJ de pregador |
| verificado | TINYINT(1) DEFAULT 0 | sim | Selo Verificado |
| pendente_revisao | TINYINT(1) DEFAULT 0 | sim | 1 = alteração aguardando re-verificação |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |

E-mail pode vir de usuario.email.

### habilidade

Catálogo de habilidades/dons (ensinar, ministrar, orar, tocar instrumento, etc.).

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| codigo | VARCHAR(50) UNIQUE | sim | ex.: ensinar, ministrar, orar |
| label | VARCHAR(100) | sim | Exibição |

### profissional_habilidade

Relação N:N entre profissional e habilidades.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| profissional_id | INT FK → profissional.id | sim | |
| habilidade_id | INT FK → habilidade.id | sim | |
| PK (profissional_id, habilidade_id) | | | |

### igreja

Um usuário com tipo_conta = 'igreja' tem um registro aqui.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| usuario_id | INT FK → usuario.id | sim | UNIQUE (1:1) |
| cnpj | VARCHAR(20) | sim | CNPJ da igreja |
| nome_igreja | VARCHAR(255) | sim | |
| email_contato | VARCHAR(255) | sim | |
| telefone | VARCHAR(50) | não | |
| cep | VARCHAR(20) | sim | |
| cidade | VARCHAR(255) | sim | |
| status_aprovacao | ENUM('pendente','aprovado','rejeitado') | sim | DEFAULT 'pendente' |
| aprovado_em | DATETIME | não | |
| aprovado_por | INT FK → usuario.id | não | Admin |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |

### denuncia

Registro de denúncia de um usuário contra outro.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| denunciante_id | INT FK → usuario.id | sim | Quem denunciou |
| denunciado_id | INT FK → usuario.id | sim | Quem foi denunciado |
| descricao | TEXT | sim | Motivo da denúncia (ex.: falsa identidade) |
| criado_em | DATETIME | sim | |
| status | ENUM('pendente','analisada') | sim | DEFAULT 'pendente' |

Admin lista usuários denunciados (agrupando por denunciado_id), vê descrição e pode inativar usuario (ativo = 0).

### solicitacao

Solicitação de contato com texto livre sobre o evento.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| solicitante_id | INT FK → usuario.id | sim | |
| tipo_destino | ENUM('profissional','igreja') | sim | |
| destino_id | INT | sim | profissional.id ou igreja.id |
| mensagem | TEXT | sim | Texto livre obrigatório (evento/motivo) |
| criado_em | DATETIME | sim | |
| notificado_em | DATETIME | não | Quando o e-mail foi enviado |

O papel de administrador é indicado pelo campo **usuario.admin** (1 = admin). Admin aprova igrejas, gerencia selo Verificado, re-verifica alterações e gerencia denúncias (ver descrição e inativar).

---

## Geolocalização (busca por raio)

- Adicionar **latitude** e **longitude** em `profissional` e `igreja` (preenchidos a partir de CEP/cidade via API; por hora sem cache).
- Busca por raio: fórmula Haversine (ou equivalente) em SQL para filtrar por distância em km a partir do ponto informado (CEP ou cidade).

---

## Índices

- usuario(email) UNIQUE; usuario(ativo); usuario(tipo_conta).
- profissional(usuario_id) UNIQUE; profissional(cidade); profissional(verificado); profissional(pendente_revisao).
- igreja(usuario_id) UNIQUE; igreja(cidade); igreja(status_aprovacao); igreja(cnpj).
- denuncia(denunciado_id); denuncia(denunciante_id).
- solicitacao(solicitante_id); solicitacao(destino_id, tipo_destino).
