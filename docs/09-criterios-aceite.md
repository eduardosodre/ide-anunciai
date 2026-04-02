# Critérios de aceite — ide-anunciai

Critérios objetivos para validar o MVP. Cada item deve ser demonstrável (manual ou automatizado). Referência cruzada: [03-requisitos-funcionais.md](03-requisitos-funcionais.md), [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md).

---

## Convenções

- **Dado / Quando / Então** descreve o comportamento esperado.
- **Bloqueante** = não libera etapa ou release sem atender.
- Decisões de negócio foram consolidadas em [11-regras-negocio.md](11-regras-negocio.md).

---

## Fase 1 — Fundação

### RF1.1 — Cadastro de usuário

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF1.1.1 | Formulário aceita nome completo, e-mail e senha; submissão válida cria usuário e redireciona conforme UX. | Sim |
| A-RF1.1.2 | Após cadastro + login, usuário acessa área logada sem exigir documentos. | Sim |
| A-RF1.1.3 | Usuário logado pode enviar foto de perfil; imagem aparece na conta e onde a UX definir. | Sim |
| A-RF1.1.4 | Senha rejeitada se fora da política; aceita apenas hash bcrypt no armazenamento (nunca texto plano). | Sim |
| A-RF1.1.5 | Tentativa de cadastro com e-mail já existente retorna erro claro. | Sim |
| A-RF1.1.6 | Cadastro sem aceite da Política de Privacidade é bloqueado; consentimento com data/hora persistida. | Sim |

### RF1.2 / RF1.3 — Perfis ministro e igreja

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF1.2 | Usuário logado cria/edita perfil ministro com nome público, telefone, cidade e ≥1 habilidade. | Sim |
| A-RF1.3 | Usuário logado cria/edita perfil igreja com campos obrigatórios de [07-contratos-interfaces.md](07-contratos-interfaces.md). | Sim |

### RF1.4 — Autenticação

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF1.4.1 | Login com e-mail + senha válidos cria sessão. | Sim |
| A-RF1.4.2 | Sessão expira após 8h de inatividade ou critério equivalente documentado; logout invalida sessão; ID regenerado no login. | Sim |
| A-RF1.4.3 | Acesso a rota protegida sem sessão redireciona para login (ou 401 em API). | Sim |
| A-RF1.4.4 | Fluxo recuperação: solicita e-mail → token → redefinição; token expira e é uso único. | Sim |

### RF1.5 / RF1.6 — CRUD próprio

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF1.5 | Ministro edita apenas o próprio perfil; outro usuário não altera via URL/tampering. | Sim |
| A-RF1.6 | Igreja edita apenas o próprio perfil; regras de aprovação/re-verificação conforme Fase 2. | Sim |

---

## Fase 2 — Verificação e denúncias

### RF2.1 / RF2.2 — Verificação

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF2.1 | Solicitação ministro exige RG, CPF e anexos; admin aprova/rejeita; selo exibido quando aprovado. | Sim |
| A-RF2.2 | Solicitação igreja exige CNPJ e anexos; idem fluxo admin. | Sim |
| A-RF2.3 | Alteração em perfil verificado gera pendência; público vê snapshot aprovado até nova aprovação. | Sim |

### RF2.4 — Denúncias

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF2.4.1 | Usuário logado denuncia outro com descrição obrigatória. | Sim |
| A-RF2.4.2 | Admin lista denunciados, contagens e textos das denúncias. | Sim |
| A-RF2.4.3 | Admin inativa usuário; inativo não loga e não aparece na busca. | Sim |

---

## Fase 3 — Busca e solicitações

### RF3.1 — Busca

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF3.1.1 | Busca por CEP ou cidade + raio (km) retorna resultados dentro do raio (Brasil). | Sim |
| A-RF3.1.2 | Apenas perfis ativos; filtros tipo e habilidade quando aplicável. | Sim |
| A-RF3.1.3 | Visitante e logado não visualizam telefone publicamente; contato inicial apenas por mensagem/chat conforme [11-regras-negocio.md](11-regras-negocio.md). | Sim |

### RF3.2 — Primeiro contato por mensagem/chat

| ID | Critério | Bloqueante |
|----|----------|------------|
| A-RF3.2.1 | Apenas logado inicia conversa; primeira mensagem em texto livre é obrigatória. | Sim |
| A-RF3.2.2 | Destinatário recebe e-mail com solicitante + primeira mensagem. | Sim |
| A-RF3.2.3 | MVP possui listagem e detalhe de conversas conforme [07-contratos-interfaces.md](07-contratos-interfaces.md). | Sim |

---

## Por etapa de desenvolvimento ([08](08-etapas-desenvolvimento.md))

| Etapa | Aceite resumido |
|-------|-----------------|
| 2 | Projeto roda localmente; `database.php.example` presente; home estática acessível. |
| 3 | Cadastro, login, logout, recuperação senha, edição conta + foto, rotas protegidas. |
| 4 | CRUD ministro + catálogo habilidades + perfil público. |
| 5 | CRUD igreja + perfil público + CEP→cidade. |
| 6 | Filas admin verificação + re-verificação + selos. |
| 7 | Denúncias + painel admin + inativação. |
| 8 | Busca raio + filtros + regras de exibição pendente_revisao. |
| 9 | Chat inicial + e-mail + listagem/detalhe de conversas. |
| 10 | Responsivo, `/privacidade`, revisão UX e segurança para deploy. |

---

## Definição de pronto (DoD) do MVP

- Todos os critérios bloqueantes das tabelas acima atendidos.
- Política de privacidade publicada e consentimento rastreável.
- Erros de validação e falhas técnicas com mensagens seguras (sem vazar stack em produção).
- Checklist de segurança mínima em [14-plano-testes-mvp.md](14-plano-testes-mvp.md) executado.
