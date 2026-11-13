<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
start_secure_session();

/*
 * Recuperación de administrador deliberadamente NO usa una clave almacenada
 * dentro del ZIP ni en config.php. El secreto vive en un archivo oculto
 * fuera de public_html: ../.nexus_admin_recovery
 *
 * Tras usarlo correctamente se elimina automáticamente. Para disponer de
 * recuperación otra vez, el propietario debe crear un nuevo secreto desde
 * el Administrador de archivos/cPanel o SSH.
 */
$secretFile = dirname(NEXUS_ROOT) . DIRECTORY_SEPARATOR . '.nexus_admin_recovery';
$error = '';
$done = false;

if (!is_file($secretFile) || !is_readable($secretFile)) {
    http_response_code(404);
    exit('Recuperación deshabilitada. Crea el archivo privado .nexus_admin_recovery fuera de public_html para habilitarla.');
}

$recoverySecret = trim((string)file_get_contents($secretFile));
if (strlen($recoverySecret) < 32) {
    http_response_code(500);
    exit('El secreto de recuperación no cumple el mínimo de seguridad.');
}

if (!isset($_SESSION['recovery_nonce'])) {
    $_SESSION['recovery_nonce'] = bin2hex(random_bytes(32));
    $_SESSION['recovery_issued_at'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string)($_SESSION['recovery_nonce'] ?? ''), (string)($_POST['nonce'] ?? ''))) {
            throw new RuntimeException('Solicitud de recuperación no válida. Recarga la página.');
        }
        if ((time() - (int)($_SESSION['recovery_issued_at'] ?? 0)) > 900) {
            throw new RuntimeException('La solicitud ha caducado. Recarga la página.');
        }

        $key = (string)($_POST['recovery_key'] ?? '');
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (!hash_equals($recoverySecret, $key)) {
            throw new RuntimeException('Clave de recuperación incorrecta.');
        }
        if ($username === '' || strlen($username) > 80) {
            throw new RuntimeException('Usuario no válido.');
        }
        if (strlen($password) < 14 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('La nueva contraseña debe tener al menos 14 caracteres e incluir mayúsculas, minúsculas y números.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            $pdo->rollBack();
            throw new RuntimeException('El usuario indicado no existe.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $u = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin' WHERE id = ?");
        $u->execute([$hash, (int)$id]);
        $pdo->commit();

        /* El secreto es de un solo uso. */
        @unlink($secretFile);
        unset($_SESSION['recovery_nonce'], $_SESSION['recovery_issued_at']);
        session_regenerate_id(true);
        $done = true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $error = 'No se pudo completar la recuperación: ' . $e->getMessage();
        usleep(350000);
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recuperación segura | NEXUS</title><link rel="stylesheet" href="/css/global.css"><link rel="stylesheet" href="/Admin/assets/admin.css"></head>
<body class="admin-body"><main class="admin-main"><div class="admin-card login-admin">
<div class="eyebrow">NEXUS · RECUPERACIÓN SEGURA</div><h1>Recuperar administrador</h1>
<?php if ($done): ?>
<div class="flash">Contraseña restablecida correctamente. El secreto de recuperación ha quedado invalidado y el archivo se ha eliminado.</div>
<a class="btn primary" href="/Admin/">Ir al panel</a>
<?php else: ?>
<p class="muted">Esta herramienta solo funciona con un secreto privado de un solo uso almacenado fuera de <code>public_html</code>. La solicitud caduca en 15 minutos.</p>
<?php if ($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<form class="admin-form" method="post" autocomplete="off">
<input type="hidden" name="nonce" value="<?=e((string)$_SESSION['recovery_nonce'])?>">
<label>Secreto de recuperación<input type="password" name="recovery_key" required autocomplete="off"></label>
<label>Usuario administrador<input name="username" required autocomplete="username"></label>
<label>Nueva contraseña<input type="password" name="password" minlength="14" required autocomplete="new-password"></label>
<button class="btn primary" type="submit">Restablecer administrador</button>
</form>
<p class="muted small" style="margin-top:16px">El secreto no se guarda en la web ni en la base de datos y se elimina después de un uso correcto.</p>
<?php endif; ?></div></main></body></html>
