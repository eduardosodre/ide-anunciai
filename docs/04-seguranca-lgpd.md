# Segurança e LGPD — ide-anunciai

## Segurança técnica

### Credenciais e configuração

- Arquivo **database.php** (credenciais) **nunca** é versionado.
- **database.php.example** deve ser **versionado no Git**, com variáveis sem valores reais (ou uso de ambiente); o desenvolvedor copia para `database.php` e preenche localmente.
- Uso de variáveis de ambiente ou arquivo de config fora do repositório para: host do banco, usuário, senha, e-mail (se houver).

### Autenticação e sessão

- Senhas armazenadas com **password_hash** (bcrypt); nunca em texto plano.
- **Política de senha segura:** mínimo 8 caracteres; pelo menos uma letra maiúscula, uma minúscula e um número (ou critério equivalente forte). Detalhes fixados na implementação.
- **Sessão:** duração de **8 horas**; **regenerar ID** após login; logout invalida sessão.
- **HTTPS** em produção (obrigatório para login e dados sensíveis).

### Recuperação de senha

- Fluxo de **“Esqueci minha senha”**: usuário informa e-mail; sistema envia link (ou token com validade curta) por e-mail para redefinir a senha.
- Token de recuperação deve expirar (ex.: 1 hora) e ser de uso único.
- Nova senha deve respeitar a mesma política de senha segura.

### Proteção de rotas e dados

- Rotas que alteram dados ou exibem dados restritos verificam **autenticação** e **tipo de conta** (igreja, ministro, admin).
- Ações de administrador restritas a usuários com papel **admin**.
- Edição de perfil/igreja apenas pelo **próprio** usuário (ou admin).
- **CSRF:** token em formulários de alteração.
- **Prepared statements** (PDO com bind) em todas as consultas SQL.
- Sanitização de entrada e escape de saída (HTML) para evitar XSS.

### E-mail e notificações

- Não expor e-mails em massa; notificações enviadas pelo sistema.
- Configuração de envio de e-mail fora do repositório quando possível.

---

## LGPD — Conformidade

### Base legal e consentimento

- Tratamento com **consentimento** explícito (cadastro, busca, solicitações).
- Checkbox obrigatório de aceite da Política de Privacidade; **registro de data/hora e versão** da política aceita.

### Direitos do titular

- **Acesso:** usuário pode ver seus dados em “Meus dados” ou no perfil.
- **Correção:** edição do perfil já prevista.
- **Exclusão:** possibilidade de **excluir conta** (“direito ao esquecimento”); dados removidos ou anonimizados conforme política.
- **Exportação:** desejável no MVP; a confirmar na validação.

### Política de privacidade

- Página pública com: dados coletados, finalidade, base legal, retenção, direitos do titular, contato do encarregado.
- Versão e data documentadas; alterações de finalidade podem exigir novo consentimento.

### Retenção e minimização

- Guardar apenas o necessário. Retenção após exclusão (ex.: 30 dias em log) definida na política.
- Logs com finalidade e prazo; acesso restrito.
