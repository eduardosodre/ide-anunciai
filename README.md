# ide-anunciai

Plataforma de networking religioso — conecta igrejas, ministros e profissionais.

Stack: PHP (sem framework), MySQL, HTML/CSS/JS.

**Interface:** estilos globais em `src/View/Html.php`; formulários de cadastro, login, recuperação/redefinição de senha e busca usam `public/js/app.js` com `fetch` às rotas `/api/auth/*` e `GET /api/search`, validação inline e sem perda de dados ao errar.

## Requisitos

- PHP **8.1+** (extensões: `pdo_mysql`, `mbstring`, `json`, `fileinfo` para upload de imagem; `allow_url_fopen=On` recomendado para consulta ViaCEP em desenvolvimento)
- MySQL **8.x** ou compatível (MariaDB 10.5+)
- Servidor web (Apache com `mod_rewrite` **ou** servidor embutido do PHP para desenvolvimento)

## Instalação — passo a passo

### 1. Clonar o repositório

```bash
git clone <url-do-repositório>
cd ide-anunciai
```

### 2. Criar o banco de dados

No MySQL (ex.: cliente ou phpMyAdmin):

```sql
CREATE DATABASE ide_anunciai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Criar o usuário SQL (opcional)

Se não usar `root`, crie um usuário com permissão no banco acima e anote `usuario` e `senha`.

### 4. Aplicar o schema

```bash
mysql -u SEU_USUARIO -p ide_anunciai < database/schema.sql
```

Se você já tinha criado o banco **antes** da Etapa 5 e só precisa da tabela `igreja`, pode aplicar também:

```bash
mysql -u SEU_USUARIO -p ide_anunciai < database/migrations/20260331_add_igreja.sql
```

Para atualizar um banco já existente para a **Etapa 6** (verificação e re-verificação):

```bash
mysql -u SEU_USUARIO -p ide_anunciai < database/migrations/20260331_add_verificacao_etapa6.sql
```

Para atualizar um banco já existente para a **Etapa 7** (denúncias e moderação):

```bash
mysql -u SEU_USUARIO -p ide_anunciai < database/migrations/20260331_add_denuncia_etapa7.sql
```

Para atualizar um banco já existente para a **Etapa 9** (chat e coordenadas em `profissional`):

```bash
mysql -u SEU_USUARIO -p ide_anunciai < database/migrations/20260401_add_chat_etapa9.sql
```

Se o MySQL retornar erro de coluna duplicada em `profissional` (latitude/longitude já existentes), aplique apenas o trecho `CREATE TABLE` do arquivo de migração manualmente ou ajuste o script.

### 5. Configurar credenciais

Copie o exemplo e edite:

```bash
copy database.php.example database.php
```

No Windows PowerShell, se preferir:

```powershell
Copy-Item database.php.example database.php
```

Edite `database.php` e preencha `host`, `database`, `user`, `password`. Ajuste também:

- `base_url` — URL base do site (para links de recuperação de senha), ex.: `http://localhost:8080`
- `policy_version` — versão da política aceita no cadastro (ex.: `1.0`)

Variáveis de ambiente opcionais (sobrescrevem valores): `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_BASE_URL`, `POLICY_VERSION`.

### 6. Permissões de pasta (Linux/macOS)

Garanta escrita em `storage/logs` e `public/uploads/avatars` (upload de foto):

```bash
chmod -R u+rwX storage/logs public/uploads
```

No Windows, geralmente não é necessário.

### 7. Rodar em desenvolvimento

Na raiz do projeto:

```bash
php -S localhost:8080 -t public
```

Abra no navegador:

- Home: `http://localhost:8080/`
- Cadastro: `http://localhost:8080/cadastro`
- Login: `http://localhost:8080/login`
- API health: `http://localhost:8080/api/health`

### 8. Apache (produção ou homologação)

Aponte o **DocumentRoot** para a pasta `public/` e habilite `AllowOverride` para o `.htaccess` redirecionar tudo para `index.php`.

### 9. E-mails (recuperação de senha)

O sistema envia e-mail com `mail()` quando o PHP estiver configurado para isso.  
Em qualquer ambiente, uma cópia do **texto** do e-mail é gravada em `storage/logs/mail.log` (útil em desenvolvimento no Windows).

## Estrutura de pastas (resumo)

```text
public/              # Front controller e assets estáticos
  index.php
  uploads/avatars/   # Fotos de perfil (gitignored)
src/                 # Código PHP (namespace App\)
database/
  schema.sql         # Tabela inicial
storage/logs/        # Logs (gitignored)
database.php         # Credenciais locais (não versionado)
```

## Documentação (OpenSpec / SDD)

A especificação está em **[docs/](docs/README.md)**. O desenvolvimento do código segue as etapas em `docs/08-etapas-desenvolvimento.md`.

- Arquivo `database.php` não é versionado; use `database.php.example` como referência.

## API (MVP)

- `GET /api/health`
- `GET /api/search` — busca por raio (`cep|cidade`, `raio_km`, `tipo`, `habilidade_id`, `pagina`, `limite`)
- `POST /api/auth/register` — JSON: `nome`, `email`, `senha`, `senha_confirmacao`, `consentimento`
- `POST /api/auth/login` — JSON: `email`, `senha`
- `POST /api/auth/logout`
- `POST /api/auth/password/forgot` — JSON: `email`
- `POST /api/auth/password/reset` — JSON: `token`, `nova_senha`, `nova_senha_confirmacao`
- `GET /api/privacy/export` — exporta dados do usuário (JSON, requer sessão)
- `GET /api/profissionais/{uuid}` — dados públicos do perfil ministro (aceita UUID ou ID numérico legado; resposta inclui `perfil_uuid`)
- `GET /api/igrejas/{uuid}` — dados públicos da igreja (idem)
- `POST /api/admin/verificacoes/{id}/decisao` — endpoint admin (sessão autenticada e `usuario.admin=1`) para aprovar/rejeitar solicitação (`acao=aprovar|rejeitar`, `motivo_rejeicao` opcional)
- `POST /api/reports` — registra denúncia (requer sessão): `usuario_alvo_id`, `descricao`
- `POST /api/chat/conversations` — inicia conversa (sessão): `tipo_destino` (`profissional`|`igreja`), `destino_id`, `mensagem_inicial`
- `GET /api/chat/conversations` — lista conversas do usuário (sessão)
- `GET /api/chat/conversations/{id}` — mensagens da conversa (sessão)
- `POST /api/chat/conversations/{id}/messages` — nova mensagem (sessão): `corpo`

Para testar com sessão, use o mesmo cookie de sessão após `login` ou `register` (cookie padrão `PHPSESSID`).

## Etapas 4, 5, 6, 7, 8 e 9 implementadas

### Etapa 4 — ministro

- Schema com `profissional`, `habilidade` e `profissional_habilidade` (N:N), incluindo `verificado` e `pendente_revisao`.
- CRUD do próprio perfil ministro em HTML: `GET/POST /meu-perfil/ministro`
- Perfil público: `GET /perfil/profissional/{uuid}` (sem telefone; link para iniciar conversa quando logado; UUID em `perfil_uuid`)

### Etapa 5 — igreja

- Schema com `igreja` (1:1 com `usuario`), campos `verificado` e `pendente_revisao`, `latitude`/`longitude` reservados para Etapa 8.
- CRUD: `GET/POST /meu-perfil/igreja` — cidade obtida via **ViaCEP** quando possível; e-mail de contato gravado = e-mail da conta.
- Perfil público: `GET /perfil/igreja/{uuid}` (sem telefone nem e-mail públicos; link para iniciar conversa quando logado)

### Etapa 6 — verificação e re-verificação

- Banco: tabelas `verificacao_solicitacao` e `verificacao_documento` + snapshot público aprovado para ministro e igreja.
- Usuário: `GET/POST /verificacao` para solicitar verificação de ministro (RG/CPF/docs metadados) e igreja (CNPJ/docs metadados), com histórico de status.
- Admin: `GET /admin/verificacoes` (fila pendente), `GET /admin/revisao` (pendências de perfis já verificados) e `POST /admin/verificacoes/decisao` para aprovar/rejeitar com motivo opcional.
- Regra central: ao editar perfil verificado, o perfil entra em revisão (`pendente_revisao=1`) e a exibição pública (HTML e API) continua mostrando o snapshot aprovado anterior até nova aprovação.

### Etapa 7 — denúncias e moderação

- Banco: tabela `denuncia` com índices para consulta administrativa e anti-abuso.
- Usuário: `GET/POST /denunciar` com descrição obrigatória e rate limit (3 por par/dia, 10 por denunciante/dia).
- Perfis públicos: link “Denunciar este perfil” em `/perfil/profissional/{uuid}` e `/perfil/igreja/{uuid}`.
- Admin: `GET /admin/denuncias` para listar denúncias e `POST /admin/usuarios/{id}/inativar` para moderação.

### Etapa 8 — busca por localização

- Busca HTML em `GET /busca` com formulário por `CEP` ou `cidade`, `raio_km`, `tipo` e `habilidade_id`.
- API em `GET /api/search` com os mesmos filtros e retorno por proximidade.
- Cálculo de distância com fórmula Haversine no banco.
- Coordenadas (`latitude`, `longitude`) atualizadas ao salvar perfis de ministro e igreja.

### Etapa 9 — chat (primeiro contato)

- Rotas HTML: `GET/POST /chat/iniciar`, `GET /chat`, `GET /chat/{id}`, `POST /chat/{id}/mensagens`.
- E-mail ao destinatário na primeira mensagem de uma conversa nova (log em `storage/logs/mail.log`).
- API JSON conforme tabela acima; todas exigem sessão autenticada.

### Etapa 10 — UX, responsivo e entrega

- Estilos globais e navegação responsiva no layout HTML compartilhado (`src/View/Html.php`).
- Home com hero e CTAs; atalhos para sessão logada.
- Busca e política de privacidade com estrutura visual consistente (cards).
- Perfis públicos (`/perfil/profissional/{uuid}`, `/perfil/igreja/{uuid}`): card, selo verificado, texto explícito de que telefone/e-mail não são públicos; visitante é orientado a entrar para conversa e denúncia; usuário logado vê botões de ação conforme regras.
- Deploy: em produção, use **HTTPS**, `DocumentRoot` em `public/`, `database.php` fora do repositório e `base_url` apontando para a URL pública. `GET /api/health` inclui `stage` (10 quando a Etapa 10 está fechada no código).

## Como validar a Etapa 6 (fluxo completo)

1. Criar usuário comum, preencher `meu-perfil/ministro` e/ou `meu-perfil/igreja`.
2. Enviar solicitação em `/verificacao`.
3. Acessar com usuário admin (`usuario.admin=1`) e aprovar em `/admin/verificacoes`.
4. Confirmar selo verificado e dados públicos atualizados em:
   - `/perfil/profissional/{uuid}` e `/api/profissionais/{uuid}`
   - `/perfil/igreja/{uuid}` e `/api/igrejas/{uuid}`
5. Editar um perfil já verificado (`/meu-perfil/ministro` ou `/meu-perfil/igreja`) alterando dados públicos.
6. Verificar que o perfil foi para revisão (`/admin/revisao`) e, enquanto pendente, o público continua vendo os dados aprovados anteriores.
7. Aprovar em `/admin/revisao` e confirmar que a nova versão foi publicada no HTML e na API.
