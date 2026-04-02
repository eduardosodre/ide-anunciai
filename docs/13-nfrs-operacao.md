# Requisitos não funcionais e operação — ide-anunciai

Limites, desempenho, observabilidade e conformidade operacional do MVP.

---

## Desempenho e escalabilidade (MVP)

- Tempo de resposta alvo para páginas dinâmicas **< 2s P95** em ambiente de referência (hardware modesto).
- Busca com paginação obrigatória; `limite` máximo **50** por requisição.
- Queries com prepared statements e índices alinhados a [05-modelo-dados.md](05-modelo-dados.md).

---

## Disponibilidade e deploy

- **HTTPS** obrigatório em produção ([04](04-seguranca-lgpd.md)).
- Variáveis sensíveis fora do repositório; `database.php` ignorado no Git.

---

## Armazenamento e uploads

| Item | Valor definido |
|------|------------------------------|
| Foto de perfil | máx. **5 MB**; tipos: JPEG, PNG, WebP |
| Documentos de verificação | máx. **10 MB** por arquivo; tipos: PDF, JPEG, PNG |
| Quantidade de anexos por solicitação de verificação | máx. **5 arquivos** |
| Retenção de anexos após rejeição/exclusão | **90 dias** ou política em [04](04-seguranca-lgpd.md) |

Arquivos sensíveis em diretório **não servido** diretamente pelo web root; entrega via script com autorização.

---

## E-mail (SMTP)

- Configuração por ambiente (host, porta, TLS, usuário).
- Timeout e log de falhas sem gravar corpo completo de e-mails em log público.
- Retry: até 3 tentativas com backoff simples (30s, 2min, 10min), com log de falha final.

---

## Segurança

- Rate limiting no MVP:
  - login: 10 tentativas/15min por IP e 5 tentativas/15min por e-mail
  - recuperação de senha: 3 solicitações/h por e-mail e por IP
  - cadastro: 5 tentativas/h por IP
  - início de conversa: 20 novas conversas/dia por usuário
  - envio de mensagens de chat: 120 mensagens/h por usuário
  - denúncia: 3 denúncias/dia por par (denunciante, alvo) e 10 denúncias/dia por denunciante
- Cabeçalhos obrigatórios no MVP: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.
- Logs sem dados sensíveis (senha, token completo, documentos).

---

## Observabilidade

- Log de aplicação: nível erro + eventos críticos (falha e-mail, falha API CEP).
- Página `/health` no MVP retornando `ok` e versão do build.

---

## Backup e recuperação

- Backup MySQL diário em produção; retenção de 14 dias; janela noturna (00:00–03:00).
- Procedimento de restore documentado fora deste repositório (runbook interno).

---

## LGPD — retenção (proposta de fechamento)

| Dado / situação | Retenção sugerida |
|-----------------|-------------------|
| Conta ativa | enquanto existir conta |
| Após exclusão de conta | 30 dias para exclusão definitiva; anonimizar logs de acesso identificáveis |
| Logs de segurança | **90 dias** |
| Tokens de recuperação de senha | expiração **1h** + remoção após uso |
| Versão da política aceita | histórico mínimo: data + versão |

---

## Acessibilidade e idioma

- PT-BR único no MVP ([06](06-ux-navegacao.md)).
- Contraste e teclado: meta WCAG nível AA.
