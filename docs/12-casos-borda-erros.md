# Casos de borda e erros — ide-anunciai

Comportamento esperado em falhas e extremos. Complementa [10-contrato-api.md](10-contrato-api.md) e [04-seguranca-lgpd.md](04-seguranca-lgpd.md).

---

## Autenticação e sessão

| Situação | Comportamento esperado |
|----------|------------------------|
| Sessão expirada (8h) ao POST | Redirecionar para `/login` com mensagem; preservar URL de retorno opcional |
| Duplo submit de login | Idempotente: uma sessão válida; sem erro ruidoso |
| Logout em aba duplicada | Ambas invalidadas após logout |
| CSRF inválido ou ausente | 403; não executar ação; mensagem genérica |

---

## Cadastro e conta

| Situação | Comportamento esperado |
|----------|------------------------|
| E-mail duplicado | Erro de validação no campo e-mail; HTTP 422/409 em API |
| Senha fraca | Mensagem alinhada à política em [04](04-seguranca-lgpd.md) |
| Upload de foto: tipo inválido | Rejeitar; MIME permitidos: `image/jpeg`, `image/png`, `image/webp` |
| Upload de foto: arquivo grande | Rejeitar com limite explícito |

---

## Recuperação de senha

| Situação | Comportamento esperado |
|----------|------------------------|
| E-mail inexistente | Mesma mensagem que sucesso (“se existir, enviaremos”) — anti-enumeração |
| Token expirado | Página de redefinição com erro; link para solicitar novo |
| Token já usado | Erro claro; solicitar novo fluxo |
| Múltiplos pedidos seguidos | Rate limit por IP/e-mail **(valores em [13-nfrs-operacao.md](13-nfrs-operacao.md))** |

---

## CEP, cidade e geolocalização

| Situação | Comportamento esperado |
|----------|------------------------|
| CEP inválido ou não encontrado | Erro no campo; não salvar lat/long vazios para busca por raio |
| API ViaCEP indisponível | Mensagem de serviço indisponível; retry manual |
| Cidade sem geocodificação | Conforme [11-regras-negocio.md](11-regras-negocio.md) seção 7 |

---

## Perfis e verificação

| Situação | Comportamento esperado |
|----------|------------------------|
| Usuário tenta editar perfil de outro | 403 |
| Upload documento verificação: corrupção/tamanho | Validar tamanho e tipo; erro inline |
| Admin aprova solicitação já processada | 409 ou mensagem de estado inválido |
| Perfil inativo na busca | Não listar; perfil público 404 |

---

## Busca

| Situação | Comportamento esperado |
|----------|------------------------|
| Nenhum resultado | Estado vazio amigável + sugestão de ampliar raio |
| Raio zero ou negativo | Validação; mínimo 1 km |
| pagina/limite fora do range | Corrigir para limites máximos ou erro 400 |

---

## Solicitações e denúncias

| Situação | Comportamento esperado |
|----------|------------------------|
| Início de conversa para si mesmo | Bloquear com mensagem |
| Destinatário inativo | 404 ou “indisponível” |
| Falha SMTP ao notificar | Registrar `notificado_em` nulo; retry/admin **(processo mínimo: log + fila manual)** |
| Denúncia duplicada spam | Aplicar rate limit definido em [11](11-regras-negocio.md) |

---

## LGPD

| Situação | Comportamento esperado |
|----------|------------------------|
| Exclusão de conta | Fluxo em [04](04-seguranca-lgpd.md); prazos em [13](13-nfrs-operacao.md) |
| Exportação no MVP | Disponibilizar JSON com dados do titular autenticado |
