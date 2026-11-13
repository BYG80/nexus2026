<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_admin();

$pdo = db();
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'delete_character') {
            $profileId = (int)($_POST['profile_id'] ?? 0);
            if ($profileId <= 0) throw new RuntimeException('Debes seleccionar un personaje.');

            $stmt = $pdo->prepare('SELECT mp.id,mp.name,mp.discord_id,m.discord_username FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id WHERE mp.id=? AND m.is_member=1 LIMIT 1');
            $stmt->execute([$profileId]);
            $character = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$character) throw new RuntimeException('El personaje seleccionado no existe o ya no pertenece a un miembro activo.');

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM member_profiles WHERE id=?')->execute([$profileId]);
            $pdo->prepare("DELETE FROM roster WHERE discord_id=? AND source='member' AND name=?")->execute([$character['discord_id'], $character['name']]);
            $pdo->commit();
            $flash = 'Personaje «' . (string)$character['name'] . '» de «' . (string)$character['discord_username'] . '» eliminado correctamente.';
        } elseif ($action === 'delete_member') {

        $discordId = trim((string)($_POST['discord_id'] ?? ''));
        if ($discordId === '') {
            throw new RuntimeException('Debes seleccionar un miembro.');
        }

        $stmt = $pdo->prepare('SELECT discord_id,discord_username FROM members WHERE discord_id=? AND is_member=1 LIMIT 1');
        $stmt->execute([$discordId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new RuntimeException('El miembro seleccionado no existe o ya no está activo.');
        }

        $pdo->beginTransaction();

        // Quitamos los datos que identifican al miembro como miembro activo.
        // Conservamos historial de eventos/RSP/pedidos para no perder registros históricos.
        $pdo->prepare('DELETE FROM professions WHERE member_discord_id=?')->execute([$discordId]);
        $pdo->prepare('DELETE FROM member_profiles WHERE discord_id=?')->execute([$discordId]);
        $pdo->prepare("DELETE FROM roster WHERE discord_id=? AND source='member'")->execute([$discordId]);

        // Si existe una cuenta administrativa vinculada a Discord, deja de ser miembro,
        // pero no se elimina la cuenta de administrador/editor.
        $pdo->prepare("UPDATE users SET is_member=0, member_role_name=NULL, verified_at=NULL WHERE discord_id=?")->execute([$discordId]);

        $pdo->prepare('DELETE FROM members WHERE discord_id=?')->execute([$discordId]);

        $pdo->commit();

        $flash = 'Miembro «' . (string)$member['discord_username'] . '» eliminado correctamente. Se ha conservado su historial de eventos y RSP.';
        } else {
            throw new RuntimeException('Acción no válida.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$members = [];
try {
    $stmt = $pdo->query("SELECT m.discord_id,m.discord_username,m.member_role_name,m.verified_at,
        GROUP_CONCAT(DISTINCT CONCAT(mp.name, ' · ', mp.class_name) ORDER BY mp.name SEPARATOR ' | ') AS characters
        FROM members m
        LEFT JOIN member_profiles mp ON mp.discord_id=m.discord_id
        WHERE m.is_member=1
        GROUP BY m.discord_id,m.discord_username,m.member_role_name,m.verified_at
        ORDER BY m.discord_username ASC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'No se pudieron cargar los miembros: ' . $e->getMessage();
}

$characters = [];
try {
    $stmt = $pdo->query("SELECT mp.id,mp.name,mp.race,mp.class_name,mp.level,mp.discord_id,m.discord_username
        FROM member_profiles mp
        INNER JOIN members m ON m.discord_id=mp.discord_id
        WHERE m.is_member=1
        ORDER BY m.discord_username ASC, mp.name ASC");
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'No se pudieron cargar los personajes: ' . $e->getMessage();
}

admin_header('Miembros', 'members');
?>

<div class="page-hero">
    <div class="eyebrow">NEXUS · ADMINISTRACIÓN</div>
    <h1>👥 Gestión de miembros</h1>
    <p>Selecciona un miembro de la hermandad y elimínalo desde el panel. La eliminación no borra su historial de eventos ni sus movimientos RSP.</p>
</div>

<?php if ($flash): ?>
    <div class="flash"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-box"><?= e($error) ?></div>
<?php endif; ?>

<section class="admin-card" style="max-width:900px;">
    <h2>🗑️ Eliminar miembro</h2>
    <p class="muted">
        El miembro desaparecerá de Miembros, Profesiones y de los personajes creados desde su perfil.
        Su cuenta administrativa no se elimina y su historial de eventos/RSP se conserva.
    </p>

    <?php if (!$members): ?>
        <div class="notice muted">No hay miembros activos para eliminar.</div>
    <?php else: ?>
        <form method="post" class="admin-form" onsubmit="return confirmMemberDelete();">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_member">

            <label>
                Selecciona el miembro
                <select name="discord_id" id="memberSelect" required>
                    <option value="">— Selecciona un miembro —</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= e($member['discord_id']) ?>" data-name="<?= e($member['discord_username']) ?>">
                            <?= e($member['discord_username']) ?><?php if ($member['characters']): ?> · <?= e($member['characters']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="admin-actions">
                <button class="btn danger" type="submit">🗑️ Eliminar miembro seleccionado</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="admin-card" style="margin-top:22px;">
    <h2>🎭 Eliminar personaje</h2>
    <p class="muted">Puedes eliminar un personaje concreto sin borrar al miembro. Se eliminará su ficha de personaje y su entrada vinculada del roster.</p>
    <?php if (!$characters): ?>
        <div class="notice muted">No hay personajes registrados para eliminar.</div>
    <?php else: ?>
        <form method="post" class="admin-form" onsubmit="return confirmCharacterDelete();">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_character">
            <label>
                Selecciona el personaje
                <select name="profile_id" id="characterSelect" required>
                    <option value="">— Selecciona un personaje —</option>
                    <?php foreach ($characters as $character): ?>
                        <option value="<?= (int)$character['id'] ?>" data-name="<?= e($character['name']) ?>">
                            <?= e($character['name']) ?> · <?= e($character['class_name']) ?> · <?= e($character['race']) ?> · <?= e($character['discord_username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="admin-actions">
                <button class="btn danger" type="submit">🗑️ Eliminar personaje seleccionado</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="admin-card" style="margin-top:22px;">
    <h2>📋 Miembros activos (<?= count($members) ?>)</h2>
    <?php if (!$members): ?>
        <p class="muted">No hay miembros activos.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Discord</th>
                        <th>Personajes</th>
                        <th>Rol Discord</th>
                        <th>Verificado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><strong><?= e($member['discord_username']) ?></strong></td>
                        <td><?= e($member['characters'] ?: 'Sin personaje') ?></td>
                        <td><?= e($member['member_role_name'] ?: '—') ?></td>
                        <td><?= e($member['verified_at'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
function confirmCharacterDelete() {
    const select = document.getElementById('characterSelect');
    const option = select ? select.options[select.selectedIndex] : null;
    const name = option ? (option.dataset.name || option.textContent.trim()) : '';
    if (!name) return false;
    return confirm('¿Seguro que quieres eliminar el personaje «' + name + '»?\n\nSe eliminará su ficha y su entrada vinculada del roster.');
}

function confirmMemberDelete() {
    const select = document.getElementById('memberSelect');
    const option = select ? select.options[select.selectedIndex] : null;
    const name = option ? (option.dataset.name || option.textContent.trim()) : '';
    if (!name) return false;
    return confirm(
        '¿Seguro que quieres eliminar a «' + name + '»?\n\n' +
        'Se eliminarán sus perfiles, profesiones y personajes vinculados.\n' +
        'Su historial de eventos y RSP se conservará.'
    );
}
</script>

<?php admin_footer(); ?>
