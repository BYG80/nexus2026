<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');
require_once __DIR__ . '/../includes/ai-chat.php';
$message = '';
$error = '';
$info = nexus_guild_info();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        $values = nexus_guild_validate($_POST);
        $pdo = db();
        $pdo->exec('CREATE TABLE IF NOT EXISTS nexus_settings (setting_key VARCHAR(80) NOT NULL PRIMARY KEY, value TEXT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $stmt = $pdo->prepare('INSERT INTO nexus_settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $stmt->execute(['ai_guild_info', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $info = nexus_guild_info(true);
        $message = 'Información actualizada. El chat ya utiliza estos datos.';
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No se han podido guardar los datos. Comprueba la conexión y los permisos de la base de datos.';
    }
}
admin_header('Asistente IA', 'chat');
?>
<div class="eyebrow">NEXUS · INFORMACIÓN DE LA HERMANDAD</div>
<h1>Asistente IA</h1>
<p>El asistente utiliza estos datos públicos, las noticias publicadas y los próximos eventos. No tiene acceso a mensajes de Discord, contraseñas ni solicitudes privadas.</p>
<?php if ($message): ?><div class="flash"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-card">
    <h2>Estado: <?=nexus_chat_available() ? 'IA disponible' : 'pendiente de activar en el servidor'?></h2>
    <p>Facción: <strong><?=e(nexus_faction_label())?></strong>. <?php if (!nexus_chat_available()): ?>Configura <code>OPENAI_API_KEY</code> en el entorno del servidor o <code>openai_api_key</code> en tu <code>config/config.php</code> privado, y habilita PHP cURL. La clave no se introduce en esta página ni en GitHub.<?php endif; ?></p>
    <p>Por defecto: 8 preguntas por sesión cada 10 minutos, 30 por IP y hora y 100 al día para toda la web. La API tiene consumo facturable; ajusta también el presupuesto del proyecto en OpenAI.</p>
</div>
<div class="admin-card" style="margin-top:20px">
    <h2>Datos que puede comunicar el chat</h2>
    <form class="admin-form" method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <label>Invitación de Discord<input type="url" name="discord_invite" maxlength="200" value="<?=e($info['discord_invite'])?>" required></label>
        <label>Días y horarios de raid<textarea name="raid_schedule" maxlength="1200" placeholder="Escribe los días y horas oficiales, solo cuando estén confirmados."><?=e($info['raid_schedule'])?></textarea></label>
        <label>Zona horaria<input name="raid_timezone" value="<?=e($info['raid_timezone'])?>" maxlength="80" required></label>
        <label>Canal de voz para raids (nombre público)<input name="raid_voice_channel" maxlength="150" value="<?=e($info['raid_voice_channel'])?>"></label>
        <label>Reclutamiento y requisitos<textarea name="recruitment" maxlength="1500"><?=e($info['recruitment'])?></textarea></label>
        <label>Otra información pública confirmada<textarea name="extra_info" maxlength="3000"><?=e($info['extra_info'])?></textarea></label>
        <p class="small">No incluyas tokens, contraseñas ni información privada. Si dejas los horarios vacíos, el asistente indicará que no están confirmados.</p>
        <button class="btn primary" type="submit">Guardar información</button>
    </form>
</div>
<?php admin_footer(); ?>
