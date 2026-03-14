# UX e navegação — ide-anunciai

Requisitos de layout, usabilidade e navegação para um site moderno e de boa usabilidade. Decisões: apenas português (BR); layout responsivo desde o MVP; home com proposta do site e links para Buscar e Cadastrar.

---

## Princípios

- **Layout bonito e interessante** para boa primeira impressão e confiança.
- **Layout super moderno** (tipografia, espaçamento, cores atuais).
- **Boa usabilidade:** ações claras, feedback imediato, poucos cliques para tarefas principais.
- **Boa navegabilidade:** menu e estrutura consistentes; usuário sabe onde está e como voltar.
- **Idioma:** apenas **português (BR)** no MVP.
- **Responsivo:** layout **responsivo desde o MVP** (desktop e mobile).

---

## Público-alvo

- Igrejas e ministros do meio religioso; visitantes que buscam contato ou querem apenas enviar solicitações (conta mínima).

---

## Tela inicial (Home)

- **Proposta do site:** texto claro explicando o objetivo da plataforma (conectar igrejas e ministros por localização, solicitações de contato com texto sobre o evento).
- **Links principais:** Buscar, Cadastrar, Login (e “Esqueci minha senha” na tela de login).
- Pode incluir vitrine ou destaque para busca (CEP + raio) e chamada para cadastro.
- **Sem blog** no MVP.

---

## Estrutura de navegação (MVP)

- **Home:** proposta + links (Buscar, Cadastrar, Login).
- **Busca:** formulário CEP/cidade + raio (km); resultados de profissionais e igrejas; filtros (tipo, habilidade).
- **Perfil público:** profissional ou igreja; botão “Solicitar contato” (exige login).
- **Área logada:**
  - Meu perfil (ministro ou igreja) — edição.
  - Minhas solicitações (se implementado no MVP).
  - Sair.
- **Administração (admin):** aprovação de igrejas; re-verificação de alterações de verificados; selo Verificado; **denúncias** (listar denunciados, ver descrição, inativar); recuperação de senha não gerida aqui (é fluxo do usuário).
- **Rodapé:** Política de Privacidade, contato; recuperação de senha via link “Esqueci minha senha” no login.

---

## Usabilidade

- Formulários com **labels** e mensagens de erro **inline** ou próximas ao campo.
- **Feedback:** confirmação após cadastro, envio de solicitação, recuperação de senha e ações de admin.
- **Estados de carregamento:** indicador ao submeter formulários ou ao buscar resultados.
- **Acessibilidade:** contraste adequado, navegação por teclado.

---

## Identidade visual (orientação)

- Paleta de cores consistente; tipografia legível; hierarquia clara (títulos, subtítulos, corpo).
- Espaçamento generoso; botões primários e secundários distintos; ícones quando ajudarem (busca, perfil, sair).

---

## Responsividade

- Menu adaptado para mobile (hambúrguer ou colapsável).
- Formulários e resultados de busca utilizáveis em tela pequena.
- Áreas clicáveis adequadas para toque (mínimo ~44px).
