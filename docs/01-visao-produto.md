# Visão do produto — ide-anunciai

## Objetivo

Plataforma que conecta **igrejas** e **ministros/profissionais** do meio religioso em um ambiente confiável e acessível, com busca por localização (CEP + raio em km) e solicitações de contato com texto livre sobre o evento.

## Escopo do MVP

- Busca de profissionais e igrejas por **CEP ou cidade** e **raio em km** (definido pelo usuário na pesquisa). Apenas Brasil; API de CEP sem cache no primeiro momento.
- **Conta base única**: cadastro inicial com **nome completo, e-mail e senha**; após login o usuário pode completar o perfil e inserir foto.
- A mesma conta pode criar dois perfis vinculados:
  - **Ministro/Profissional** (com habilidades/dons para divulgação).
  - **Igreja**.
- Solicitação de contato com **texto livre** explicando o evento; notificação por e-mail ao destinatário.
- **Verificação por perfil:** ministro e igreja têm selos independentes; verificação exige documentação.
- **Recuperação de senha** e sessão de 8 horas.
- **Fora do MVP:** blog, cache de CEP, notificações in-app.

## Princípios

| Princípio | Descrição |
|-----------|-----------|
| Simplicidade | Cadastro inicial simples (nome, e-mail, senha) com entrada imediata na plataforma. |
| Confiabilidade | Selo Verificado por perfil (ministro e igreja) com validação documental. |
| Visibilidade | Busca por CEP/cidade e raio em km já no MVP. |
| Escalabilidade | Estrutura preparada para crescimento (habilidades, fotos, etc.). |
| Segurança | Sessão 8h, política de senha, recuperação de senha, proteção de rotas, LGPD. |
| LGPD | Consentimento, política de privacidade, direitos do titular desde o MVP. |

## Fases e etapas

- As **10 etapas de desenvolvimento** (incluindo documentação SDD) estão em [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md).
- O MVP cobre as etapas 1 a 10; blog e expansões ficam para depois.

## Fora do escopo do MVP

- Blog.
- Cache de CEP (por hora sem cache).
- Notificações in-app (apenas e-mail).
- Funcionalidades avançadas de moderação que não sejam essenciais para o fluxo de cadastro e busca inicial.
