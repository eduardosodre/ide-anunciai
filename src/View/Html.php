<?php

declare(strict_types=1);

namespace App\View;

use App\Http\BasePath;
use App\Session\SessionFacade;

final class Html
{
    public static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Caminho absoluto no site (inclui prefixo /public quando aplicável). */
    public static function u(string $path): string
    {
        return BasePath::url($path);
    }

    public static function layout(string $title, string $body, ?string $csrfToken = null): string
    {
        $uid = SessionFacade::userId();
        $navMain = '';
        if ($uid !== null && $csrfToken !== null) {
            $adminLinks = '';
            if (SessionFacade::isAdmin()) {
                $adminLinks = '<span class="nav-sep"></span><a href="' . self::u('/admin/verificacoes') . '">Admin verificações</a>'
                    . '<a href="' . self::u('/admin/revisao') . '">Admin revisão</a>'
                    . '<a href="' . self::u('/admin/denuncias') . '">Admin denúncias</a>';
            }
            $navMain = '<a href="' . self::u('/') . '">Início</a>'
                . '<a href="' . self::u('/busca') . '">Buscar</a>'
                . '<a href="' . self::u('/conta') . '">Minha conta</a>'
                . '<a href="' . self::u('/chat') . '">Conversas</a>'
                . '<a href="' . self::u('/meu-perfil/ministro') . '">Perfil ministro</a>'
                . '<a href="' . self::u('/meu-perfil/igreja') . '">Perfil igreja</a>'
                . '<a href="' . self::u('/verificacao') . '">Verificação</a>'
                . $adminLinks
                . '<span class="nav-sep"></span>'
                . '<form action="' . self::u('/sair') . '" method="post" class="nav-logout">'
                . '<input type="hidden" name="csrf_token" value="' . self::escape($csrfToken) . '">'
                . '<button type="submit">Sair</button>'
                . '</form>';
        } else {
            $navMain = '<a href="' . self::u('/') . '">Início</a>'
                . '<a href="' . self::u('/busca') . '">Buscar</a>'
                . '<a href="' . self::u('/cadastro') . '">Cadastrar</a>'
                . '<a href="' . self::u('/login') . '">Entrar</a>';
        }

        return '<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>' . self::escape($title) . ' — ide-anunciai</title>
    <style>
        :root {
            --bg: #f8f9fb;
            --surface: #fff;
            --text: #1a1d26;
            --muted: #5c6370;
            --border: #e2e5eb;
            --accent: #1a56db;
            --accent-hover: #1547b8;
            --radius: 10px;
            --shadow: 0 1px 3px rgba(0,0,0,.06);
            --header-h: 3.25rem;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12141a;
                --surface: #1c1f28;
                --text: #eef0f4;
                --muted: #a8b0c0;
                --border: #2e3444;
                --accent: #4d8eff;
                --accent-hover: #7aa8ff;
                --shadow: 0 1px 3px rgba(0,0,0,.35);
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif;
            margin: 0;
            line-height: 1.55;
            color: var(--text);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        a { color: var(--accent); }
        a:hover { color: var(--accent-hover); }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            border: 0;
        }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .header-inner {
            max-width: 52rem;
            margin: 0 auto;
            padding: 0.65rem 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
        }
        .brand {
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            color: var(--text);
            margin-right: auto;
        }
        .brand:hover { color: var(--accent); }
        .nav-toggle-label {
            display: none;
            cursor: pointer;
            font-size: 1.35rem;
            line-height: 1;
            padding: 0.35rem 0.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg);
            user-select: none;
        }
        .main-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.75rem;
            font-size: 0.92rem;
        }
        .main-nav a { text-decoration: none; white-space: nowrap; }
        .main-nav a:hover { text-decoration: underline; }
        .nav-sep { display: inline-block; width: 0; }
        .nav-logout { display: inline; margin: 0; }
        .nav-logout button {
            background: none;
            border: none;
            padding: 0;
            color: var(--accent);
            cursor: pointer;
            font: inherit;
            font-size: 0.92rem;
            text-decoration: none;
        }
        .nav-logout button:hover { text-decoration: underline; color: var(--accent-hover); }
        @media (max-width: 767px) {
            .nav-toggle-label { display: block; }
            .main-nav {
                display: none;
                flex-direction: column;
                align-items: flex-start;
                flex-basis: 100%;
                padding: 0.5rem 0 0;
                border-top: 1px solid var(--border);
                margin-top: 0.25rem;
            }
            .nav-toggle:checked ~ .main-nav { display: flex; }
        }
        @media (min-width: 768px) {
            .nav-toggle-label { display: none !important; }
            .main-nav { display: flex !important; flex-direction: row; flex-wrap: wrap; align-items: center; }
        }
        main {
            flex: 1;
            width: 100%;
            max-width: 52rem;
            margin: 0 auto;
            padding: 1.25rem 1rem 2.5rem;
        }
        .site-footer {
            margin-top: auto;
            padding: 1.25rem 1rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
            font-size: 0.88rem;
            color: var(--muted);
            text-align: center;
        }
        .site-footer nav { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem 0.75rem; }
        .site-footer a { color: var(--muted); }
        .site-footer a:hover { color: var(--accent); }
        .msg { padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; }
        .ok { background: #e8f5e9; color: #1b5e20; }
        .err { background: #ffebee; color: #b71c1c; }
        @media (prefers-color-scheme: dark) {
            .ok { background: #1b3d1f; color: #c8e6c9; }
            .err { background: #3d1b1b; color: #ffcdd2; }
        }
        label { display: block; margin-top: 0.75rem; }
        input[type=text], input[type=email], input[type=password], input[type=file], select, textarea {
            width: 100%;
            max-width: 24rem;
            padding: 0.45rem 0.55rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
        }
        button[type=submit], .btn {
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 8px;
            border: 1px solid var(--border);
            font: inherit;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff !important;
            border-color: var(--accent);
        }
        .btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); }
        .btn-secondary {
            background: var(--surface);
            color: var(--text) !important;
        }
        .hero {
            padding: 1.5rem 0 2rem;
        }
        .hero h1 {
            font-size: clamp(1.65rem, 4vw, 2.1rem);
            line-height: 1.2;
            margin: 0 0 0.75rem;
        }
        .hero-lead {
            font-size: 1.05rem;
            color: var(--muted);
            max-width: 38rem;
            margin: 0 0 1.25rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
        }
        .hero-actions .btn { margin-top: 0; }
        .hero-compact { padding: 1rem 0 1.5rem; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.15rem;
            margin: 1rem 0;
            box-shadow: var(--shadow);
        }
        .result-list { list-style: none; padding: 0; margin: 0; }
        .result-list li {
            padding: 0.65rem 0;
            border-bottom: 1px solid var(--border);
        }
        .result-list li:last-child { border-bottom: none; }
        .badge-verified {
            display: inline-block;
            margin-left: 0.35rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #e8f5e9;
            color: #1b5e20;
            vertical-align: middle;
        }
        @media (prefers-color-scheme: dark) {
            .badge-verified { background: #1b3d1f; color: #c8e6c9; }
        }
        .profile-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            align-items: center;
        }
        .profile-actions .btn { margin-top: 0; }
        .profile-muted { font-size: 0.9rem; color: var(--muted); margin: 0; }
        .skills-list { margin: 0.35rem 0 0; padding-left: 1.25rem; }
        h1 { font-size: 1.45rem; margin-top: 0; }
        h2 { font-size: 1.15rem; margin: 1.25rem 0 0.5rem; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="' . self::u('/') . '">ide-anunciai</a>
        <input type="checkbox" id="site-nav-toggle" class="nav-toggle sr-only">
        <label for="site-nav-toggle" class="nav-toggle-label"><span aria-hidden="true">☰</span><span class="sr-only">Abrir ou fechar menu</span></label>
        <nav class="main-nav" aria-label="Principal">' . $navMain . '</nav>
    </div>
</header>
<main>' . $body . '</main>
<footer class="site-footer">
    <nav aria-label="Rodapé">
        <a href="' . self::u('/') . '">Início</a>
        <span aria-hidden="true"> · </span>
        <a href="' . self::u('/busca') . '">Buscar</a>
        <span aria-hidden="true"> · </span>
        <a href="' . self::u('/privacidade') . '">Privacidade</a>
    </nav>
    <p style="margin:0.5rem 0 0">MVP — conectando igrejas, ministros e profissionais</p>
</footer>
</body>
</html>';
    }
}
