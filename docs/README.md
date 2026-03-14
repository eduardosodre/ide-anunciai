# Documentação OpenSpec / Spec-Driven Development (SDD) — ide-anunciai

Documentação de especificação que orienta o desenvolvimento da plataforma de networking religioso. O código será implementado **após** validação destes artefatos.

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
| [02-personas-glossario.md](02-personas-glossario.md) | Personas, papéis (uma conta = um tipo), glossário |
| [03-requisitos-funcionais.md](03-requisitos-funcionais.md) | Requisitos funcionais por fase (SDD) |
| [04-seguranca-lgpd.md](04-seguranca-lgpd.md) | Segurança (8h sessão, senha, recuperação) e LGPD |
| [05-modelo-dados.md](05-modelo-dados.md) | Modelo de dados (usuario, profissional, igreja, denúncia, etc.) |
| [06-ux-navegacao.md](06-ux-navegacao.md) | Layout, usabilidade, home (proposta + links), responsivo |
| [07-contratos-interfaces.md](07-contratos-interfaces.md) | Contratos de telas, formulários e buscas |
| [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md) | **10 etapas** de desenvolvimento (SDD = Etapa 1) |

## Fluxo de trabalho

1. **Etapa 1 (atual):** documentação SDD — validar com o usuário os docs 00 a 08.
2. **Etapas 2 a 10:** implementação em PHP/MySQL/HTML/CSS/JS conforme [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md).
