# Personas e glossário — ide-anunciai

## Modelo de conta (decisão)

- **Conta base única:** todo usuário entra com **nome completo**, **e-mail** e **senha**.
- Após login, pode completar:
  - **Perfil Ministro/Profissional** (para divulgação e busca).
  - **Perfil Igreja**.
- Os dois perfis podem existir na mesma conta.
- O usuário pode definir **foto de perfil** na área logada.

## Personas e papéis

| Persona / Tipo | Ação principal | Identificação | Campos MVP |
|----------------|----------------|---------------|------------|
| Usuário base | Acessa a plataforma com cadastro simples; pode navegar e evoluir perfis. | e-mail único | nome completo, e-mail, senha, foto (opcional no perfil) |
| Ministro / Profissional | Cria persona para ser encontrado; define habilidades/dons; pode solicitar selo verificado. | RG + CPF para verificação | nome público, telefone, cidade, habilidades/dons |
| Igreja | Cria cadastro de igreja vinculado ao usuário; pode solicitar selo verificado. | CNPJ para verificação | nome da igreja, e-mail de contato, telefone, CEP, cidade |
| Administrador | Aprova/rejeita igrejas; gerencia selo Verificado; vê denúncias (usuários denunciados + descrição); pode inativar usuário. | — | — |

## Habilidades/dons do ministro (exemplos)

O ministro pode marcar **várias** habilidades, por exemplo: ensinar, dar aula, ministrar, orar, tocar instrumento. A lista é definida no sistema e pode ser ampliada depois.

## Glossário

| Termo | Definição |
|-------|-----------|
| Conta base | Cadastro inicial com nome completo, e-mail e senha; acesso imediato à plataforma. |
| Profissional / Ministro | Perfil vinculado ao usuário para divulgação, com habilidades/dons e possibilidade de selo verificado. |
| Igreja | Perfil de igreja vinculado ao usuário, com CNPJ e possibilidade de selo verificado. |
| Selo Verificado | Indicação de identidade validada documentalmente para um perfil específico (ministro ou igreja). |
| Verificação documental | Processo de envio e análise de documentos para liberar o selo verificado. |
| Denúncia | Registro em que um usuário acusa outro (ex.: falsa identidade). Admin vê lista de denunciados, descrição da denúncia e pode inativar. |
| Solicitação | Pedido de contato enviado a profissional ou igreja, com **texto livre** sobre o evento; destinatário recebe por e-mail. |
| Foto de perfil | Imagem opcional que o usuário pode definir após login no próprio perfil. |
| Raio de proximidade | Distância em **km** a partir de um CEP ou cidade, definida pelo usuário na busca (ex.: 10 km). |
