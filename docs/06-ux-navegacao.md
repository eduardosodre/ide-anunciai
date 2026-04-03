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

---

## Responsividade

- Menu adaptado para mobile (hambúrguer ou colapsável).
- Formulários e resultados de busca utilizáveis em tela pequena.
- Áreas clicáveis adequadas para toque (mínimo ~44px).
