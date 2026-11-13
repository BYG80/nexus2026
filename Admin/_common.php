<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

start_secure_session();

/**
 * Escapado HTML seguro.
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/**
 * Comprueba que existe una sesión de administrador.
 */
function require_admin(): void
{
    if (empty($_SESSION['admin_user'])) {
        header('Location: /Admin/index.php');
        exit;
    }
}

/**
 * Comprueba el rol del administrador.
 */
function require_admin_role(string $role = 'admin'): void
{
    require_admin();

    if (
        $role === 'admin' &&
        ($_SESSION['admin_role'] ?? '') !== 'admin'
    ) {
        http_response_code(403);
        exit('No autorizado.');
    }
}

/**
 * Cabecera común del panel de administración.
 */
function admin_header(
    string $title,
    string $active = ''
): void {

    $sessionUser = $_SESSION['admin_user'] ?? '';
    $sessionRole = $_SESSION['admin_role'] ?? '';

    ?>
    <!doctype html>
    <html lang="es">

    <head>
        <meta charset="utf-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >

        <title><?= e($title) ?> | NEXUS Admin</title>

        <link
            rel="stylesheet"
            href="/css/global.css"
        >

        <link
            rel="stylesheet"
            href="/Admin/assets/admin.css"
        >

        <link
            rel="icon"
            href="/favicon.ico"
        >
    </head>

    <body class="admin-body">

        <header class="admin-top">

            <div class="inner">

                <a
                    class="admin-brand"
                    href="/Admin/dashboard.php"
                >
                    NEXUS ADMIN
                </a>

                <div>
                    <span class="small">
                        Sesión: <?= e($sessionUser) ?>
                    </span>

                    ·

                    <a
                        href="/Admin/logout.php"
                        style="color:#d8ad55"
                    >
                        Salir
                    </a>
                </div>

            </div>

            <nav class="admin-nav">

                <a
                    class="<?= $active === 'dashboard' ? 'active' : '' ?>"
                    href="/Admin/dashboard.php"
                >
                    Dashboard
                </a>

                <a
                    class="<?= $active === 'events' ? 'active' : '' ?>"
                    href="/Admin/events.php"
                >
                    Eventos
                </a>

                <a
                    class="<?= $active === 'discord' ? 'active' : '' ?>"
                    href="/Admin/discord.php"
                >
                    💬 Discord
                </a>

                <a
                    class="<?= $active === 'roster' ? 'active' : '' ?>"
                    href="/Admin/roster.php"
                >
                    Roster
                </a>

                <a
                    class="<?= $active === 'professions' ? 'active' : '' ?>"
                    href="/Admin/profesiones.php"
                >
                    Profesiones
                </a>

                <a
                    class="<?= $active === 'members' ? 'active' : '' ?>"
                    href="/Admin/miembros.php"
                >
                    Miembros
                </a>

                <a
                    class="<?= $active === 'rsp' ? 'active' : '' ?>"
                    href="/Admin/rsp.php"
                >
                    RSP
                </a>

                <a class="<?= $active === 'recruitment' ? 'active' : '' ?>" href="/Admin/recruitment.php">Solicitudes</a>

                <?php if ($sessionRole === 'admin'): ?>
                    <a class="<?= $active === 'faction' ? 'active' : '' ?>" href="/Admin/faccion.php">
                        Facción
                    </a>
                <?php endif; ?>

                <a
                    class="<?= $active === 'news' ? 'active' : '' ?>"
                    href="/Admin/news.php"
                >
                    Noticias
                </a>

                <a
                    class="<?= $active === 'achievements' ? 'active' : '' ?>"
                    href="/Admin/logros.php"
                >
                    Logros
                </a>

                <a
                    class="<?= $active === 'media' ? 'active' : '' ?>"
                    href="/Admin/media.php"
                >
                    Multimedia
                </a>

                <?php if ($sessionRole === 'admin'): ?>

                    <a
                        class="<?= $active === 'users' ? 'active' : '' ?>"
                        href="/Admin/users.php"
                    >
                        Administradores
                    </a>

                <?php endif; ?>

                <a
                    href="/"
                    target="_blank"
                    rel="noopener"
                >
                    Ver web ↗
                </a>

            </nav>

        </header>

        <main class="admin-main">
    <?php
}

/**
 * Pie común del panel de administración.
 */
function admin_footer(): void
{
    ?>
        </main>

    </body>
    </html>
    <?php
}