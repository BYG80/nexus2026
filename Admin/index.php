<?php

require_once __DIR__ . '/../config/bootstrap.php';

start_secure_session();

$error = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {

            $error = 'Introduce usuario y contraseña.';

        } else {

            $pdo = db();

            $stmt = $pdo->prepare(
                'SELECT id, username, password_hash, role
                 FROM users
                 WHERE username = ?
                 LIMIT 1'
            );

            $stmt->execute([$username]);

            $user = $stmt->fetch();

            if (
                $user !== false &&
                !empty($user['password_hash']) &&
                password_verify($password, $user['password_hash'])
            ) {

                session_regenerate_id(true);

                $_SESSION['admin_user'] = (string)$user['username'];
                $_SESSION['admin_role'] = (string)$user['role'];

                header('Location: /Admin/dashboard.php');
                exit;
            }

            $error = 'Usuario o contraseña incorrectos. Si acabas de instalar esta versión, comprueba que el usuario existe en la tabla users. Si has olvidado la contraseña, usa el recuperador de administrador incluido en el ZIP.';
        }

    } catch (Throwable $e) {

        $error = 'ERROR: ' . $e->getMessage();
    }
}

?>
<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title>Acceso administrador | NEXUS</title>

<link rel="stylesheet"
      href="/css/global.css">

<link rel="stylesheet"
      href="/Admin/assets/admin.css">

<link rel="icon"
      href="/favicon.ico">

</head>

<body class="admin-body">

<main class="admin-main">

<div class="admin-card login-admin">

<div class="eyebrow">
NEXUS · PANEL PRIVADO
</div>

<h1>Administración</h1>

<?php if ($info !== ''): ?>
<div class="flash">
<?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>

<div class="error-box">
<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<form class="admin-form" method="post">

<label>
Usuario

<input
    type="text"
    name="username"
    autocomplete="username"
    required
    autofocus
>
</label>

<label>
Contraseña

<input
    type="password"
    name="password"
    autocomplete="current-password"
    required
>
</label>

<button
    class="btn primary"
    type="submit">
    Iniciar sesión
</button>

</form>

<p class="muted" style="margin-top:18px">
Si no recuerdas la contraseña, utiliza temporalmente <code>/Admin/recover.php</code> y elimínalo después de recuperar el acceso.
</p>

</div>

</main>

</body>
</html>