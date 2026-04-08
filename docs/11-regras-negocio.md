# Regras de negócio — ide-anunciai

Consolida decisões e regras que atravessam RF, modelo e UX. Objetivo: evitar ambiguidade na implementação.

---

## 1. Conta base e perfis

- Uma **conta** (`usuario`) pode ter até **um** perfil `profissional` e **um** perfil `igreja` (relação 1:1 com usuário cada — ver [05-modelo-dados.md](05-modelo-dados.md)).
- **Criação:** perfis são opcionais; usuário pode navegar logado só com conta base.
- **Nome público (ministro):** pode diferir do nome da conta; exibição pública usa nome público do profissional.
- **Foto:** armazenada na conta ou no perfil conforme implementação; UX em [06-ux-navegacao.md](06-ux-navegacao.md) — priorizar um único lugar de verdade (recomendação: campo em `usuario` para foto global).

---

## 2. Visibilidade: visitante vs logado

Regra geral: **visitante** vê o mínimo para decidir contato; **logado** vê campos adicionais conforme tabela.

| Dado | Visitante | Usuário logado |
|------|-------------|----------------|
| Nome público (ministro) / nome igreja | Sim | Sim |
| Cidade | Sim | Sim |
| Habilidades (ministro) | Sim | Sim |
| Selo verificado | Sim (indicador) | Sim |
| Telefone | Não exibido | Não exibido |
| E-mail contato (igreja) | Não exibido | Não exibido |
| Documentos / RG / CPF / CNPJ | Nunca | Nunca (exceto próprio na área privada) |

---

## 3. Verificação e re-verificação

- **Selos** são independentes (ministro verificado ≠ igreja verificada).
- **Solicitação:** só após preencher campos obrigatórios (RG/CPF ou CNPJ) e anexos.
- **Rejeição:** admin pode informar motivo; usuário vê status na área `/verificacao` sem expor documentos a terceiros.
- **Re-verificação (RF2.3):** ao editar perfil já verificado, `pendente_revisao = 1`; **exibição pública** permanece com última versão aprovada até nova aprovação.
- **Campos sensíveis:** qualquer alteração em `nome_publico`, `cidade`, `rg`, `cpf`, `nome_igreja`, `cnpj`, `cep` e documentos de verificação dispara pendência de re-verificação.

---

## 4. Igreja: aprovação cadastral

- Alterações de igreja só exigem aprovação quando o perfil de igreja estiver **verificado**.
- Igreja não verificada pode editar seus dados sem fila administrativa.

---

## 5. Denúncias

- Quem pode denunciar: qualquer **usuário logado**.
- Alvo: outro **usuário** (não “perfil” isoladamente) — implementação: `usuario_alvo_id` referencia `usuario.id`.
- **Múltiplas denúncias:** acumulam; admin vê histórico/contagem.
- **Inativação:** `usuario.ativo = 0`; sessões invalidadas no próximo request; perfis públicos somem da busca.
- **Anti-abuso (MVP):** limite de 3 denúncias por dia por par (denunciante, alvo) e 10 denúncias/dia por denunciante.

---

## 6. Primeiro contato por mensagem/chat

- Remetente deve estar logado; pode não ter perfil ministro/igreja.
- **Não permitir** contato com destino sendo o próprio usuário (mesmo `usuario_id` dono do profissional/igreja).
- Primeiro contato sempre por mensagem/chat; telefone e e-mail não são públicos.
- E-mail ao destinatário usa sempre `usuario.email` do dono do perfil (ministro ou igreja).

---

## 7. Busca e raio

- Apenas **Brasil**; CEP/cidade resolvido via API externa sem cache no MVP.
- Perfis sem latitude/longitude válidos não entram no resultado por distância até preenchimento correto.
- Geocodificação é exigida no salvamento do perfil; se falhar, o sistema informa erro e não publica o perfil na busca por raio.

---

## 8. Administrador

- Papéis: flag `usuario.admin`; primeiro admin criado por script/seed documentado na Etapa 2.
- Admin não “assume” identidade de usuário no MVP.

---

## 9. Complemento ao modelo de dados

- Entidade **denúncia** está detalhada em [05-modelo-dados.md](05-modelo-dados.md) e segue:

| Campo | Notas |
|-------|--------|
| id | PK |
| denunciante_id | FK usuario |
| denunciado_id | FK usuario |
| descricao | TEXT |
| criado_em | DATETIME |

- Índice: `(denunciado_id)`, `(denunciante_id, denunciado_id, criado_em)` para moderação e anti-abuso.
