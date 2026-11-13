<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_admin();
$raceClasses = nexus_wow_races();
$currentFaction = nexus_faction();

$pdo = db();
// Compatibilidad con instalaciones antiguas: estas columnas deben existir antes de cualquier SELECT/UPDATE.
try { $pdo->exec("ALTER TABLE roster ADD COLUMN source ENUM('admin','member') NOT NULL DEFAULT 'admin' AFTER faction"); } catch (Throwable $ignore) {}
try { $pdo->exec("ALTER TABLE roster ADD COLUMN roster_group VARCHAR(2) NOT NULL DEFAULT 'A' AFTER notes"); } catch (Throwable $ignore) {}
try { $pdo->exec("ALTER TABLE roster MODIFY roster_group VARCHAR(2) NOT NULL DEFAULT 'A'"); } catch (Throwable $ignore) {}
try { $pdo->exec("UPDATE roster SET roster_group='A' WHERE roster_group IS NULL OR roster_group NOT IN ('A','B','20','40')"); } catch (Throwable $ignore) {}
$discordMembers = [];
try { $discordMembers = $pdo->query('SELECT discord_id,discord_username FROM members ORDER BY discord_username ASC')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$memberCharacters = [];
try {
    $profileStmt = $pdo->prepare("SELECT mp.id,mp.discord_id,mp.name,mp.race,mp.class_name,mp.level,m.discord_username
        FROM member_profiles mp
        INNER JOIN members m ON m.discord_id=mp.discord_id
        WHERE m.is_member=1 AND mp.faction=?
        ORDER BY m.discord_username,mp.name");
    $profileStmt->execute([$currentFaction]);
    $memberCharacters = $profileStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$msg = '';
$edit = null;

$classes = [
    'Guerrero'  => '#C79C6E',
    'Cazador'   => '#ABD473',
    'Pícaro'    => '#FFF569',
    'Sacerdote' => '#FFFFFF',
    'Chamán'    => '#0070DE',
    'Mago'      => '#69CCF0',
    'Brujo'     => '#9482C9',
    'Druida'    => '#FF7D0A',
    'Paladín'   => '#F58CBA'
];

$races = array_keys($raceClasses);

/*
|--------------------------------------------------------------------------
| PROCESAR POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        check_csrf($_POST['csrf'] ?? null);

        $action = (string)($_POST['action'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | GUARDAR PERSONAJE
        |--------------------------------------------------------------------------
        */

        if ($action === 'save') {

            $name = trim((string)($_POST['name'] ?? ''));
            $race = trim((string)($_POST['race'] ?? ''));
            $class = trim((string)($_POST['class_name'] ?? ''));
            $profileId = (int)($_POST['profile_id'] ?? 0);
            if ($profileId > 0) {
                $profileLookup = $pdo->prepare("SELECT mp.discord_id,mp.name,mp.race,mp.class_name,mp.level,mp.role
                    FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id
                    WHERE mp.id=? AND mp.faction=? AND m.is_member=1 LIMIT 1");
                $profileLookup->execute([$profileId, $currentFaction]);
                $profile = $profileLookup->fetch(PDO::FETCH_ASSOC);
                if (!$profile) throw new RuntimeException('El personaje seleccionado no pertenece a un miembro verificado de esta facción.');
                $name = trim((string)$profile['name']);
                $race = trim((string)$profile['race']);
                $class = trim((string)$profile['class_name']);
                $_POST['level'] = $profile['level'] !== null ? (string)$profile['level'] : '';
                // En el panel de miembros el rol se presenta actualmente como DPS;
                // usar ese mismo valor al importar evita tener que rellenarlo de nuevo.
                if (trim((string)($_POST['role'] ?? '')) === '') $_POST['role'] = trim((string)($profile['role'] ?? '')) ?: 'DPS';
                $_POST['discord_id'] = (string)$profile['discord_id'];
            }

            $levelRaw = trim((string)($_POST['level'] ?? ''));

            $level = null;

            if ($levelRaw !== '') {
                $level = (int)$levelRaw;
            }

            $role = trim((string)($_POST['role'] ?? ''));

            $status = (
                isset($_POST['status']) &&
                $_POST['status'] === 'inactive'
            )
                ? 'inactive'
                : 'active';

            $notes = trim((string)($_POST['notes'] ?? ''));
            $rosterGroupRaw = (string)($_POST['roster_group'] ?? 'A');
            $rosterGroup = in_array($rosterGroupRaw, ['A','B','20','40'], true) ? $rosterGroupRaw : 'A';
            $discordId = trim((string)($_POST['discord_id'] ?? ''));

            /*
            |--------------------------------------------------------------------------
            | VALIDACIONES
            |--------------------------------------------------------------------------
            */

            if ($name === '') {
                throw new RuntimeException(
                    'El nombre del personaje es obligatorio.'
                );
            }

            if ($race === '') {
                throw new RuntimeException(
                    'Debes seleccionar una raza.'
                );
            }

            if (!in_array($race, $races, true)) {
                throw new RuntimeException(
                    'La raza seleccionada no es válida.'
                );
            }

            if ($class === '') {
                throw new RuntimeException(
                    'Debes seleccionar una clase.'
                );
            }

            if (!array_key_exists($class, $classes)) {
                throw new RuntimeException(
                    'La clase seleccionada no es válida.'
                );
            }
            if (!in_array($class, $raceClasses[$race] ?? [], true)) {
                throw new RuntimeException('La combinación raza/clase no es válida para WoW: Forever '.nexus_faction_label().'.');
            }

            if ($level !== null && ($level < 1 || $level > 100)) {
                throw new RuntimeException(
                    'El nivel debe estar entre 1 y 100.'
                );
            }

            $color = $classes[$class];

            /*
            |--------------------------------------------------------------------------
            | ID
            |--------------------------------------------------------------------------
            |
            | Si viene un ID > 0 = editar.
            | Si NO viene ID = crear.
            |
            */

            $id = isset($_POST['id'])
                ? (int)$_POST['id']
                : 0;

            /*
            |--------------------------------------------------------------------------
            | EDITAR
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $check = $pdo->prepare(
                    'SELECT id
                     FROM roster
                     WHERE id = ?
                     LIMIT 1'
                );

                $check->execute([$id]);

                if (!$check->fetchColumn()) {
                    throw new RuntimeException(
                        'El personaje que intentas editar no existe.'
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE roster
                     SET
                        name = ?,
                        race = ?,
                        class_name = ?,
                        class_color = ?,
                        level = ?,
                        role = ?,
                        status = ?,
                        notes = ?,
                        roster_group = ?,
                        discord_id = ?,
                        faction = ?,
                        source = 'admin'
                     WHERE id = ?"
                );

                $stmt->execute([
                    $name,
                    $race,
                    $class,
                    $color,
                    $level,
                    $role,
                    $status,
                    $notes,
                    $rosterGroup,
                    $discordId !== '' ? $discordId : null,
                    $currentFaction,
                    $id
                ]);

                /*
                | Redirección después de editar.
                */
                header(
                    'Location: /Admin/roster.php?saved=edit&id=' .
                    $id
                );

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | CREAR NUEVO
            |--------------------------------------------------------------------------
            |
            | IMPORTANTE:
            | Aquí NO comprobamos si la clase ya existe.
            |
            | Por ejemplo:
            |
            | Chamán
            | Chamán
            | Chamán
            | Chamán
            |
            | Todos son registros independientes.
            |
            */

            $stmt = $pdo->prepare(
                "INSERT INTO roster
                (
                    name,
                    race,
                    class_name,
                    class_color,
                    level,
                    role,
                    status,
                    notes,
                    roster_group,
                    discord_id,
                    faction,
                    source
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin')"
            );

            $stmt->execute([
                $name,
                $race,
                $class,
                $color,
                $level,
                $role,
                $status,
                $notes,
                $rosterGroup,
                $discordId !== '' ? $discordId : null,
                $currentFaction
            ]);

            $newId = (int)$pdo->lastInsertId();

            /*
            | Redirección después de crear.
            */
            header(
                'Location: /Admin/roster.php?saved=new&id=' .
                $newId
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | ELIMINAR
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            $id = isset($_POST['id'])
                ? (int)$_POST['id']
                : 0;

            if ($id <= 0) {
                throw new RuntimeException(
                    'ID no válido.'
                );
            }

            $stmt = $pdo->prepare(
                'DELETE FROM roster
                 WHERE id = ?'
            );

            $stmt->execute([$id]);

            header(
                'Location: /Admin/roster.php?deleted=1'
            );

            exit;
        }

    } catch (Throwable $e) {

        $msg = 'Error: ' . $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| MENSAJES DESPUÉS DE REDIRECCIÓN
|--------------------------------------------------------------------------
*/

if (isset($_GET['saved'])) {

    $saved = (string)$_GET['saved'];
    $savedId = isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;

    if ($saved === 'new') {

        $msg =
            'Personaje creado correctamente.' .
            ($savedId > 0
                ? ' ID: ' . $savedId
                : '');

    } elseif ($saved === 'edit') {

        $msg =
            'Personaje actualizado correctamente.' .
            ($savedId > 0
                ? ' ID: ' . $savedId
                : '');
    }
}

if (isset($_GET['deleted'])) {

    $msg = 'Personaje eliminado correctamente.';
}


/*
|--------------------------------------------------------------------------
| CARGAR PERSONAJE PARA EDITAR
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit']) &&
    $_GET['edit'] !== ''
) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $pdo->prepare(
            'SELECT *
             FROM roster
             WHERE id = ? AND faction = ?
             LIMIT 1'
        );

        $stmt->execute([$editId, $currentFaction]);

        $edit = $stmt->fetch();

        if (!$edit) {

            $msg = 'El personaje solicitado no existe.';
            $edit = null;
        }
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER TODOS LOS PERSONAJES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT id,name,race,class_name,class_color,level,role,status,notes,roster_group
     FROM roster
     WHERE faction = ?
     ORDER BY roster_group ASC,status DESC,class_name ASC,name ASC'
);
$stmt->execute([$currentFaction]);
$rows = $stmt->fetchAll();

admin_header('Roster', 'roster');
?>

<div class="eyebrow">
    NEXUS · ROSTER
</div>

<h1>
    Roster de la hermandad
</h1>

<p class="muted">
    Añade y modifica los personajes que aparecen automáticamente
    en la página pública.
</p>


<?php if ($msg): ?>

    <div class="flash">
        <?= e($msg) ?>
    </div>

<?php endif; ?>


<div class="admin-card">

    <h2>
        <?= $edit
            ? 'Editar personaje'
            : 'Añadir personaje' ?>
    </h2>


    <!--
    IMPORTANTE:
    El action apunta SIEMPRE directamente a roster.php.
    Así nunca arrastramos ?edit=ID al enviar el formulario.
    -->

    <form
        class="admin-form"
        method="post"
        action="/Admin/roster.php"
    >

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="save"
        >


        <?php if ($edit): ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$edit['id'] ?>"
            >

        <?php endif; ?>


        <?php if (!$edit): ?>
        <label>
            Importar personaje de un miembro (opcional)
            <select name="profile_id" id="profile_id">
                <option value="0">— Selecciona un personaje registrado —</option>
                <?php foreach ($memberCharacters as $character): ?>
                    <option value="<?= (int)$character['id'] ?>"
                        data-name="<?= e($character['name']) ?>"
                        data-race="<?= e($character['race']) ?>"
                        data-class="<?= e($character['class_name']) ?>"
                        data-level="<?= e((string)($character['level'] ?? '')) ?>"
                        data-role="DPS"
                        data-discord="<?= e($character['discord_id']) ?>">
                        <?= e($character['name']) ?> · <?= e($character['race']) ?> · <?= e($character['class_name']) ?> · Nv. <?= e((string)($character['level'] ?? '—')) ?> · <?= e($character['discord_username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Al seleccionarlo se rellenan automáticamente nombre, raza, clase, nivel y Discord. El rol y el roster se pueden ajustar debajo.</small>
        </label>
        <?php endif; ?>

        <label>
            Nombre del personaje

            <input
                type="text"
                name="name"
                value="<?= e($edit['name'] ?? '') ?>"
                required
                maxlength="80"
                placeholder="Ej.: Thrallxx"
            >
        </label>

        <label>
            Discord ID del miembro
            <select name="discord_id">
                <option value="">Sin vincular</option>
                <?php foreach ($discordMembers as $member): ?>
                    <option value="<?= e($member['discord_id']) ?>" <?= (($edit['discord_id'] ?? '') === $member['discord_id']) ? 'selected' : '' ?>>
                        <?= e($member['discord_username']) ?> · <?= e($member['discord_id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Enlaza el personaje con su cuenta Discord para que asistencia y RSP se asignen automáticamente.</small>
        </label>


        <label>
            Raza

            <select
                name="race"
                required
            >

                <option value="">
                    Selecciona raza
                </option>

                <?php foreach ($races as $race): ?>

                    <option
                        value="<?= e($race) ?>"
                        <?= (($edit['race'] ?? '') === $race)
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($race) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </label>


        <label>
            Clase

            <select
                name="class_name"
                id="class_name"
                required
            >

                <option value="">
                    Selecciona clase
                </option>

                <?php foreach ($classes as $class => $color): ?>

                    <option
                        value="<?= e($class) ?>"
                        data-color="<?= e($color) ?>"
                        <?= (($edit['class_name'] ?? '') === $class)
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($class) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </label>


        <input
            type="hidden"
            name="class_color"
            id="class_color"
            value="<?= e(
                $edit['class_color']
                ?? '#d8ad55'
            ) ?>"
        >


        <label>
            Tipo de raid / roster
            <select name="roster_group" required>
                <?php $selectedRoster = $edit['roster_group'] ?? 'A'; ?>
                <option value="A" <?= $selectedRoster === 'A' ? 'selected' : '' ?>>Raid 10 · Roster A</option>
                <option value="B" <?= $selectedRoster === 'B' ? 'selected' : '' ?>>Raid 10 · Roster B</option>
                <option value="20" <?= $selectedRoster === '20' ? 'selected' : '' ?>>Raid 20</option>
                <option value="40" <?= $selectedRoster === '40' ? 'selected' : '' ?>>Raid 40</option>
            </select>
            <small class="muted">Selecciona en qué grupo de raid aparecerá este personaje en el roster público.</small>
        </label>

        <label>
            Nivel

            <input
                type="number"
                name="level"
                min="1"
                max="100"
                value="<?= e(
                    isset($edit['level'])
                        ? (string)$edit['level']
                        : ''
                ) ?>"
                placeholder="30"
            >
        </label>


        <label>
            Rol

            <select name="role">

                <option value="">
                    Selecciona rol
                </option>

                <?php foreach (
                    ['Tanque', 'Healer', 'DPS']
                    as $role
                ): ?>

                    <option
                        value="<?= e($role) ?>"
                        <?= (($edit['role'] ?? '') === $role)
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($role) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </label>


        <label>
            Estado

            <select name="status">

                <option
                    value="active"
                    <?= (($edit['status'] ?? 'active') === 'active')
                        ? 'selected'
                        : '' ?>
                >
                    Activo
                </option>

                <option
                    value="inactive"
                    <?= (($edit['status'] ?? '') === 'inactive')
                        ? 'selected'
                        : '' ?>
                >
                    Inactivo
                </option>

            </select>
        </label>


        <label>
            Notas

            <input
                type="text"
                name="notes"
                value="<?= e($edit['notes'] ?? '') ?>"
                maxlength="255"
                placeholder="Opcional"
            >
        </label>


        <div class="admin-actions">

            <button
                class="btn primary"
                type="submit"
            >
                <?= $edit
                    ? 'Guardar cambios'
                    : 'Añadir personaje' ?>
            </button>


            <?php if ($edit): ?>

                <a
                    class="btn ghost"
                    href="/Admin/roster.php"
                >
                    Cancelar edición
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<br>


<div class="admin-card">

    <h2>
        Personajes publicados
        (<?= count($rows) ?>)
    </h2>


    <div style="overflow:auto">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Raza</th>
                    <th>Clase</th>
                    <th>Roster</th>
                    <th>Nivel</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>

            </thead>


            <tbody>

            <?php if (!$rows): ?>

                <tr>

                    <td colspan="9">
                        Todavía no hay personajes.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($rows as $r): ?>

                    <tr>

                        <td>
                            <?= (int)$r['id'] ?>
                        </td>


                        <td>

                            <strong>
                                <?= e($r['name']) ?>
                            </strong>

                        </td>


                        <td>
                            <?= e($r['race']) ?>
                        </td>


                        <td
                            style="color:<?= e(
                                $r['class_color']
                            ) ?>"
                        >
                            <?= e($r['class_name']) ?>
                        </td>


                        <td><strong><?= e(['A'=>'Raid 10 · Roster A','B'=>'Raid 10 · Roster B','20'=>'Raid 20','40'=>'Raid 40'][$r['roster_group'] ?? 'A'] ?? 'Raid 10 · Roster A') ?></strong></td>

                        <td>
                            <?= e(
                                (string)$r['level']
                            ) ?>
                        </td>


                        <td>
                            <?= e($r['role']) ?>
                        </td>


                        <td>

                            <?= $r['status'] === 'active'
                                ? '🟢 Activo'
                                : '⚪ Inactivo' ?>

                        </td>


                        <td>

                            <a
                                href="/Admin/roster.php?edit=<?= (int)$r['id'] ?>"
                            >
                                Editar
                            </a>


                            <form
                                method="post"
                                action="/Admin/roster.php"
                                style="display:inline"
                                onsubmit="return confirm('¿Eliminar personaje?')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$r['id'] ?>"
                                >

                                <button
                                    class="btn danger"
                                    style="padding:5px 9px"
                                    type="submit"
                                >
                                    Eliminar
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<script>
(function () {

    const classSelect =
        document.getElementById('class_name');

    const colorInput =
        document.getElementById('class_color');

    if (!classSelect || !colorInput) {
        return;
    }

    function updateClassColor() {

        const option =
            classSelect.options[
                classSelect.selectedIndex
            ];

        if (
            option &&
            option.dataset &&
            option.dataset.color
        ) {

            colorInput.value =
                option.dataset.color;

        } else {

            colorInput.value =
                '#d8ad55';
        }
    }

    classSelect.addEventListener(
        'change',
        updateClassColor
    );

    updateClassColor();

    const profileSelect = document.getElementById('profile_id');
    if (profileSelect) {
        profileSelect.addEventListener('change', function () {
            const opt = profileSelect.options[profileSelect.selectedIndex];
            if (!opt || !opt.value || opt.value === '0') return;
            const fields = {
                name: opt.dataset.name || '',
                race: opt.dataset.race || '',
                class_name: opt.dataset.class || '',
                level: opt.dataset.level || '',
                role: opt.dataset.role || 'DPS',
                discord_id: opt.dataset.discord || ''
            };
            Object.keys(fields).forEach(function (key) {
                const field = document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = fields[key];
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    }

})();
</script>


<?php
admin_footer();
?>