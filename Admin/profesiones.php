<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_admin();

$pdo = db();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        if (trim((string)app_config('discord_bot_token', '')) === '') {
            throw new RuntimeException('Falta configurar discord_bot_token.');
        }
        if (trim((string)app_config('discord_profession_channel_id', '')) === '') {
            throw new RuntimeException('Falta configurar discord_profession_channel_id.');
        }
        $result = sync_professions_to_discord($pdo);
        $msg = $result['created'] ? 'Directorio de profesiones publicado en Discord.' : 'Directorio de profesiones actualizado en Discord.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rowsStmt = $pdo->prepare("
    SELECT character_name, member_username, race, class_name, level, profession_name, profession_type, skill_level
    FROM professions
    WHERE status='active'
      AND (faction=:faction OR faction IS NULL)
    ORDER BY character_name, profession_type, profession_name
");
$rowsStmt->execute([':faction' => nexus_faction()]);
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

admin_header('Profesiones', 'professions');
?>
<section class="admin-card">
    <h1>Profesiones</h1>
    <p class="small">El directorio se genera desde los datos guardados por los miembros y se publica en el canal de Discord configurado.</p>

    <?php if ($msg): ?><div class="flash"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash" style="border-color:#a33"><?= e($error) ?></div><?php endif; ?>

    <form method="post" style="margin:18px 0">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn primary" type="submit">🔄 Sincronizar profesiones con Discord</button>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>Personaje</th><th>Discord</th><th>Raza</th><th>Clase</th><th>Profesión</th><th>Tipo</th><th>Nivel</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7">No hay profesiones registradas.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['character_name']) ?></td>
                <td><?= e($row['member_username']) ?></td>
                <td><?= e($row['race']) ?></td>
                <td><?= e($row['class_name']) ?></td>
                <td><?= e($row['profession_name']) ?></td>
                <td><?= e($row['profession_type'] === 'secondary' ? 'Secundaria' : 'Principal') ?></td>
                <td><?= e((string)($row['skill_level'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php admin_footer(); ?>
