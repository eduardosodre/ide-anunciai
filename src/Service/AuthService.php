<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AppConfig;
use App\Repository\UserRepository;
use App\Security\PasswordValidator;
use PDOException;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordValidator $passwords,
        private readonly Mailer $mailer,
        private readonly AppConfig $config
    ) {
    }

    public function register(string $nome, string $email, string $senha, string $senhaConfirmacao, bool $consentimento): array
    {
        $errors = $this->validateNomeEmail($nome, $email);
        if (!$consentimento) {
            $errors['consentimento'] = 'É necessário aceitar a Política de Privacidade.';
        }
        if ($senha !== $senhaConfirmacao) {
            $errors['senha'] = 'As senhas não conferem.';
        }
        $pwErr = $this->passwords->validate($senha);
        if ($pwErr !== []) {
            $errors['senha'] = implode(' ', $pwErr);
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $id = $this->users->create([
                'nome' => $nome,
                'email' => $email,
                'senha_hash' => $hash,
                'now' => $now,
                'versao_politica_aceita' => $this->config->policyVersion(),
            ]);
        } catch (PDOException $e) {
            if ($this->users->isDuplicateEmail($e)) {
                return ['ok' => false, 'errors' => ['email' => 'Este e-mail já está cadastrado.']];
            }

            throw $e;
        }

        return ['ok' => true, 'user_id' => $id];
    }

    public function login(string $email, string $senha): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || (int) $user['ativo'] !== 1) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        if (!password_verify($senha, (string) $user['senha_hash'])) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        return ['ok' => true, 'user' => $user];
    }

    public function requestPasswordReset(string $email): array
    {
        $email = trim($email);
        $user = $this->users->findByEmail($email);

        if ($user === null || (int) $user['ativo'] !== 1) {
            return ['ok' => true, 'message' => 'generic'];
        }

        $last = $user['ultimo_reset_solicitado_em'] ?? null;
        if ($last !== null && $last !== '') {
            $lastDt = new \DateTimeImmutable((string) $last);
            if ($lastDt > (new \DateTimeImmutable('now'))->sub(new \DateInterval('PT1H'))) {
                return ['ok' => false, 'error' => 'rate_limit'];
            }
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = (new \DateTimeImmutable('now'))->add(new \DateInterval('PT1H'));
        $now = new \DateTimeImmutable('now');

        $this->users->setPasswordReset((int) $user['id'], $hash, $expires, $now);

        $link = $this->config->baseUrl() . '/redefinir-senha?token=' . urlencode($plain);
        $body = "Olá,\n\nPara redefinir sua senha, acesse o link abaixo (válido por 1 hora):\n\n{$link}\n\nSe você não solicitou, ignore este e-mail.\n";
        $this->mailer->send((string) $user['email'], 'Redefinir sua senha — ide-anunciai', $body);

        return ['ok' => true, 'message' => 'sent'];
    }

    public function resetPassword(string $token, string $novaSenha, string $confirmacao): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['ok' => false, 'errors' => ['token' => 'Token inválido.']];
        }
        if ($novaSenha !== $confirmacao) {
            return ['ok' => false, 'errors' => ['senha' => 'As senhas não conferem.']];
        }
        $pwErr = $this->passwords->validate($novaSenha);
        if ($pwErr !== []) {
            return ['ok' => false, 'errors' => ['senha' => implode(' ', $pwErr)]];
        }

        $hash = hash('sha256', $token);
        $user = $this->users->findByResetTokenHash($hash);
        if ($user === null) {
            return ['ok' => false, 'errors' => ['token' => 'Token inválido ou expirado.']];
        }

        $newHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $this->users->updatePasswordHash((int) $user['id'], $newHash);

        return ['ok' => true];
    }

    public function updateAccount(int $userId, string $nome, string $email): array
    {
        $errors = $this->validateNomeEmail($nome, $email);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $current = $this->users->findById($userId);
        if ($current === null) {
            return ['ok' => false, 'errors' => ['geral' => 'Usuário não encontrado.']];
        }

        if (mb_strtolower(trim($email)) !== mb_strtolower((string) $current['email'])) {
            $other = $this->users->findByEmail($email);
            if ($other !== null && (int) $other['id'] !== $userId) {
                return ['ok' => false, 'errors' => ['email' => 'Este e-mail já está em uso.']];
            }
        }

        try {
            $this->users->updateAccount($userId, $nome, $email);
        } catch (PDOException $e) {
            if ($this->users->isDuplicateEmail($e)) {
                return ['ok' => false, 'errors' => ['email' => 'Este e-mail já está em uso.']];
            }
            throw $e;
        }

        return ['ok' => true];
    }

    public function saveAvatar(int $userId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Falha no upload da imagem.'];
        }

        $tmp = (string) $file['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['ok' => false, 'error' => 'Arquivo de imagem inválido.'];
        }

        $mime = $info['mime'] ?? '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Use JPEG, PNG ou WebP.'];
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Imagem deve ter no máximo 5 MB.'];
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Não foi possível salvar a imagem.'];
        }

        $publicUrl = '/uploads/avatars/' . $name;
        $this->users->updateFotoUrl($userId, $publicUrl);

        return ['ok' => true, 'url' => $publicUrl];
    }

    private function validateNomeEmail(string $nome, string $email): array
    {
        $errors = [];
        $nome = trim($nome);
        if ($nome === '') {
            $errors['nome'] = 'Nome é obrigatório.';
        }
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        return $errors;
    }
}
