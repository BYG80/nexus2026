
<?php

require_once __DIR__ . '/../config/bootstrap.php';

start_secure_session();

// Canonicalizar el dominio antes de iniciar el flujo OAuth. Así evitamos
// que una sesión creada en nexus-forever.es se pierda al volver a www.
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== 'www.nexus-forever.es') {
    $query = $_GET;
    $target = 'https://www.nexus-forever.es/auth/discord.php';
    if ($query) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target, true, 302);
    exit;
}

if (!discord_configured()) {
    http_response_code(503);
    exit('Discord no está configurado. Añade las credenciales OAuth2 en config/config.php.');
}

$pdo = null;

/*
 * Página a la que volver después de iniciar sesión.
 * Solo permitimos rutas internas.
 */
$returnTo = (string)($_GET['return_to'] ?? '/calendario.php');

if (
    $returnTo === '' ||
    $returnTo[0] !== '/' ||
    str_starts_with($returnTo, '//') ||
    preg_match('/^\/auth\//i', $returnTo)
) {
    $returnTo = '/panel.php';
}

/*
 * Si todavía no tenemos código OAuth,
 * enviamos al usuario a Discord.
 */
$code  = trim((string)($_GET['code'] ?? ''));
$state = (string)($_GET['state'] ?? '');

if (!$code) {

    $state = bin2hex(random_bytes(24));

    $_SESSION['discord_oauth_state'] = $state;
    $_SESSION['discord_oauth_return_to'] = $returnTo;

    $params = http_build_query([
        'client_id'     => app_config('discord_client_id'),
        'redirect_uri'  => discord_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'identify email',
        'state'         => $state
    ]);

    header(
        'Location: https://discord.com/oauth2/authorize?' . $params,
        true,
        302
    );

    exit;
}

/*
 * Comprobar estado OAuth.
 */
if (
    !$state ||
    !hash_equals(
        (string)($_SESSION['discord_oauth_state'] ?? ''),
        $state
    )
) {
    http_response_code(400);
    exit('Estado OAuth no válido.');
}

unset($_SESSION['discord_oauth_state']);

/*
 * Recuperar destino guardado antes de ir a Discord.
 */
$returnTo = (string)(
    $_SESSION['discord_oauth_return_to'] ?? '/calendario.php'
);

unset($_SESSION['discord_oauth_return_to']);

/*
 * Seguridad adicional para el destino.
 */
if (
    $returnTo === '' ||
    $returnTo[0] !== '/' ||
    str_starts_with($returnTo, '//') ||
    preg_match('/^\/auth\//i', $returnTo)
) {
    $returnTo = '/panel.php';
}

/*
 * Intercambiar el código OAuth por un access token.
 */
$ch = curl_init('https://discord.com/api/oauth2/token');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id'     => app_config('discord_client_id'),
        'client_secret' => app_config('discord_client_secret'),
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => discord_redirect_uri()
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$raw = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token = json_decode((string)$raw, true);

if (
    $status !== 200 ||
    empty($token['access_token'])
) {
    http_response_code(502);
    exit('No se pudo autenticar con Discord.');
}

/*
 * Obtener información del usuario de Discord.
 */
$ch = curl_init('https://discord.com/api/users/@me');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token['access_token']
    ]
]);

$raw = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$d = json_decode((string)$raw, true);

if (
    $status !== 200 ||
    empty($d['id'])
) {
    http_response_code(502);
    exit('No se pudo obtener tu cuenta de Discord.');
}

/*
 * Datos básicos del usuario.
 */
$username = (string)(
    $d['global_name']
    ?? $d['username']
    ?? 'Discord'
);

$avatar = !empty($d['avatar'])
    ? 'https://cdn.discordapp.com/avatars/'
        . $d['id']
        . '/'
        . $d['avatar']
        . '.png'
    : null;

/* Verificar pertenencia real al servidor y rol Miembros mediante el bot. */
$memberRole = discord_member_role_info((string)$d['id']);
if ($memberRole === null) {
    http_response_code(403);
    exit('Tu cuenta de Discord no tiene el rol Miembros en el servidor de NEXUS.');
}

/*
 * Guardar / actualizar usuario y miembro.
 * La conexión MySQL se abre aquí, después de completar OAuth,
 * para que un fallo de base de datos no impida siquiera iniciar
 * el flujo de login de Discord.
 */
try {
    $pdo = nexus_is_configured() ? db() : null;
} catch (Throwable $dbError) {
    http_response_code(503);
    exit(
        'La conexión con la base de datos de NEXUS no está disponible. ' .
        'Revisa config/config.php (usuario, contraseña y base de datos MySQL). ' .
        'Código: DB-1045.'
    );
}

if ($pdo) {

    /*
     * Comprobar si es administrador.
     */
    $st = $pdo->prepare(
        'SELECT id FROM users WHERE discord_id=? LIMIT 1'
    );

    $st->execute([$d['id']]);

    $admin = $st->fetch();

    if ($admin) {
        $_SESSION['admin_user'] = null;
        $_SESSION['admin_role'] = null;
    }

    /*
     * Buscar miembro.
     */
    $st = $pdo->prepare(
        'SELECT id FROM members WHERE discord_id=? LIMIT 1'
    );

    $st->execute([$d['id']]);

    $member = $st->fetch();

    if ($member) {

        $pdo->prepare(
            'UPDATE members
             SET discord_username=?,
                 discord_avatar=?,
                 is_member=1,
                 member_role_name=?,
                 verified_at=CURRENT_TIMESTAMP,
                 updated_at=CURRENT_TIMESTAMP
             WHERE id=?'
        )->execute([
            $username,
            $avatar,
            $memberRole['name'],
            $member['id']
        ]);

    } else {

        $pdo->prepare(
            'INSERT INTO members
             (discord_id, discord_username, discord_avatar, is_member, member_role_name, verified_at)
             VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)'
        )->execute([
            $d['id'],
            $username,
            $avatar,
            1,
            $memberRole['name']
        ]);
    }
}

/* Crear una nueva sesión después de OAuth. */
session_regenerate_id(true);

$_SESSION['member_discord_id'] = (string)$d['id'];
$_SESSION['member_username']   = $username;
$_SESSION['member_avatar']     = $avatar;
$_SESSION['member_role']       = $memberRole['name'];

/*
 * Volver a la página desde la que inició sesión.
 */
header('Location: ' . $returnTo, true, 302);
exit;