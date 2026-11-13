<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');
require_once __DIR__ . '/../includes/weekly-player.php';
$pdo = db();
$current = nexus_weekly_player($pdo);
$values = ['name' => $current['name'] ?? '', 'feat' => $current['feat'] ?? '', 'description' => $current['description'] ?? '', 'icon' => $current['icon'] ?? 'trophy'];
$error = '';
$message = (string)($_SESSION['weekly_player_flash'] ?? '');
unset($_SESSION['weekly_player_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null);
        if (($_POST['action'] ?? '') === 'clear') {
            nexus_weekly_clear($pdo);
            $_SESSION['weekly_player_flash'] = 'El reconocimiento se ha retirado de la portada.';
        } else {
            foreach (array_keys($values) as $key) {
                if (!is_string($_POST[$key] ?? null)) throw new InvalidArgumentException('Revisa los campos del formulario.');
                $values[$key] = trim($_POST[$key]);
            }
            nexus_weekly_save($pdo, $values['name'], $values['feat'], $values['description'], $values['icon']);
            $_SESSION['weekly_player_flash'] = 'Jugador de la semana publicado debajo del carrusel.';
        }
        header('Location: /Admin/jugador-semana.php');
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException ? 'No se ha podido guardar el reconocimiento. Revisa la conexión y los permisos de la base de datos.' : $exception->getMessage();
    }
}
admin_header('Jugador de la semana', 'weekly-player');
?>
<div class="eyebrow">NEXUS · RECONOCIMIENTOS</div>
<h1>Jugador de la semana</h1>
<p>Elige a un miembro escribiendo su nombre y apellido de WoW Forever. En el inicio aparecerán solo la imagen del personaje y la hazaña que escribas.</p>
<?php if ($message): ?><div class="flash"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-grid">
    <div class="admin-card">
        <h2>Elegir personaje y hazaña</h2>
        <form class="admin-form" method="post">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="save">
            <label>Nombre y apellido del personaje<input name="name" maxlength="120" value="<?=e($values['name'])?>" placeholder="Ej.: Melkir Vossgrave" autocomplete="off" required></label>
            <label>Hazaña o título del reconocimiento<input name="feat" maxlength="120" value="<?=e($values['feat'])?>" placeholder="Ej.: Siempre al rescate de sus compañeros"></label>
            <label>Detalle de la hazaña (opcional)<textarea name="description" rows="4" maxlength="700" placeholder="Cuenta qué ha hecho para merecer este reconocimiento."><?=e($values['description'])?></textarea></label>
            <label>Icono<select name="icon"><?php foreach (nexus_weekly_icons() as $key => $icon): ?><option value="<?=e($key)?>" <?=$values['icon'] === $key ? 'selected' : ''?>><?=e($icon['symbol'] . ' ' . $icon['label'])?></option><?php endforeach; ?></select></label>
            <button class="btn primary" type="submit">Publicar jugador de la semana</button>
        </form>
        <p class="small">Debe ser un miembro registrado en la web y su perfil oficial debe indicar Horda y NexusForever. El reconocimiento se mantiene hasta que lo cambies o lo retires.</p>
    </div>
    <div class="admin-card">
        <h2>Publicado actualmente</h2>
        <?php if ($current): ?>
            <div class="weekly-admin-portrait"><img src="<?=e($current['portrait'])?>" alt="<?=e($current['name'])?>" referrerpolicy="no-referrer"></div>
            <h3><?=e($current['name'])?></h3>
            <p><?=e(nexus_weekly_icons()[$current['icon']]['symbol'])?> <?=e($current['feat'] ?: 'Reconocimiento de la hermandad')?></p>
            <?php if ($current['description']): ?><p style="white-space:pre-line"><?=e($current['description'])?></p><?php endif; ?>
            <p><a href="/#weekly-player-title" target="_blank" rel="noopener">Ver en la portada ↗</a></p>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="clear"><button class="btn ghost" type="submit">Retirar reconocimiento</button></form>
        <?php else: ?><p>No hay un jugador de la semana publicado. La sección aparecerá cuando guardes el primero.</p><?php endif; ?>
    </div>
</div>

<?php admin_footer(); ?>
