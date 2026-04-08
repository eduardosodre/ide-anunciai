# Perguntas de descoberta — ide-anunciai

Este documento registra as **decisões tomadas** com base nas respostas do usuário. Usado como referência para os demais specs.

---

## 1. Localização e raio de busca — DECIDIDO

- **CEP:** apenas Brasil; uso de API (ex.: ViaCEP) para CEP → cidade/coordenadas; **por hora não usar cache**.
- **Raio:** o próprio usuário define o raio na pesquisa (ex.: “encontrar alguém que sabe ensinar no CEP X com raio de 10 km”). Unidade: km; valor livre ou opções (ex.: 5, 10, 25, 50, 100 km) a definir na implementação.

---

## 2. Papéis e tipos de perfil — DECIDIDO

- **Conta única de usuário (base):** cadastro inicial sempre com **nome completo**, **e-mail** e **senha**.
- Após login, o usuário pode criar até dois perfis vinculados à mesma conta:
  - **Perfil Ministro/Profissional** (persona para ser encontrado).
  - **Perfil Igreja**.
- Os dois perfis podem coexistir na mesma conta (ex.: usuário com perfil de ministro e igreja).
- **Habilidades/dons** ficam no perfil de ministro e aceitam múltipla seleção.

---

## 3. Verificação por camadas — DECIDIDO

- **Conta base:** não exige documentação; usuário já entra na plataforma após cadastro e login.
- **Selo verificado do ministro/profissional:** exige completar dados de identificação (**RG**, **CPF**) e envio de documentos comprobatórios.
- **Selo verificado da igreja:** exige dados de **CNPJ** e envio de documentos comprobatórios.
- Cada perfil (ministro e igreja) possui seu próprio status de verificação.

---

## 4. Solicitações de contato / agendamento — DECIDIDO

- **Texto livre obrigatório:** o solicitante informa um **texto livre** explicando o evento/motivo do contato. O ministro, ao receber, entende o contexto e pode validar se vai atender antes de responder.
- Notificação por e-mail ao destinatário com dados do solicitante e o texto da mensagem.

---

## 5. Autenticação e conta base — DECIDIDO

- Cadastro inicial obrigatório com **Nome completo**, **e-mail** e **senha**.
- Login: e-mail + senha.
- No perfil do usuário, deve ser possível definir **foto de perfil**.

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
- **Visibilidade de dados:** visitante vê dados mínimos em resultados/perfis; usuário logado vê mais informações conforme política da plataforma.

---

## 10. Escopo e etapas — DECIDIDO

- Todo o desenvolvimento está dividido em **10 etapas** (detalhes em [08-etapas-desenvolvimento.md](08-etapas-desenvolvimento.md)).
- **Agora:** trabalhar apenas com a **documentação SDD**; o código será implementado após validação desta documentação.
