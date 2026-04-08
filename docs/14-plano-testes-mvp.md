# Plano de testes do MVP — ide-anunciai

Escopo de verificação antes de considerar o MVP entregue. Alinha-se a [09-criterios-aceite.md](09-criterios-aceite.md).

---

## Tipos de teste

| Tipo | Escopo MVP |
|------|------------|
| Manual exploratório | Fluxos críticos listados abaixo |
| Regressão rápida | Checklist após cada release candidate |
| Segurança básica | Itens na seção “Segurança” |
| Automatizado | Opcional; recomendado para regras de validação e busca |

---

## Fluxos críticos (obrigatórios)

1. **Cadastro → login → logout**
2. **Recuperação de senha** (token válido, expirado, reuso)
3. **Criar perfil ministro** com habilidades; **perfil público**
4. **Criar perfil igreja**; CEP preenchendo cidade; **perfil público**
5. **Solicitar verificação** (ministro e igreja); **admin aprova/rejeita**
6. **Editar perfil verificado** → pendência; **público mostra dados antigos**
7. **Denunciar** → **admin lista** → **inativar** → usuário não loga e some da busca
8. **Busca** CEP + raio + filtros; visitante vs logado (visibilidade)
9. **Início de conversa no chat** + recebimento de e-mail (ambiente de teste)
10. **Responsivo** home + busca + perfil (larguras mobile/desktop)

---

## Matriz RF → teste (amostra)

| RF | Caso de teste |
|----|-----------------|
| RF1.1.5 | Dois cadastros com mesmo e-mail |
| RF1.4.2 | Sessão após 8h (ou simulação reduzida em dev) |
| RF2.3.2 | Edição com pendente_revisao: dados públicos inalterados até aprovação |
| RF3.1.4 / .5 | Mesmo perfil como visitante (logout) e logado |
| RF3.2.3 | E-mail contém solicitante + primeira mensagem obrigatória e conversa é criada |

---

## Segurança (checklist mínimo)

- [ ] SQL injection: tentativas em campos de busca e login bloqueadas (prepared statements)
- [ ] XSS: strings com `<script>` em nome/mensagem não executam ao exibir
- [ ] CSRF: POST sem token rejeitado
- [ ] IDOR: trocar `id` na URL de edição de outro usuário → 403
- [ ] Upload: executável disfarçado de imagem rejeitado
- [ ] Senha: política aplicada; hash bcrypt verificável
- [ ] Documentos de verificação: URL direta sem sessão admin não baixa arquivo
- [ ] Telefone e e-mail não aparecem publicamente em perfis (visitante e logado)
- [ ] Endpoint `/api/privacy/export` retorna JSON apenas para o usuário autenticado

---

## Dados de teste

- Usuários: normal, admin, inativo.
- Perfis: só ministro, só igreja, ambos, nenhum.
- Estados: verificado, pendente verificação, re-verificação, rejeitado.

---

## Critério de saída

- Todos os fluxos críticos passam.
- Checklist de segurança sem itens falhando bloqueantes.
- Critérios bloqueantes de [09](09-criterios-aceite.md) satisfeitos.
