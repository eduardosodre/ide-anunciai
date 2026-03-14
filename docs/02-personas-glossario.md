# Personas e glossário — ide-anunciai

## Modelo de conta (decisão)

- **Uma conta = um único tipo:** ou **Igreja** (com CNPJ da igreja) ou **Ministro/Profissional** (com CPF ou CNPJ de pregador).
- Quem quiser atuar como igreja e como ministro deve ter **duas contas** (dois e-mails).
- **Conta mínima:** nome, e-mail e senha, sem perfil de igreja ou ministro; serve para **enviar solicitações** de contato.

## Personas e papéis

| Persona / Tipo | Ação principal | Identificação | Campos MVP |
|----------------|----------------|---------------|------------|
| Ministro / Pregador / Profissional | Cadastro com CPF ou CNPJ de pregador; várias habilidades/dons; selo Verificado opcional; re-verificação em toda alteração. | CPF ou CNPJ (pregador) | nome, e-mail, telefone, cidade, habilidades/dons |
| Igreja | Cadastro com CNPJ da igreja; envia solicitações; aprovação administrativa. | CNPJ da igreja | nome da igreja, e-mail, telefone, CEP, cidade |
| Visitante (conta mínima) | Só quer enviar contato; cadastra nome, e-mail e senha. | — | nome, e-mail, senha |
| Administrador | Aprova/rejeita igrejas; gerencia selo Verificado; vê denúncias (usuários denunciados + descrição); pode inativar usuário. | — | — |

## Habilidades/dons do ministro (exemplos)

O ministro pode marcar **várias** habilidades, por exemplo: ensinar, dar aula, ministrar, orar, tocar instrumento. A lista é definida no sistema e pode ser ampliada depois.

## Glossário

| Termo | Definição |
|-------|-----------|
| Profissional / Ministro | Pessoa cadastrada com CPF ou CNPJ de pregador; pode ter várias habilidades/dons; aparece na busca (após aprovação se aplicável). |
| Igreja | Entidade cadastrada com CNPJ; passa por aprovação administrativa; pode buscar profissionais e enviar solicitações. |
| Selo Verificado | Indicação de que a identidade do profissional foi verificada. Quando verificado, **toda alteração** no perfil volta para re-verificação. |
| Re-verificação | Fluxo em que alterações feitas por usuário verificado são analisadas novamente antes de irem ao ar. |
| Denúncia | Registro em que um usuário acusa outro (ex.: falsa identidade). Admin vê lista de denunciados, descrição da denúncia e pode inativar. |
| Solicitação | Pedido de contato enviado a profissional ou igreja, com **texto livre** sobre o evento; destinatário recebe por e-mail. |
| Conta mínima | Cadastro com nome, e-mail e senha apenas para poder enviar solicitações (sem perfil de igreja ou ministro). |
| Raio de proximidade | Distância em **km** a partir de um CEP ou cidade, definida pelo usuário na busca (ex.: 10 km). |
