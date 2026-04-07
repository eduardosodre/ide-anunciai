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

- Igrejas e ministros do meio religioso; visitantes e usuários com conta base que buscam contato e divulgação.

---

## Tela inicial (Home)

- **Proposta do site:** texto claro explicando o objetivo da plataforma (conectar igrejas e ministros por localização, solicitações de contato com texto sobre o evento).
- **Links principais:** Buscar, Cadastrar, Login (e “Esqueci minha senha” na tela de login).
- Pode incluir vitrine ou destaque para busca (CEP + raio) e chamada para cadastro.
- **Sem blog** no MVP.
- Mostrar que visitantes veem dados resumidos e usuários logados veem informações adicionais.
- **Perfis públicos (ministro e igreja):** visitante vê nome, local, habilidades (ministro), selo verificado e texto de que telefone/e-mail não são públicos; **usuário logado** vê um bloco **Contato** com telefone e e-mail cadastrados (e CEP no perfil de igreja), com links `tel:` / `mailto:`, além dos botões de conversa e denúncia. A API pública `GET /api/profissionais/{uuid}` e `GET /api/igrejas/{uuid}` **não** inclui esses campos — apenas o HTML autenticado por sessão.

---

## Manutenção centralizada (nome e menus)

- **Configuração única:** `src/Config/Site.php` define o **nome do site** (`Site::NAME`), **itens do menu** (visitante, usuário logado, admin) e **links do rodapé** + texto do rodapé (`Site::FOOTER_TAGLINE`). Alterar ali reflete no cabeçalho e no `<title>` (via `Html::layout()`).
- **Páginas com assets extra:** `Html::layout($title, $body, $csrf, $extraHead, $extraFooter)` — usado em **Minha conta** para CSS/JS do recorte de foto (Cropper.js).

## Estrutura de navegação (MVP)

- **Home:** proposta + links (Buscar, Cadastrar, Login).
- **Busca:** formulário CEP/cidade + raio (km); resultados de profissionais e igrejas; filtros (tipo, habilidade).
- **Perfil público:** profissional ou igreja; botão “Iniciar conversa” (exige login).
- **Área logada:**
  - Minha conta (nome, e-mail, foto com **pré-visualização e recorte** antes do envio; ver `public/js/avatar-crop.js`).
  - Meu perfil ministro (opcional) — criação/edição.
  - Meu perfil igreja (opcional) — criação/edição.
  - Verificação (status e envio de documentos para selo) — página em cartões, textos explicativos, campos com *hints* e tabela de solicitações estilizada (`/verificacao`).
  - Chat (lista de conversas e detalhe).
  - Sair.
- **Administração (admin):** fila de verificações (ministro e igreja), aprovação/rejeição, re-verificação de alterações; recuperação de senha não gerida aqui (é fluxo do usuário).
- **Rodapé:** Política de Privacidade, contato; recuperação de senha via link “Esqueci minha senha” no login.

---

## Usabilidade

- **Login, cadastro, recuperar e redefinir senha:** envio via `public/js/app.js` (API JSON). A caixa de mensagem global de cada tela (`#form-login-global`, `#form-register-global`, etc.) fica **como primeiro filho dentro do `<form>`**, para o script localizar com `form.querySelector` e exibir erros de credenciais ou de rede. Campos usam `.field-error` com `data-error-for` alinhado ao `name` do input. O spinner do botão fica oculto até o envio (atributo `hidden` + CSS que não sobrescreve `[hidden]`).
- Formulários com **labels** e mensagens de erro **inline** ou próximas ao campo.
- **Feedback:** confirmação após cadastro, início de conversa, recuperação de senha e ações de admin.
- **Estados de carregamento:** indicador ao submeter formulários ou ao buscar resultados.
- **Acessibilidade:** contraste adequado, navegação por teclado.
- Diferenciar visualmente selo “Verificado” e status “Pendente de verificação”.

---

## Identidade visual (orientação)

- Paleta de cores consistente; tipografia legível; hierarquia clara (títulos, subtítulos, corpo).
- Espaçamento generoso; botões primários e secundários distintos; ícones quando ajudarem (busca, perfil, sair).
- Base visual centralizada em `public/css/stitch-theme.css`, inspirada nos templates da pasta `stitch/` (home, busca e perfil): paleta teal/gold, tipografia Manrope + Inter, cards sem divisórias agressivas e header com efeito de vidro.
- O `Html::layout()` continua como shell único (header/footer), carregando o tema global para todas as páginas HTML.
- Adoção incremental dos templates da pasta `stitch/`: primeiro ciclo aplicado em **Início**, **Busca** e **Perfis públicos** (ministro/igreja), preservando rotas, contrato API e regras de sessão já existentes.
- Segundo ciclo aplicado em **Login**, **Cadastro**, **Recuperar senha**, **Redefinir senha** e **Minha conta**, com cabeçalhos editoriais e shell visual unificado, sem alterar validações/client API e regras de autenticação já implementadas.
- Terceiro ciclo aplicado em **Meu perfil ministro**, **Meu perfil igreja**, **Verificação (usuário/admin)**, **Denúncia (usuário/admin)** e **Chat HTML**, com padronização visual de cabeçalhos, cards de ação e listas administrativas/conversas.
- Ajuste de aderência ao Stitch: a **Home** e a **Busca** passaram de tema superficial para estrutura visual mais próxima dos templates (hero em duas colunas, cards de destaque e grid de blocos), mantendo as mesmas rotas e comportamento funcional.

---

## Responsividade

- Menu adaptado para mobile (hambúrguer ou colapsável).
- Formulários e resultados de busca utilizáveis em tela pequena.
- Áreas clicáveis adequadas para toque (mínimo ~44px).
