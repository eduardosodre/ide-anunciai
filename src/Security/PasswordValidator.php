<?php

declare(strict_types=1);

namespace App\Security;

final class PasswordValidator
{
    public function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter no mínimo 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/u', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra maiúscula.';
        }
        if (!preg_match('/[a-z]/u', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra minúscula.';
        }
        if (!preg_match('/[0-9]/u', $password)) {
            $errors[] = 'Senha deve conter pelo menos um número.';
        }

        return $errors;
    }
}
