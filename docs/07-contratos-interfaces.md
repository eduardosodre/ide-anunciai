# Contratos e interfaces — ide-anunciai

Definição das telas, formulários e parâmetros de busca para implementação guiada por spec. Alinhado às decisões em [00-perguntas-descoberta.md](00-perguntas-descoberta.md).

---

## Telas e rotas (MVP)

| Rota / Tela | Acesso | Descrição |
|-------------|--------|-----------|
| / | Público | Home: proposta do site + links Buscar, Cadastrar, Login |
| /busca | Público | Formulário CEP/cidade + raio (km); listagem de resultados |
| /perfil/profissional/{id} | Público | Perfil público do ministro; botão Solicitar contato |
| /perfil/igreja/{id} | Público | Perfil público da igreja; botão Solicitar contato |
| /cadastro | Público | Escolha de tipo (conta mínima / igreja / ministro); formulário conforme tipo |
| /login | Público | E-mail e senha; link “Esqueci minha senha” |
| /recuperar-senha | Público | Solicitação de recuperação (e-mail) |
| /redefinir-senha?token=... | Público | Nova senha (token válido) |
| /sair | Logado | Logout |
| /meu-perfil | Logado | Edição do próprio perfil (ministro ou igreja) |
| /minhas-solicitacoes | Logado (opcional) | Listagem de solicitações enviadas/recebidas |
| /admin/aprovacoes | Admin | Fila de igrejas para aprovar/rejeitar |
| /admin/revisao | Admin | Fila de alterações de verificados para re-verificar |
| /admin/verificado | Admin | Atribuir/remover selo Verificado em profissionais |
| /admin/denuncias | Admin | Listar usuários denunciados; ver descrição das denúncias; inativar usuário |
| /privacidade | Público | Política de Privacidade |
| /termos | Público (opcional) | Termos de Uso |

---

## Formulários — Campos e validações

### Cadastro — Escolha de tipo

- Opções: **Conta mínima** (só quero enviar contatos) | **Igreja** | **Ministro**.
- Próximo passo: formulário específico do tipo.

### Cadastro — Conta mínima

- nome (obrigatório)
- email (obrigatório, único)
- senha (obrigatório, política segura)
- confirmação de senha
- checkbox Política de Privacidade (obrigatório)
- Botão: Cadastrar

### Cadastro — Ministro

- nome (obrigatório)
- email (obrigatório, único)
- senha (obrigatório, política segura)
- confirmação de senha
- cpf_ou_cnpj (obrigatório; CPF ou CNPJ de pregador)
- telefone (opcional)
- cidade (obrigatório)
- habilidades/dons (múltipla escolha; pelo menos uma)
- checkbox Política de Privacidade (obrigatório)
- Botão: Cadastrar

### Cadastro — Igreja

- nome_igreja (obrigatório)
- cnpj (obrigatório; CNPJ da igreja)
- email_contato (obrigatório)
- telefone (opcional)
- cep (obrigatório)
- cidade (obrigatório; pode preencher via API a partir do CEP)
- senha (obrigatório, política segura)
- confirmação de senha
- checkbox Política de Privacidade (obrigatório)
- Botão: Cadastrar

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

### Solicitação de contato

- mensagem (obrigatório — texto livre explicando o evento/motivo do contato)
- Botão: Enviar solicitação

### Denúncia (link/botão no perfil ou em “reportar”)

- descricao (obrigatório — texto livre)
- Botão: Enviar denúncia

---

## Contratos de exibição (perfil público)

### Profissional (ministro)

- nome
- habilidades/dons (lista)
- cidade
- telefone (se permitido)
- selo “Verificado” (se verificado)
- Botão: Solicitar contato

### Igreja

- nome da igreja
- cidade
- telefone (se permitido)
- Contato via solicitação (e-mail não exposto em lista)
- Botão: Solicitar contato

---

## Parâmetros de busca (backend)

- **cep:** string (Brasil; API para coordenadas/cidade; sem cache por hora)
- **cidade:** string (alternativa ao CEP)
- **raio_km:** inteiro (obrigatório ou padrão)
- **tipo:** profissional | igreja | ambos
- **habilidade_id** ou **habilidade:** filtro para profissionais
- **pagina**, **limite:** paginação

Resultado: apenas aprovados e ativos; profissionais com pendente_revisao podem exibir dados antigos até re-verificação.

---

## E-mail de notificação (solicitação)

- **Para:** e-mail do destinatário (profissional ou igreja).
- **Assunto:** ex.: “Nova solicitação de contato no ide-anunciai”
- **Corpo:** nome do solicitante, e-mail, telefone (se houver), **texto da mensagem** (obrigatório). Link para o site (e para “Minhas solicitações” se existir).

## E-mail de recuperação de senha

- **Para:** e-mail do usuário.
- **Assunto:** ex.: “Redefinir sua senha — ide-anunciai”
- **Corpo:** link com token de uso único e validade limitada (ex.: 1 hora) para a rota /redefinir-senha.
