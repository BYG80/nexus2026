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
            nexus_validate_event_input($date, $time, $title, $type, $description);
            $values = [$date, $time ?: null, $title, $type, $description];
            if ($id > 0) {
                $pdo->prepare('UPDATE events SET event_date=?, event_time=?, title=?, type=?, description=? WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO events (event_date,event_time,title,type,description) VALUES (?,?,?,?,?)')->execute($values);
                $id = (int)$pdo->lastInsertId();
            }
            $sync = nexus_publish_event($pdo, $id);
            $_SESSION['events_flash'] = $sync['ok'] ? 'Evento guardado y publicado en Eventos de Discord y en el canal de eventos.' : 'Evento guardado en la web. Pendiente de Discord: ' . $sync['error'];
            $_SESSION['events_flash_error'] = !$sync['ok'];
            header('Location: /Admin/events.php?saved=1');
            exit;
        }

        elseif ($action === 'delete') {
            check_csrf($_POST['csrf'] ?? null);
            $id=(int)($_POST['id']??0);
            if($id<1)throw new InvalidArgumentException('Evento no válido.');
            nexus_delete_web_event($pdo,$id);
            header('Location: /Admin/events.php?deleted=1');
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
    $msg = (string)($_SESSION['events_flash'] ?? 'Evento guardado correctamente.');
    $msgType = !empty($_SESSION['events_flash_error']) ? 'error-box' : 'flash';
    unset($_SESSION['events_flash'], $_SESSION['events_flash_error']);
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


// La actualización de interesados es explícita o mediante el script cron CLI.

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


        <p class="small">Al guardar se sincronizan el evento programado de Discord y el aviso del canal. Si falta configuración o permisos, verás el error y podrás reintentar sin volver a crear el evento.</p>


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
                        <?php if (!empty($event['discord_sync_error'])): ?><div class="error-box small"><?=e($event['discord_sync_error'])?></div><?php endif; ?>


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
<button
                                type="button"
                                class="btn ghost sync-event"
                                data-event="<?= e(
                                    (string)$event['id']
                                ) ?>"
                            >
                                Sincronizar
                            </button>



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
const NEXUS_EVENTS_CSRF = <?=json_encode(csrf_token())?>;

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
                        fd.set('csrf', NEXUS_EVENTS_CSRF);


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
                        fd.set('csrf', NEXUS_EVENTS_CSRF);


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