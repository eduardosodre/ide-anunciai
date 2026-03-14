# Perguntas de descoberta — ide-anunciai

Este documento registra as **decisões tomadas** com base nas respostas do usuário. Usado como referência para os demais specs.

---

## 1. Localização e raio de busca — DECIDIDO

- **CEP:** apenas Brasil; uso de API (ex.: ViaCEP) para CEP → cidade/coordenadas; **por hora não usar cache**.
- **Raio:** o próprio usuário define o raio na pesquisa (ex.: “encontrar alguém que sabe ensinar no CEP X com raio de 10 km”). Unidade: km; valor livre ou opções (ex.: 5, 10, 25, 50, 100 km) a definir na implementação.

---

## 2. Papéis e tipos de perfil — DECIDIDO

- **Uma conta = um único tipo:** usuário é **ou** Igreja **ou** Ministro/Profissional. Não acumula os dois na mesma conta.
- **Igreja:** cadastro com **CNPJ da igreja** (obrigatório).
- **Ministro/Profissional:** cadastro com **CPF** ou **CNPJ de pregador**.
- **Habilidades/dons do ministro:** múltiplas por perfil. Exemplos: ensinar, dar aula, ministrar, orar, tocar instrumento, etc. Lista definida no sistema; usuário escolhe várias.

---

## 3. Aprovação, verificação e denúncias — DECIDIDO

- **Usuário verificado:** toda alteração no perfil volta para **re-verificação** antes de ser publicada.
- **Denúncias:** qualquer usuário pode **denunciar** outro. O sistema deve:
  - Exibir em algum ponto (área admin) **quais usuários foram denunciados**.
  - Permitir ver a **descrição da denúncia** (ex.: pessoa se passando por outra).
  - Permitir **inativar** o usuário denunciado se necessário (ação administrativa).

---

## 4. Solicitações de contato / agendamento — DECIDIDO

- **Texto livre obrigatório:** o solicitante informa um **texto livre** explicando o evento/motivo do contato. O ministro, ao receber, entende o contexto e pode validar se vai atender antes de responder.
- Notificação por e-mail ao destinatário com dados do solicitante e o texto da mensagem.

---

## 5. Autenticação e conta mínima — DECIDIDO

- **Conta mínima** para fazer contato: **Nome**, **e-mail** e **senha** (sem papel de igreja ou ministro; só para enviar solicitações).
- Login: e-mail + senha.

---

## 6. Blog — DECIDIDO

- **Não ter blog** no primeiro momento (fora do MVP).

---

## 7. LGPD e privacidade

- Manter conforme 04-seguranca-lgpd.md (consentimento, política, direitos do titular). Detalhes de retenção e exportação a fixar na validação final se necessário.

---

## 8. Segurança técnica — DECIDIDO

- **database.php.example** pode (e deve) ser **versionado no Git**; apenas `database.php` com credenciais fica fora.
- **Sessão:** duração de **8 horas**.
- **Política de senha segura:** obrigatória (detalhes em 04-seguranca-lgpd.md).
- **Recuperação de senha** (“lembrete de senha”): implementar no MVP.

---

## 9. UX e navegação — DECIDIDO

- **Idioma:** apenas português (BR).
- **Layout responsivo** desde o MVP.
- **Tela inicial (home):** mostrar a **proposta do site** e ter **links** para Buscar, Cadastrar, etc.

---

## 10. Escopo e etapas — DECIDIDO

- Todo o desenvolvimento está dividido em **10 etapas** (detalhes em [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md)).
- **Agora:** trabalhar apenas com a **documentação SDD**; o código será implementado após validação desta documentação.
