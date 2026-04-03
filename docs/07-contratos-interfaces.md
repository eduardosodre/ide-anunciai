# Contratos e interfaces — ide-anunciai

Definição das telas, formulários e parâmetros de busca para implementação guiada por spec. Alinhado às decisões em [00-perguntas-descoberta.md](00-perguntas-descoberta.md).

---

## Telas e rotas (MVP)

| Rota / Tela | Acesso | Descrição |
|-------------|--------|-----------|
| / | Público | Home: proposta do site + links Buscar, Cadastrar, Login |
| /busca | Público | Formulário CEP/cidade + raio (km); listagem de resultados |
| /perfil/profissional/{uuid} | Público | Perfil público do ministro (UUID); ID numérico redireciona 301 |
| /perfil/igreja/{uuid} | Público | Perfil público da igreja (UUID); ID numérico redireciona 301 |
| /cadastro | Público | Cadastro base (nome completo, e-mail, senha) |
| /login | Público | E-mail e senha; link “Esqueci minha senha” |
| /recuperar-senha | Público | Solicitação de recuperação (e-mail) |
| /redefinir-senha?token=... | Público | Nova senha (token válido) |
| /sair | Logado | Logout |
| /conta | Logado | Edição da conta base (nome completo, e-mail, foto) |
| /meu-perfil/ministro | Logado | Criação/edição do perfil ministro |
| /meu-perfil/igreja | Logado | Criação/edição do perfil igreja |
| /verificacao | Logado | Solicitar selo verificado e enviar documentos |
| /chat | Logado | Listagem de conversas iniciadas/recebidas |
| /chat/{conversa_id} | Logado | Tela de conversa e envio de mensagens |
| /admin/verificacoes | Admin | Fila de verificações (ministro/igreja) para aprovar/rejeitar |
| /admin/revisao | Admin | Fila de alterações de perfis verificados para re-verificar |
| /admin/denuncias | Admin | Lista de denunciados, contagens e detalhes |
| /privacidade | Público | Política de Privacidade |
| /termos | Público (opcional) | Termos de Uso |

---

## Formulários — Campos e validações

### Cadastro — Conta base

- nome_completo (obrigatório)
- email (obrigatório, único)
- senha (obrigatório, política segura)
- confirmação de senha
- checkbox Política de Privacidade (obrigatório)
- Botão: Cadastrar

### Conta (/conta)

- nome_completo (obrigatório)
- email (obrigatório, único)
- foto_perfil (opcional)
- Botão: Salvar

### Perfil Ministro

- nome_publico (obrigatório)
- telefone (opcional)
- cidade (obrigatório)
- habilidades/dons (múltipla escolha; pelo menos uma)
- Botão: Salvar perfil ministro

### Perfil Igreja

- nome_igreja (obrigatório)
- email_contato (obrigatório)
- telefone (opcional)
- cep (obrigatório)
- cidade (obrigatório; pode preencher via API a partir do CEP)
- Botão: Salvar perfil igreja

### Verificação de Ministro

- rg (obrigatório para solicitar)
- cpf (obrigatório para solicitar)
- documentos (obrigatório; um ou mais anexos)
- Botão: Solicitar verificação

### Verificação de Igreja

- cnpj (obrigatório para solicitar)
- documentos (obrigatório; um ou mais anexos)
- Botão: Solicitar verificação

### Login

- email (obrigatório)
- senha (obrigatório)
- Link: Esqueci minha senha
- Botão: Entrar

### Recuperar senha

- email (obrigatório)
- Botão: Enviar link

### Redefinir senha (com token)

- nova_senha (obrigatório, política segura)
- confirmação de senha
- Botão: Redefinir

### Busca

- cep OU cidade (pelo menos um)
- raio_km (obrigatório ou padrão; ex.: 5, 10, 25, 50, 100)
- tipo: profissionais | igrejas | ambos
- habilidade (opcional; filtro para profissionais)
- Botão: Buscar

### Primeiro contato (mensagem/chat)

- mensagem (obrigatório — texto livre explicando o evento/motivo do contato)
- Botão: Iniciar conversa

### Denúncia (link/botão no perfil ou em “reportar”)

- descricao (obrigatório — texto livre)
- Botão: Enviar denúncia

---

## Contratos de exibição (perfil público)

### Profissional (ministro)

- nome_publico
- habilidades/dons (lista)
- cidade
- telefone (não exibido publicamente)
- selo “Verificado” (se verificado)
- Botão: Iniciar conversa

### Igreja

- nome da igreja
- cidade
- telefone (não exibido publicamente)
- Contato via chat (e-mail não exposto)
- selo “Verificado” (se verificado)
- Botão: Iniciar conversa

---

## Parâmetros de busca (backend)

- **cep:** string (Brasil; API para coordenadas/cidade; sem cache por hora)
- **cidade:** string (alternativa ao CEP)
- **raio_km:** inteiro (obrigatório ou padrão)
- **tipo:** profissional | igreja | ambos
- **habilidade_id** ou **habilidade:** filtro para profissionais
- **pagina**, **limite:** paginação

Resultado: apenas perfis ativos; perfis verificados com pendente_revisao exibem dados antigos até re-verificação. Visitante vê dados mínimos; logado vê dados adicionais permitidos.

---

## E-mail de notificação (início de conversa)

- **Para:** e-mail do destinatário (profissional ou igreja).
- **Assunto:** ex.: “Nova mensagem no ide-anunciai”
- **Corpo:** nome do solicitante, e-mail, **texto da mensagem** (obrigatório). Link para o site (e para `/chat`).

---

## Endpoints API (MVP)

Além das rotas HTML, o MVP expõe endpoints equivalentes em `/api` para integrações frontend.

- `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`
- `POST /api/auth/password/forgot`, `POST /api/auth/password/reset`
- `GET /api/search`
- `POST /api/chat/conversations`, `GET /api/chat/conversations`, `GET /api/chat/conversations/{id}`, `POST /api/chat/conversations/{id}/messages`
- `POST /api/reports`

## E-mail de recuperação de senha

- **Para:** e-mail do usuário.
- **Assunto:** ex.: “Redefinir sua senha — ide-anunciai”
- **Corpo:** link com token de uso único e validade limitada (ex.: 1 hora) para a rota /redefinir-senha.
