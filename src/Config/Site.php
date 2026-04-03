<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Nome do site, menus e rodapé: altere aqui para refletir em todo o layout.
 */
final class Site
{
    public const NAME = 'ide-anunciai';

    public const FOOTER_TAGLINE = 'MVP — conectando igrejas, ministros e profissionais';

    /** @return list<array{label: string, path: string}> */
    public static function navGuest(): array
    {
        return [
            ['label' => 'Início', 'path' => '/'],
            ['label' => 'Buscar', 'path' => '/busca'],
            ['label' => 'Cadastrar', 'path' => '/cadastro'],
            ['label' => 'Entrar', 'path' => '/login'],
        ];
    }

    /** @return list<array{label: string, path: string}> */
    public static function navUser(): array
    {
        return [
            ['label' => 'Início', 'path' => '/'],
            ['label' => 'Buscar', 'path' => '/busca'],
            ['label' => 'Minha conta', 'path' => '/conta'],
            ['label' => 'Conversas', 'path' => '/chat'],
            ['label' => 'Perfil ministro', 'path' => '/meu-perfil/ministro'],
            ['label' => 'Perfil igreja', 'path' => '/meu-perfil/igreja'],
            ['label' => 'Verificação', 'path' => '/verificacao'],
        ];
    }

    /** @return list<array{label: string, path: string}> */
    public static function navAdmin(): array
    {
        return [
            ['label' => 'Admin verificações', 'path' => '/admin/verificacoes'],
            ['label' => 'Admin revisão', 'path' => '/admin/revisao'],
            ['label' => 'Admin denúncias', 'path' => '/admin/denuncias'],
        ];
    }

    /** @return list<array{label: string, path: string}> */
    public static function footerLinks(): array
    {
        return [
            ['label' => 'Início', 'path' => '/'],
            ['label' => 'Buscar', 'path' => '/busca'],
            ['label' => 'Privacidade', 'path' => '/privacidade'],
        ];
    }
}
