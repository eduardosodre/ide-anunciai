# Modelo de dados — ide-anunciai

Visão lógica das entidades para implementação em MySQL (utf8mb4). Alinhado a uma conta base de usuário (nome, e-mail, senha) com perfis vinculados de ministro e igreja.

---

## Entidades principais

### usuario

Conta de acesso (login) sempre criada com dados básicos.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| nome | VARCHAR(255) | sim | Nome completo da conta base |
| email | VARCHAR(255) UNIQUE | sim | |
| senha_hash | VARCHAR(255) | sim | bcrypt |
| ativo | TINYINT(1) DEFAULT 1 | sim | 0 = inativado (ex.: por denúncia) |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |
| consentimento_em | DATETIME | sim | Aceite da política |
| versao_politica_aceita | VARCHAR(50) | não | Versão da política |
| admin | TINYINT(1) DEFAULT 0 | sim | 1 = administrador (aprovações, verificado, denúncias) |

### profissional (ministro)

Um usuário pode ter um registro de ministro/profissional vinculado.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| usuario_id | INT FK → usuario.id | sim | UNIQUE (1:1) |
| nome_completo | VARCHAR(255) | sim | |
| telefone | VARCHAR(50) | não | |
| cidade | VARCHAR(255) | sim | Município (sem UF no texto; ver `estado`) |
| estado | VARCHAR(2) | não | UF (ex.: SP); usado na busca e geocodificação |
| rg | VARCHAR(30) | não | Exigido para solicitar verificação |
| cpf | VARCHAR(14) | não | Exigido para solicitar verificação |
| foto_url | VARCHAR(500) | não | Foto opcional do usuário/perfil |
| verificado | TINYINT(1) DEFAULT 0 | sim | Selo Verificado do ministro |
| pendente_revisao | TINYINT(1) DEFAULT 0 | sim | 1 = alteração aguardando re-verificação |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |

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

Um usuário pode ter um registro de igreja vinculado.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| usuario_id | INT FK → usuario.id | sim | UNIQUE (1:1) |
| cnpj | VARCHAR(20) | sim | CNPJ da igreja |
| nome_igreja | VARCHAR(255) | sim | |
| email_contato | VARCHAR(255) | sim | |
| telefone | VARCHAR(50) | não | |
| cep | VARCHAR(20) | sim | |
| cidade | VARCHAR(255) | sim | Município |
| estado | VARCHAR(2) | não | UF (ViaCEP ao salvar CEP) |
| verificado | TINYINT(1) DEFAULT 0 | sim | Selo Verificado da igreja |
| pendente_revisao | TINYINT(1) DEFAULT 0 | sim | 1 = alteração aguardando re-verificação |
| criado_em | DATETIME | sim | |
| atualizado_em | DATETIME | sim | |

### verificacao_solicitacao

Solicitações de verificação de perfil (ministro ou igreja).

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| usuario_id | INT FK → usuario.id | sim | Dono da solicitação |
| tipo_perfil | ENUM('ministro','igreja') | sim | Perfil solicitado |
| perfil_id | INT | sim | profissional.id ou igreja.id |
| status | ENUM('pendente','aprovada','rejeitada') | sim | DEFAULT 'pendente' |
| motivo_rejeicao | TEXT | não | |
| criado_em | DATETIME | sim | |
| analisado_em | DATETIME | não | |
| analisado_por | INT FK → usuario.id | não | Admin |

### verificacao_documento

Anexos de documentos associados a uma solicitação de verificação.

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| solicitacao_id | INT FK → verificacao_solicitacao.id | sim | |
| tipo_documento | ENUM('rg','cpf','cnpj','comprovante') | sim | |
| arquivo_url | VARCHAR(500) | sim | Caminho/URL protegido |
| criado_em | DATETIME | sim | |

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

### denuncia

Registro de denúncia entre usuários (RF2.4). Detalhes de regras em [11-regras-negocio.md](11-regras-negocio.md).

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| id | INT PK AUTO_INCREMENT | sim | |
| denunciante_id | INT FK → usuario.id | sim | Quem denuncia |
| denunciado_id | INT FK → usuario.id | sim | Usuário alvo |
| descricao | TEXT | sim | Motivo em texto livre |
| criado_em | DATETIME | sim | |

O papel de administrador é indicado pelo campo **usuario.admin** (1 = admin). Admin aprova/rejeita verificações e re-verifica alterações em perfis verificados.

---

## Geolocalização (busca por raio)

- Adicionar **latitude** e **longitude** em `profissional` e `igreja` (preenchidos a partir de CEP/cidade via API; por hora sem cache).
- Busca por raio: fórmula Haversine (ou equivalente) em SQL para filtrar por distância em km a partir do ponto informado (CEP ou cidade).

---

## Índices

- usuario(email) UNIQUE; usuario(ativo).
- profissional(usuario_id) UNIQUE; profissional(cidade); profissional(verificado); profissional(pendente_revisao).
- igreja(usuario_id) UNIQUE; igreja(cidade); igreja(verificado); igreja(cnpj).
- verificacao_solicitacao(usuario_id, tipo_perfil, status).
- verificacao_documento(solicitacao_id, tipo_documento).
- solicitacao(solicitante_id); solicitacao(destino_id, tipo_destino).
- denuncia(denunciado_id); denuncia(denunciante_id, denunciado_id, criado_em).
