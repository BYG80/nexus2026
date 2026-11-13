<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

require_admin();

$pdo = db();

$msg = '';
$msgType = 'flash';
$edit = null;


/*
|--------------------------------------------------------------------------
| ACCIONES POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    try {

        /*
        |--------------------------------------------------------------------------
        | GUARDAR / CREAR EVENTO
        |--------------------------------------------------------------------------
        */

        if ($action === 'save') {

            check_csrf($_POST['csrf'] ?? null);

            $id = (int)($_POST['id'] ?? 0);

            $date = trim((string)($_POST['date'] ?? ''));
            $time = trim((string)($_POST['time'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $type = trim((string)($_POST['type'] ?? 'Evento'));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($date === '') {
                throw new RuntimeException(
                    'La fecha es obligatoria.'
                );
            }

            if ($title === '') {
                throw new RuntimeException(
                    'El título del evento es obligatorio.'
                );
            }

            $timeValue = $time !== ''
                ? $time
                : null;


            /*
            |--------------------------------------------------------------------------
            | EDITAR
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare(
                    'UPDATE events
                     SET
                        event_date = ?,
                        event_time = ?,
                        title = ?,
                        type = ?,
                        description = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $date,
                    $timeValue,
                    $title,
                    $type,
                    $description,
                    $id
                ]);

                $msg =
                    'Evento actualizado correctamente.';

            }


            /*
            |--------------------------------------------------------------------------
            | CREAR
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare(
                    'INSERT INTO events
                    (
                        event_date,
                        event_time,
                        title,
                        type,
                        description
                    )
                    VALUES (?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $date,
                    $timeValue,
                    $title,
                    $type,
                    $description
                ]);

                $id = (int)$pdo->lastInsertId();

                if ($id <= 0) {
                    throw new RuntimeException(
                        'El evento aparentemente se guardó, pero MySQL no devolvió su ID.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CREAR EVENTO EN DISCORD
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($_POST['discord_event']) &&
                    function_exists('discord_create_event') &&
                    app_config('discord_bot_token') &&
                    app_config('discord_guild_id') &&
                    app_config('discord_voice_channel_id')
                ) {

                    try {

                        $discordData =
                            discord_create_event([
                                'date' =>
                                    $date,

                                'time' =>
                                    $timeValue,

                                'title' =>
                                    $title,

                                'type' =>
                                    $type,

                                'description' =>
                                    $description
                            ]);


                        if (
                            is_array($discordData) &&
                            !empty($discordData['id'])
                        ) {

                            $discordId =
                                (string)$discordData['id'];

                            $discordUrl =
                                'https://discord.com/events/' .
                                app_config('discord_guild_id') .
                                '/' .
                                $discordId;


                            $updateDiscord =
                                $pdo->prepare(
                                    'UPDATE events
                                     SET
                                        discord_event_id = ?,
                                        discord_event_url = ?
                                     WHERE id = ?'
                                );

                            $updateDiscord->execute([
                                $discordId,
                                $discordUrl,
                                $id
                            ]);


                            $msg =
                                'Evento creado correctamente y vinculado con Discord.';

                        } else {

                            $msg =
                                'Evento creado correctamente, pero Discord no devolvió un ID de evento.';
                        }

                    } catch (Throwable $discordError) {

                        $msg =
                            'Evento creado correctamente, pero no se pudo crear en Discord: ' .
                            $discordError->getMessage();

                        $msgType =
                            'error-box';
                    }

                } else {

                    $msg =
                        'Evento creado correctamente.';
                }
            }


            if ($msgType === 'flash') {

                header(
                    'Location: /Admin/events.php?saved=1'
                );

                exit;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | ELIMINAR EVENTO
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            check_csrf($_POST['csrf'] ?? null);

            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(
                    'ID de evento no válido.'
                );
            }


            $find = $pdo->prepare(
                'SELECT
                    id,
                    title,
                    discord_event_id
                 FROM events
                 WHERE id = ?
                 LIMIT 1'
            );

            $find->execute([
                $id
            ]);

            $event = $find->fetch();

            if (!$event) {
                throw new RuntimeException(
                    'El evento no existe.'
                );
            }


            $pdo->beginTransaction();

            try {

                $deleteAttendees =
                    $pdo->prepare(
                        'DELETE FROM event_attendees
                         WHERE event_id = ?'
                    );

                $deleteAttendees->execute([
                    $id
                ]);


                $deleteEvent =
                    $pdo->prepare(
                        'DELETE FROM events
                         WHERE id = ?'
                    );

                $deleteEvent->execute([
                    $id
                ]);


                if ($deleteEvent->rowCount() !== 1) {

                    throw new RuntimeException(
                        'MySQL no eliminó el evento.'
                    );
                }


                $pdo->commit();

            } catch (Throwable $deleteError) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $deleteError;
            }


            if (
                !empty($event['discord_event_id']) &&
                function_exists('discord_delete_event')
            ) {

                try {

                    discord_delete_event(
                        (string)$event['discord_event_id']
                    );

                } catch (Throwable $discordDeleteError) {
                    // El borrado web ya se ha realizado.
                }
            }


            header(
                'Location: /Admin/events.php?deleted=1'
            );

            exit;
        }

    } catch (Throwable $e) {

        $msg =
            'ERROR: ' . $e->getMessage();

        $msgType =
            'error-box';
    }
}


/*
|--------------------------------------------------------------------------
| MENSAJES
|--------------------------------------------------------------------------
*/

if (isset($_GET['saved'])) {

    $msg =
        'Evento guardado correctamente.';

    $msgType =
        'flash';
}

if (isset($_GET['deleted'])) {

    $msg =
        'Evento eliminado correctamente.';

    $msgType =
        'flash';
}


/*
|--------------------------------------------------------------------------
| EDITAR EVENTO
|--------------------------------------------------------------------------
*/

if (isset($_GET['edit'])) {

    $editId =
        (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt =
            $pdo->prepare(
                'SELECT
                    id,
                    event_date AS date,
                    event_time AS time,
                    title,
                    type,
                    description
                 FROM events
                 WHERE id = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $editId
        ]);

        $edit =
            $stmt->fetch();

        if (!$edit) {

            $msg =
                'El evento solicitado no existe.';

            $msgType =
                'error-box';
        }
    }
}


/*
|--------------------------------------------------------------------------
| SINCRONIZACIÓN AUTOMÁTICA DE DISCORD
|--------------------------------------------------------------------------
*/

try {

    $past = $pdo->query(
        "SELECT
            id,
            event_date,
            event_time,
            discord_event_id,
            discord_last_synced_at
         FROM events
         WHERE discord_event_id IS NOT NULL
           AND event_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)"
    )->fetchAll();


    $now =
        new DateTimeImmutable(
            'now',
            app_timezone()
        );


    foreach ($past as $pe) {

        if (empty($pe['discord_event_id'])) {
            continue;
        }

        try {

            $start =
                event_start_utc($pe);


            $last =
                !empty($pe['discord_last_synced_at'])
                    ? new DateTimeImmutable(
                        $pe['discord_last_synced_at']
                    )
                    : null;


            if (
                $start->modify('+2 hours')->getTimestamp()
                <= $now->getTimestamp()
                &&
                (
                    !$last ||
                    $now->getTimestamp()
                    - $last->getTimestamp()
                    >= 600
                )
            ) {

                if (
                    function_exists(
                        'sync_discord_event'
                    )
                ) {

                    sync_discord_event(
                        $pdo,
                        (int)$pe['id'],
                        false
                    );
                }
            }

        } catch (Throwable $ignored) {
            // No romper el panel.
        }
    }

} catch (Throwable $ignored) {
    // Discord no debe dejar el panel en blanco.
}


/*
|--------------------------------------------------------------------------
| LISTADO DE EVENTOS
|--------------------------------------------------------------------------
*/

$events = $pdo->query(
    "SELECT
        e.id,
        e.event_date AS date,
        e.event_time AS time,
        e.title,
        e.type,
        e.description,
        e.discord_event_url,
        e.discord_event_id,
        e.discord_last_synced_at,
        e.discord_sync_error,

        (
            SELECT COUNT(*)
            FROM event_attendees a
            WHERE a.event_id = e.id
        ) AS attendee_count,

        (
            SELECT COUNT(*)
            FROM event_attendees a
            WHERE a.event_id = e.id
              AND a.discord_interested = 1
        ) AS discord_count,

        (
            SELECT COUNT(*)
            FROM event_attendees a
            WHERE a.event_id = e.id
              AND a.attended = 1
        ) AS attended_count

     FROM events e

     ORDER BY
        e.event_date DESC,
        e.event_time DESC,
        e.id DESC"
)->fetchAll();


admin_header(
    'Eventos',
    'events'
);

?>

<div class="eyebrow">
    NEXUS · CALENDARIO
</div>

<h1>
    Eventos
</h1>


<?php if ($msg !== ''): ?>

    <div class="<?= e($msgType) ?>">
        <?= e($msg) ?>
    </div>

<?php endif; ?>


<!-- ============================================================
     FORMULARIO
============================================================ -->

<div class="admin-card">

    <h2>
        <?= $edit
            ? 'Editar evento'
            : 'Nuevo evento' ?>
    </h2>


    <form
        class="admin-form"
        method="post"
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

        <input
            type="hidden"
            name="id"
            value="<?= e(
                (string)($edit['id'] ?? 0)
            ) ?>"
        >


        <label>
            Fecha

            <input
                type="date"
                name="date"
                value="<?= e(
                    $edit['date']
                    ?? date('Y-m-d')
                ) ?>"
                required
            >
        </label>


        <label>
            Hora

            <input
                type="time"
                name="time"
                value="<?= e(
                    substr(
                        (string)(
                            $edit['time'] ?? ''
                        ),
                        0,
                        5
                    )
                ) ?>"
            >
        </label>


        <label>
            Título

            <input
                type="text"
                name="title"
                value="<?= e(
                    $edit['title'] ?? ''
                ) ?>"
                required
            >
        </label>


        <label>
            Tipo

            <input
                type="text"
                name="type"
                value="<?= e(
                    $edit['type']
                    ?? 'Evento'
                ) ?>"
            >
        </label>


        <label>
            Descripción

            <textarea
                name="description"
            ><?= e(
                $edit['description'] ?? ''
            ) ?></textarea>
        </label>


        <?php if (!$edit): ?>

            <label
                style="
                    display:flex;
                    align-items:center;
                    gap:8px;
                "
            >

                <input
                    type="checkbox"
                    name="discord_event"
                    value="1"
                    style="width:auto"
                >

                Crear también un evento programado en Discord

            </label>

        <?php endif; ?>


        <div class="admin-actions">

            <button
                class="btn primary"
                type="submit"
            >
                <?= $edit
                    ? 'Guardar cambios'
                    : 'Crear evento' ?>
            </button>


            <?php if ($edit): ?>

                <a
                    class="btn ghost"
                    href="/Admin/events.php"
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<br>


<!-- ============================================================
     LISTADO
============================================================ -->

<div class="admin-card">

    <h2>
        Eventos existentes
    </h2>

    <p class="muted">
        Consulta los participantes de cada evento,
        sus clases y roles, la sincronización con Discord
        y la asistencia.
    </p>


    <div style="overflow:auto">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Inscritos</th>
                    <th>Discord</th>
                    <th>Asistencia</th>
                    <th>Acciones</th>
                </tr>

            </thead>


            <tbody>

            <?php foreach ($events as $event): ?>

                <tr>

                    <td>

                        <?= e(
                            $event['date']
                        ) ?>

                        <?php if (!empty($event['time'])): ?>

                            <?= e(
                                substr(
                                    (string)$event['time'],
                                    0,
                                    5
                                )
                            ) ?>

                        <?php endif; ?>

                    </td>


                    <td>

                        <strong>
                            <?= e(
                                $event['title']
                            ) ?>
                        </strong>

                        <br>

                        <small>
                            <?= e(
                                $event['type']
                            ) ?>
                        </small>

                    </td>


                    <td>
                        <?= e(
                            (string)$event['attendee_count']
                        ) ?>
                    </td>


                    <td>

                        <?= e(
                            (string)$event['discord_count']
                        ) ?>


                        <?php if (
                            !empty(
                                $event['discord_event_url']
                            )
                        ): ?>

                            ·

                            <a
                                href="<?= e(
                                    $event['discord_event_url']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Discord
                            </a>

                        <?php endif; ?>

                    </td>


                    <td>
                        <?= e(
                            (string)$event['attended_count']
                        ) ?>
                    </td>


                    <td>

                        <button
                            type="button"
                            class="btn ghost attendee-view"
                            data-event="<?= e(
                                (string)$event['id']
                            ) ?>"
                        >
                            Ver
                        </button>


                        <?php if (
                            !empty(
                                $event['discord_event_id']
                            )
                        ): ?>

                            <button
                                type="button"
                                class="btn ghost sync-event"
                                data-event="<?= e(
                                    (string)$event['id']
                                ) ?>"
                            >
                                Sincronizar
                            </button>

                        <?php endif; ?>


                        <a
                            href="?edit=<?= e(
                                (string)$event['id']
                            ) ?>"
                        >
                            Editar
                        </a>


                        <form
                            method="post"
                            style="display:inline"
                            onsubmit="return confirm('¿Eliminar este evento y todas sus inscripciones?')"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e(
                                    csrf_token()
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= e(
                                    (string)$event['id']
                                ) ?>"
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


            <?php if (!$events): ?>

                <tr>

                    <td colspan="6">
                        No hay eventos creados.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ============================================================
     PARTICIPANTES
============================================================ -->

<div
    id="attendeePanel"
    class="admin-card"
    style="
        display:none;
        margin-top:18px
    "
>

    <h2>
        Participantes
    </h2>


    <div
        id="attendeeContent"
        class="muted"
    >
        Selecciona un evento.
    </div>

</div>


<script>

const escA = s =>
    String(s ?? '').replace(
        /[&<>"']/g,
        c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[c])
    );


/*
|--------------------------------------------------------------------------
| PARTICIPANTES
|--------------------------------------------------------------------------
*/

async function loadAttendees(id) {

    const panel =
        document.getElementById(
            'attendeePanel'
        );

    const content =
        document.getElementById(
            'attendeeContent'
        );


    panel.style.display =
        'block';

    content.textContent =
        'Cargando…';


    try {

        const response =
            await fetch(
                '/api/event-attendees.php?event_id=' +
                encodeURIComponent(id),
                {
                    cache: 'no-store'
                }
            );


        const data =
            await response.json();


        if (!response.ok) {

            throw new Error(
                data.error ||
                'Error al cargar participantes.'
            );
        }


        if (
            !Array.isArray(
                data.attendees
            )
        ) {

            throw new Error(
                'Respuesta incorrecta del servidor.'
            );
        }


        if (
            !data.attendees.length
        ) {

            content.innerHTML =
                '<p>No hay participantes todavía.</p>';

            return;
        }


        content.innerHTML =

            '<div style="overflow:auto">' +

            '<table class="admin-table">' +

            '<thead>' +

            '<tr>' +

            '<th>Discord</th>' +

            '<th>Raza</th>' +

            '<th>Clase</th>' +

            '<th>Rol</th>' +

            '<th>Web</th>' +

            '<th>Discord</th>' +

            '<th>Asistencia</th>' +

            '<th></th>' +

            '</tr>' +

            '</thead>' +

            '<tbody>' +

            data.attendees.map(a =>

                '<tr>' +

                '<td>' +
                escA(
                    a.member_username
                ) +
                '</td>' +

                '<td>' +
                escA(
                    a.race || '—'
                ) +
                '</td>' +

                '<td>' +
                escA(
                    a.class_name || '—'
                ) +
                '</td>' +

                '<td><strong>' +
                escA(
                    a.role || '—'
                ) +
                '</strong></td>' +

                '<td>' +
                (
                    Number(
                        a.registered_via_web
                    ) === 1
                        ? '✓'
                        : '—'
                ) +
                '</td>' +

                '<td>' +
                (
                    Number(
                        a.discord_interested
                    ) === 1
                        ? '✓'
                        : '—'
                ) +
                '</td>' +

                '<td>' +
                (
                    Number(
                        a.attended
                    ) === 1
                        ? '✓'
                        : Number(
                            a.attended
                        ) === 0
                            ? '✗'
                            : '—'
                ) +
                '</td>' +

                '<td>' +

                '<button ' +
                'type="button" ' +
                'class="btn ghost mark-att" ' +
                'data-event="' +
                escA(id) +
                '" ' +
                'data-discord="' +
                escA(
                    a.member_discord_id
                ) +
                '" ' +
                'data-value="1">' +
                'Asistió' +
                '</button> ' +

                '<button ' +
                'type="button" ' +
                'class="btn danger mark-att" ' +
                'data-event="' +
                escA(id) +
                '" ' +
                'data-discord="' +
                escA(
                    a.member_discord_id
                ) +
                '" ' +
                'data-value="0">' +
                'No' +
                '</button>' +

                '</td>' +

                '</tr>'

            ).join('') +

            '</tbody>' +

            '</table>' +

            '</div>';


        content
            .querySelectorAll(
                '.mark-att'
            )
            .forEach(button => {

                button.addEventListener(
                    'click',
                    async () => {

                        const fd =
                            new FormData();


                        fd.append(
                            'event_id',
                            button.dataset.event
                        );


                        fd.append(
                            'discord_id',
                            button.dataset.discord
                        );


                        fd.append(
                            'attended',
                            button.dataset.value
                        );


                        button.disabled =
                            true;


                        try {

                            const response =
                                await fetch(
                                    '/api/event-attendance.php',
                                    {
                                        method: 'POST',
                                        body: fd
                                    }
                                );


                            const data =
                                await response.json();


                            if (!response.ok) {

                                throw new Error(
                                    data.error ||
                                    'Error al guardar asistencia.'
                                );
                            }


                            await loadAttendees(
                                id
                            );


                        } catch (error) {

                            alert(
                                error.message
                            );

                            button.disabled =
                                false;
                        }

                    }
                );

            });


    } catch (error) {

        content.textContent =
            error.message;
    }
}


document
    .querySelectorAll(
        '.attendee-view'
    )
    .forEach(button => {

        button.addEventListener(
            'click',
            () => {

                loadAttendees(
                    button.dataset.event
                );

            }
        );

    });


/*
|--------------------------------------------------------------------------
| SINCRONIZAR DISCORD
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        '.sync-event'
    )
    .forEach(button => {

        button.addEventListener(
            'click',
            async () => {

                button.disabled =
                    true;

                const originalText =
                    button.textContent;

                button.textContent =
                    'Sincronizando…';


                try {

                    const fd =
                        new FormData();


                    fd.append(
                        'event_id',
                        button.dataset.event
                    );


                    const response =
                        await fetch(
                            '/api/event-sync.php',
                            {
                                method: 'POST',
                                body: fd
                            }
                        );


                    const data =
                        await response.json();


                    if (!response.ok) {

                        throw new Error(
                            data.error ||
                            'Error al sincronizar.'
                        );
                    }


                    alert(
                        (
                            data.message ||
                            'Sincronización completada.'
                        ) +
                        ' Usuarios detectados: ' +
                        (
                            data.synced ??
                            0
                        )
                    );


                    location.reload();


                } catch (error) {

                    alert(
                        error.message
                    );

                    button.disabled =
                        false;

                    button.textContent =
                        originalText;
                }

            }
        );

    });

</script>


<?php

admin_footer();
?>