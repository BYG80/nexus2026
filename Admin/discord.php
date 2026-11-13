<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

require_admin();

$pdo = db();
$channelId = trim((string)app_config('discord_admin_channel_id', ''));
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'send') {
            $content = trim((string)($_POST['content'] ?? ''));

            if ($content === '') {
                throw new RuntimeException('Escribe un mensaje antes de publicarlo.');
            }

            if (mb_strlen($content, 'UTF-8') > 2000) {
                throw new RuntimeException('Discord permite un máximo de 2000 caracteres en el mensaje.');
            }

            if ($channelId === '') {
                throw new RuntimeException('No hay un canal de Discord configurado para la mensajería.');
            }

            $payload = ['content' => $content];

            if (!empty($_POST['mention_everyone'])) {
                $payload['allowed_mentions'] = ['parse' => ['everyone']];
            } else {
                $payload['allowed_mentions'] = ['parse' => []];
            }

            $discordMessage = discord_send_channel_message($channelId, $payload);
            $messageId = trim((string)($discordMessage['id'] ?? ''));

            if ($messageId === '') {
                throw new RuntimeException('Discord no devolvió el ID del mensaje publicado.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO discord_admin_messages
                (channel_id, discord_message_id, content, admin_user, status)
                VALUES (?, ?, ?, ?, "sent")'
            );
            $stmt->execute([
                $channelId,
                $messageId,
                $content,
                (string)($_SESSION['admin_user'] ?? 'Administrador')
            ]);

            $flash = 'Mensaje publicado correctamente en Discord.';
        } elseif ($action === 'delete') {
            $messageId = trim((string)($_POST['message_id'] ?? ''));

            if ($messageId === '') {
                throw new RuntimeException('No se ha indicado el mensaje.');
            }

            $stmt = $pdo->prepare(
                'SELECT id, discord_message_id FROM discord_admin_messages
                 WHERE id=? AND channel_id=? LIMIT 1'
            );
            $stmt->execute([(int)($_POST['id'] ?? 0), $channelId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (string)$row['discord_message_id'] !== $messageId) {
                throw new RuntimeException('El mensaje no existe en el historial.');
            }

            discord_delete_channel_message($channelId, $messageId);

            $upd = $pdo->prepare(
                'UPDATE discord_admin_messages
                 SET status="deleted", deleted_at=NOW()
                 WHERE id=?'
            );
            $upd->execute([(int)$row['id']]);

            $flash = 'Mensaje eliminado de Discord.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$messages = [];
try {
    $stmt = $pdo->prepare(
        'SELECT id, discord_message_id, content, admin_user, status, created_at, deleted_at
         FROM discord_admin_messages
         WHERE channel_id=?
         ORDER BY id DESC
         LIMIT 50'
    );
    $stmt->execute([$channelId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'No se pudo cargar el historial: ' . $e->getMessage();
}

admin_header('Mensajes Discord', 'discord');
?>

<div class="page-hero">
    <div class="eyebrow">NEXUS · COMUNICACIÓN</div>
    <h1>💬 Mensajes de Discord</h1>
    <p>Publica avisos directamente desde el panel de administración en el canal configurado de Discord.</p>
</div>

<?php if ($flash): ?>
    <div class="flash"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-box"><?= e($error) ?></div>
<?php endif; ?>

<div class="admin-grid">
    <section class="admin-card" style="grid-column: span 2;">
        <h2>📢 Nuevo mensaje</h2>
        <p class="muted">
            Canal de destino: <strong>#<?= e($channelId) ?></strong>
        </p>

        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="send">

            <label>
                Mensaje
                <textarea
                    name="content"
                    maxlength="2000"
                    required
                    autofocus
                    placeholder="Escribe aquí el aviso para la hermandad..."
                    style="min-height:220px;"
                ></textarea>
            </label>

            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="mention_everyone" value="1" style="width:auto;">
                Permitir @everyone / @here si aparecen en el texto
            </label>

            <div class="admin-actions">
                <button class="btn primary" type="submit">📤 Publicar en Discord</button>
            </div>
        </form>

        <p class="small muted" style="margin-top:12px;">
            Máximo 2000 caracteres. El mensaje se publica como el bot de NEXUS y queda registrado en este panel.
        </p>
    </section>

    <section class="admin-card">
        <h2>ℹ️ Canal configurado</h2>
        <p class="muted">ID del canal</p>
        <strong style="font-size:1.2rem;word-break:break-all;"><?= e($channelId) ?></strong>
        <p class="small muted" style="margin-top:12px;">
            El bot necesita permiso para <strong>View Channel</strong> y <strong>Send Messages</strong>.
        </p>
    </section>
</div>

<section class="admin-card" style="margin-top:22px;">
    <h2>🕘 Historial reciente</h2>

    <?php if (!$messages): ?>
        <p class="muted">Todavía no has publicado mensajes desde el panel.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Administrador</th>
                        <th>Mensaje</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $message): ?>
                    <tr>
                        <td class="small"><?= e($message['created_at']) ?></td>
                        <td><?= e($message['admin_user'] ?: 'Administrador') ?></td>
                        <td style="min-width:300px;white-space:pre-wrap;"><?= e($message['content']) ?></td>
                        <td>
                            <?php if ($message['status'] === 'sent'): ?>
                                <span>🟢 Publicado</span>
                            <?php else: ?>
                                <span>⚪ Eliminado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($message['status'] === 'sent' && $message['discord_message_id']): ?>
                                <form method="post" onsubmit="return confirm('¿Eliminar este mensaje también de Discord?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                                    <input type="hidden" name="message_id" value="<?= e($message['discord_message_id']) ?>">
                                    <button class="btn danger" type="submit">🗑️ Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
