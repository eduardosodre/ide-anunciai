# Documentação OpenSpec / Spec-Driven Development (SDD) — ide-anunciai

Documentação de especificação que orienta o desenvolvimento da plataforma de networking religioso. A base técnica das **Etapas 2 a 5** está implementada no repositório (fundação, autenticação, perfil ministro, perfil igreja com ViaCEP, APIs públicas iniciais).

## Stack e restrições

- **Backend:** PHP (sem frameworks)
- **Banco:** MySQL (utf8mb4)
- **Frontend:** HTML, CSS, JavaScript
- **Segurança:** cuidado explícito em todas as rotas e dados; sessão 8h; política de senha segura; recuperação de senha
- **LGPD:** conformidade desde o MVP
- **Arquivo `database.php`:** não versionado; **database.php.example** versionado no Git

## Índice dos artefatos

| Doc | Conteúdo |
|-----|----------|
| [00-perguntas-descoberta.md](00-perguntas-descoberta.md) | **Decisões tomadas** (respostas da descoberta) |
| [01-visao-produto.md](01-visao-produto.md) | Visão do produto, objetivos e escopo do MVP |
| [02-personas-glossario.md](02-personas-glossario.md) | Personas e glossário (conta base + perfis vinculados) |
| [03-requisitos-funcionais.md](03-requisitos-funcionais.md) | Requisitos funcionais por fase (SDD) |
| [04-seguranca-lgpd.md](04-seguranca-lgpd.md) | Segurança (8h sessão, senha, recuperação) e LGPD |
| [05-modelo-dados.md](05-modelo-dados.md) | Modelo de dados (usuario, profissional, igreja, denúncia, etc.) |
| [06-ux-navegacao.md](06-ux-navegacao.md) | Layout, usabilidade, home (proposta + links), responsivo |
| [07-contratos-interfaces.md](07-contratos-interfaces.md) | Contratos de telas, formulários e buscas |
| [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md) | **10 etapas** de desenvolvimento (SDD = Etapa 1) |
| [09-criterios-aceite.md](09-criterios-aceite.md) | Critérios de aceite por RF e por etapa (DoD) |
| [10-contrato-api.md](10-contrato-api.md) | Contrato HTTP, erros, rotas e CSRF |
| [11-regras-negocio.md](11-regras-negocio.md) | Regras de negócio, visibilidade, denúncias, complemento modelo |
| [12-casos-borda-erros.md](12-casos-borda-erros.md) | Casos de borda e comportamento em falhas |
| [13-nfrs-operacao.md](13-nfrs-operacao.md) | NFRs, limites, e-mail, LGPD operacional |
| [14-plano-testes-mvp.md](14-plano-testes-mvp.md) | Plano de testes e checklist de segurança |

## Fluxo de trabalho

1. **Etapa 1:** documentação SDD — docs **00 a 14** (core 00–08; clarificação OpenSpec **09–14**).
2. **Etapas 2 a 10:** implementação em PHP/MySQL/HTML/CSS/JS conforme [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md).

## Nota de validação

- Esta documentação está preparada para validação no fluxo de specs (OpenSpec) antes de iniciar código.
- Instalação e execução local: ver **[README.md](../README.md)** na raiz do repositório.
