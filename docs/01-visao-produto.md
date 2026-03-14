# Visão do produto — ide-anunciai

## Objetivo

Plataforma que conecta **igrejas** e **ministros/profissionais** do meio religioso em um ambiente confiável e acessível, com busca por localização (CEP + raio em km) e solicitações de contato com texto livre sobre o evento.

## Escopo do MVP

- Busca de profissionais e igrejas por **CEP ou cidade** e **raio em km** (definido pelo usuário na pesquisa). Apenas Brasil; API de CEP sem cache no primeiro momento.
- Cadastro **único por conta:** usuário é **ou** Igreja (com CNPJ) **ou** Ministro/Profissional (com CPF ou CNPJ de pregador). Ministro pode ter **várias habilidades/dons** (ensinar, ministrar, orar, tocar instrumento, etc.).
- **Conta mínima** (nome, e-mail, senha) para quem só quer enviar solicitações.
- Solicitação de contato com **texto livre** explicando o evento; notificação por e-mail ao destinatário.
- **Re-verificação:** quando um usuário verificado altera o perfil, a alteração passa por nova verificação.
- **Denúncias:** usuários podem denunciar outros; admin vê denúncias e descrição, pode inativar.
- **Recuperação de senha** e sessão de 8 horas.
- **Fora do MVP:** blog, cache de CEP, notificações in-app.

## Princípios

| Princípio | Descrição |
|-----------|-----------|
| Simplicidade | Cadastro claro: uma conta, um tipo (igreja ou ministro). Conta mínima para só enviar contato. |
| Confiabilidade | Aprovação de igrejas; selo Verificado para profissionais; re-verificação em alterações; denúncias e moderação. |
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
- Múltiplos papéis na mesma conta (igreja + ministro); cada tipo exige conta própria.
