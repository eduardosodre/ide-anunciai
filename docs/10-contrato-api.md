# Contrato HTTP (backend) — ide-anunciai

Contrato técnico para o backend PHP (sem framework), alinhado às rotas de [07-contratos-interfaces.md](07-contratos-interfaces.md). Objetivo: implementação e testes com comportamento previsível.

---

## Princípios

- **HTML first** nas rotas de página: resposta `text/html` com redirecionamentos e mensagens flash quando fizer sentido.
- **JSON** obrigatório no MVP via rotas prefixadas `/api`, mantendo também as rotas HTML com formulários.
- **Autenticação:** cookie de sessão PHP; rotas admin exigem `usuario.admin = 1`.
- **CSRF:** token em todos os formulários POST que alteram estado.
- **Idempotência:** recuperação de senha e redefinição não duplicam efeitos (token uso único).

---

## Formato de erro (JSON, quando aplicável)

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Mensagem legível",
    "fields": { "email": "E-mail inválido" }
  }
}
```

| HTTP | code sugerido | Uso |
|------|----------------|-----|
| 400 | VALIDATION_ERROR | Campos inválidos |
| 401 | UNAUTHENTICATED | Sessão ausente ou expirada |
| 403 | FORBIDDEN | Autenticado mas sem permissão |
| 404 | NOT_FOUND | Recurso inexistente |
| 409 | CONFLICT | E-mail duplicado, estado inválido |
| 422 | BUSINESS_RULE | Regra de negócio (ex.: perfil pendente) |
| 429 | RATE_LIMIT | Limite de requisições |
| 500 | INTERNAL_ERROR | Falha não tratada (log server-side) |

---

## Rotas públicas

### GET `/`

- **200:** HTML home.

### GET `/busca` | POST `/busca`

| Parâmetro | Tipo | Obrigatório |
|-----------|------|-------------|
| cep | string | um de cep ou cidade |
| cidade | string | um de cep ou cidade |
| estado | string (UF, 2 letras) | não; recomendado com cidade para homônimos; com CEP o centro usa UF do ViaCEP |
| raio_km | int | sim |
| tipo | enum: profissionais, igrejas, ambos | sim ou default `ambos` |
| habilidade_id | int | não |
| pagina | int | não (default 1) |
| limite | int | não (default ex.: 20, máx. 50) |

- **200:** HTML resultados + meta paginação (query string repetida).

### GET `/perfil/profissional/{uuid}` | GET `/perfil/igreja/{uuid}`

- `{uuid}`: UUID v4 (`perfil_uuid`). URLs antigas só com número (`/perfil/profissional/1`) respondem **301** para a URL canônica com UUID.

- **200:** HTML perfil público.
- **404:** perfil inexistente ou usuário inativo.

### GET/POST `/cadastro`

- POST: nome, email, senha, confirmação, consentimento, CSRF.
- **302:** sucesso → login ou área logada.
- **422:** erros de validação.

### GET/POST `/login`

- POST: email, senha, CSRF.
- **302:** sucesso → destino padrão (ex.: `/conta`).
- **401/422:** credenciais inválidas.

### GET/POST `/recuperar-senha` | GET/POST `/redefinir-senha`

- Recuperar: POST email → envia e-mail (sempre mensagem genérica para não enumerar contas — **recomendado**).
- Redefinir: query `token`; POST nova_senha + confirmação.

### GET `/privacidade` | GET `/termos`

- **200:** HTML estático ou CMS mínimo.

---

## Rotas autenticadas (usuário)

Prefixo lógico: exige sessão válida.

| Método | Rota | Ação |
|--------|------|------|
| GET | `/conta` | Form edição conta |
| POST | `/conta` | Atualiza nome, email, foto |
| POST | `/sair` | Logout (CSRF) |
| GET/POST | `/meu-perfil/ministro` | CRUD ministro |
| GET/POST | `/meu-perfil/igreja` | CRUD igreja |
| GET/POST | `/verificacao` | Solicitar selo + upload |
| GET | `/minhas-solicitacoes` | Opcional MVP |

### POST `/chat/iniciar` (ou anexado ao perfil)

| Campo | Tipo |
|-------|------|
| tipo_destino | profissional \| igreja |
| destino_id | int |
| mensagem_inicial | text (obrigatório) |
| csrf_token | string |

- **302/200:** sucesso + conversa criada.
- **403:** não pode solicitar a si mesmo **(regra em [11-regras-negocio.md](11-regras-negocio.md))**.

### GET `/chat` | GET `/chat/{conversa_id}` | POST `/chat/{conversa_id}/mensagens`

- Rotas HTML para listar conversas, abrir conversa e enviar nova mensagem.

### POST `/denunciar`

| Campo | Tipo |
|-------|------|
| usuario_alvo_id | int |
| descricao | text (obrigatório) |
| csrf_token | string |

---

## Rotas administrativas

Exige `usuario.admin = 1`.

| Método | Rota | Ação |
|--------|------|------|
| GET | `/admin/verificacoes` | Fila pendente |
| POST | `/admin/verificacoes/{id}/aprovar` | Aprovar |
| POST | `/admin/verificacoes/{id}/rejeitar` | Rejeitar + motivo opcional |
| GET | `/admin/revisao` | Fila re-verificação |
| GET | `/admin/denuncias` | Lista denunciados |
| POST | `/admin/usuarios/{id}/inativar` | Inativa usuário |

---

## Endpoints `/api` (MVP)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/auth/register` | Cadastro conta base |
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/auth/password/forgot` | Solicitar recuperação |
| POST | `/api/auth/password/reset` | Redefinir senha por token |
| GET | `/api/search` | Busca por CEP ou cidade+UF/raio/filtros; `meta.centro` inclui `label` e `uf` quando resolvido |
| POST | `/api/chat/conversations` | Iniciar conversa com mensagem inicial |
| GET | `/api/chat/conversations` | Listar conversas do usuário |
| GET | `/api/chat/conversations/{id}` | Detalhar conversa |
| POST | `/api/chat/conversations/{id}/messages` | Enviar mensagem |
| POST | `/api/reports` | Enviar denúncia |
| GET | `/api/privacy/export` | Exportar dados do titular (MVP) |

## Headers e cookies

- `Set-Cookie`: sessão **HttpOnly**, `Secure` em produção, `SameSite=Lax` (mínimo).
- Sem credenciais em query string para ações sensíveis.

---

## Versionamento

- MVP usa `/api` sem `/v1`. Evolução futura poderá migrar para `/api/v1`.
