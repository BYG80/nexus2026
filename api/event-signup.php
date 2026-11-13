<?php

ob_start();

require_once __DIR__ . '/../config/bootstrap.php';

start_secure_session();

/*
 * ============================================================
 * NEXUS - INSCRIPCIÓN A EVENTOS
 * ============================================================
 */

function event_signup_json(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
 * ============================================================
 * CONVERTIR WARNINGS / NOTICES EN EXCEPCIONES
 * ============================================================
 */

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {

        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);


try {

    /*
     * ========================================================
     * SESIÓN DISCORD
     * ========================================================
     */

    $discordId = trim(
        (string)(
            $_SESSION['member_discord_id'] ?? ''
        )
    );

    if ($discordId === '') {

        event_signup_json([
            'error' => 'Debes iniciar sesión con Discord.'
        ], 401);
    }

    /*
     * Los eventos son una función exclusiva de miembros verificados.
     * No usamos is_admin/is_editor ni el Roster: basta con estar
     * autenticado en Discord y tener el rol Miembros.
     */
    try {
        $verifiedMember = require_discord_member();
        $discordId = trim((string)$verifiedMember['discord_id']);
        if ($discordId === '') {
            event_signup_json([
                'error' => 'No se pudo verificar tu cuenta de Discord como miembro de NEXUS.'
            ], 403);
        }
    } catch (Throwable $memberError) {
        event_signup_json([
            'error' => $memberError->getMessage() ?: 'No tienes permisos para usar los eventos. Necesitas el rol Miembros.'
        ], 403);
    }


    /*
     * ========================================================
     * MÉTODO HTTP
     * ========================================================
     */

    $method = strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );

    if (!in_array(
        $method,
        ['GET', 'POST', 'DELETE'],
        true
    )) {

        event_signup_json([
            'error' => 'Método no permitido.'
        ], 405);
    }


    if ($method !== 'GET' && !csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null)) {
        event_signup_json(['error' => 'Recarga la página: token de sesión no válido.'], 403);
    }

    /*
     * ========================================================
     * ID DEL EVENTO
     * ========================================================
     */

    $eventId = 0;

    if ($method === 'GET') {

        $eventId = (int)(
            $_GET['event_id'] ?? 0
        );

    } elseif ($method === 'POST') {

        $eventId = (int)(
            $_POST['event_id']
            ?? $_GET['event_id']
            ?? 0
        );

    } elseif ($method === 'DELETE') {

        $eventId = (int)(
            $_GET['event_id'] ?? 0
        );

        if ($eventId <= 0) {

            $raw = file_get_contents(
                'php://input'
            );

            if (
                $raw !== false &&
                trim($raw) !== ''
            ) {

                $json = json_decode(
                    $raw,
                    true
                );

                if (is_array($json)) {

                    $eventId = (int)(
                        $json['event_id'] ?? 0
                    );
                }
            }
        }
    }


    if ($eventId <= 0) {

        event_signup_json([
            'error' => 'Evento no válido.'
        ], 400);
    }


    /*
     * ========================================================
     * BASE DE DATOS
     * ========================================================
     */

    $pdo = db();

    /* Guardamos también el personaje seleccionado para identificar
     * exactamente con qué personaje se ha inscrito el miembro. */
    try {
        $checkCharacterId = $pdo->query("SHOW COLUMNS FROM `event_attendees` LIKE 'character_id'");
        if (!$checkCharacterId->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `event_attendees` ADD COLUMN `character_id` INT UNSIGNED NULL AFTER `member_discord_id`");
        }
    } catch (Throwable $ignore) {
        // Si la columna ya existe o el servidor no permite ALTER,
        // continuamos; la inscripción seguirá usando los datos del personaje.
    }


    /*
     * ========================================================
     * COMPROBAR EVENTO
     * ========================================================
     */

    $eventStmt = $pdo->prepare(
        'SELECT
            id,
            title,
            event_date,
            event_time,
            type,
            description
         FROM events
         WHERE id = ?
         LIMIT 1'
    );

    $eventStmt->execute([
        $eventId
    ]);

    $event = $eventStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$event) {

        event_signup_json([
            'error' => 'El evento no existe.'
        ], 404);
    }


    /*
     * ========================================================
     * NOMBRE DEL USUARIO
     * ========================================================
     */

    $username = trim(
        (string)(
            $verifiedMember['username']
            ?? $_SESSION['member_username']
            ?? $_SESSION['username']
            ?? $_SESSION['discord_username']
            ?? 'Jugador'
        )
    );


    try {

        $memberStmt = $pdo->prepare(
            'SELECT discord_username
             FROM members
             WHERE discord_id = ?
             LIMIT 1'
        );

        $memberStmt->execute([
            $discordId
        ]);

        $member = $memberStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (
            $member &&
            !empty($member['discord_username'])
        ) {

            $username = (string)$member['discord_username'];
        }

    } catch (Throwable $ignored) {
        // Usamos el nombre de sesión.
    }


    /*
     * ========================================================
     * COMPROBAR ROLE
     * ========================================================
     */

    $hasRoleColumn = false;

    try {

        if (function_exists('nexus_column_exists')) {

            $hasRoleColumn = nexus_column_exists(
                $pdo,
                'event_attendees',
                'role'
            );

        } else {

            $check = $pdo->query(
                "SHOW COLUMNS FROM `event_attendees`
                 LIKE 'role'"
            );

            $hasRoleColumn = (bool)$check->fetch(
                PDO::FETCH_ASSOC
            );
        }

    } catch (Throwable $e) {

        $hasRoleColumn = false;
    }


    if (!$hasRoleColumn) {

        try {

            $pdo->exec(
                "ALTER TABLE `event_attendees`
                 ADD COLUMN `role` VARCHAR(20) NULL
                 AFTER `class_name`"
            );

            $hasRoleColumn = true;

        } catch (Throwable $e) {

            event_signup_json([
                'error' =>
                    'No se ha podido crear la columna role en event_attendees.',
                'detalle' => 'No se pudo preparar el campo de rol para las inscripciones.'
            ], 500);
        }
    }


    /*
     * ========================================================
     * GET
     * ========================================================
     */

    if ($method === 'GET') {

        $stmt = $pdo->prepare(
            'SELECT
                character_id,
                race,
                class_name,
                `role`,
                registered_via_web
             FROM event_attendees
             WHERE event_id = ?
               AND member_discord_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $eventId,
            $discordId
        ]);

        $registration = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM event_attendees
             WHERE event_id = ?'
        );

        $countStmt->execute([
            $eventId
        ]);

        $count = (int)$countStmt->fetchColumn();


        event_signup_json([
            'registered' =>
                $registration && (int)$registration['registered_via_web'] === 1,

            'count' =>
                $count,

            'character_id' =>
                $registration['character_id'] ?? '',

            'race' =>
                $registration['race'] ?? '',

            'class_name' =>
                $registration['class_name'] ?? '',

            'role' =>
                $registration['role'] ?? ''
        ]);
    }


    /*
     * ========================================================
     * POST
     * ========================================================
     */

    if ($method === 'POST') {

        $characterId = (int)($_POST['character_id'] ?? 0);

        if ($characterId <= 0) {
            event_signup_json([
                'error' => 'Debes seleccionar uno de tus personajes.'
            ], 400);
        }

        /* El personaje debe pertenecer a la cuenta autenticada y a la
         * facción activa. Nunca aceptamos raza/clase/rol enviados por el
         * navegador: los obtenemos directamente de MI PANEL. */
        $characterStmt = $pdo->prepare(
            'SELECT id, name, race, class_name, `role`
             FROM member_profiles
             WHERE id = ?
               AND discord_id = ?
               AND faction = ?
             LIMIT 1'
        );
        $characterStmt->execute([
            $characterId,
            $discordId,
            nexus_faction()
        ]);
        $character = $characterStmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            event_signup_json([
                'error' => 'El personaje seleccionado no pertenece a tu cuenta o ya no está disponible.'
            ], 403);
        }

        $race = trim((string)($character['race'] ?? ''));
        $className = trim((string)($character['class_name'] ?? ''));
        $role = trim((string)($character['role'] ?? ''));

        if ($race === '' || $className === '' || $role === '') {
            event_signup_json([
                'error' => 'El personaje no tiene completos los datos de raza, clase y rol en MI PANEL.'
            ], 400);
        }

        /*
         * ====================================================
         * RAZAS
         * ====================================================
         */

        $raceClasses = nexus_wow_races();
        $allowedRaces = array_keys($raceClasses);


        /*
         * ====================================================
         * CLASES
         * ====================================================
         */

$allowedClasses = nexus_wow_classes();


        /*
         * ====================================================
         * ROLES
         * ====================================================
         */

        $classRoles = [

            'Guerrero' => [
                'Tanque',
                'DPS'
            ],

            'Cazador' => [
                'DPS'
            ],

            'Pícaro' => [
                'DPS'
            ],

            'Sacerdote' => [
                'DPS',
                'Healer'
            ],

            'Chamán' => [
                'DPS',
                'Healer'
            ],

            'Mago' => [
                'DPS'
            ],

            'Brujo' => [
                'DPS'
            ],

            'Druida' => [
                'Tanque',
                'DPS',
                'Healer'
            ],

            'Paladín' => [
                'Tanque',
                'DPS',
                'Healer'
            ]
        ];


        if (!in_array(
            $race,
            $allowedRaces,
            true
        )) {

            event_signup_json([
                'error' => 'Raza no válida.'
            ], 400);
        }


        if (!in_array(
            $className,
            $allowedClasses,
            true
        )) {

            event_signup_json([
                'error' => 'Clase no válida.'
            ], 400);
        }

        if (!isset($raceClasses[$race]) || !in_array($className, $raceClasses[$race], true)) {
            event_signup_json([
                'error' => 'Esa clase no está disponible para la raza seleccionada.'
            ], 400);
        }


        if (!isset(
            $classRoles[$className]
        )) {

            event_signup_json([
                'error' =>
                    'No existen roles configurados para esta clase.'
            ], 400);
        }


        if (!in_array(
            $role,
            $classRoles[$className],
            true
        )) {

            event_signup_json([
                'error' =>
                    'El rol seleccionado no es válido para esa clase.'
            ], 400);
        }


        /*
         * ====================================================
         * BUSCAR INSCRIPCIÓN DEL MISMO USUARIO
         * ====================================================
         *
         * IMPORTANTE:
         * NO comprobamos la clase.
         *
         * Por tanto:
         *
         * Usuario A -> Guerrero
         * Usuario B -> Guerrero
         * Usuario C -> Guerrero
         *
         * están permitidos.
         * ====================================================
         */

        $find = $pdo->prepare(
            'SELECT id
             FROM event_attendees
             WHERE event_id = ?
               AND member_discord_id = ?
             LIMIT 1'
        );

        $find->execute([
            $eventId,
            $discordId
        ]);

        $existing = $find->fetch(
            PDO::FETCH_ASSOC
        );


        /*
         * ====================================================
         * ACTUALIZAR
         * ====================================================
         */

        if ($existing) {

            $update = $pdo->prepare(
                'UPDATE event_attendees
                 SET
                    member_username = ?,
                    character_id = ?,
                    race = ?,
                    faction = ?,
                    class_name = ?,
                    `role` = ?,
                    registered_via_web = 1
                 WHERE id = ?'
            );

            $update->execute([
                $username,
                $characterId,
                $race,
                nexus_faction(),
                $className,
                $role,
                (int)$existing['id']
            ]);

        }


        /*
         * ====================================================
         * INSERTAR
         * ====================================================
         */

        else {

            $insert = $pdo->prepare(
                'INSERT INTO event_attendees
                (
                    event_id,
                    member_discord_id,
                    character_id,
                    member_username,
                    race,
                    faction,
                    class_name,
                    `role`,
                    registered_via_web
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );

            $insert->execute([
                $eventId,
                $discordId,
                $characterId,
                $username,
                $race,
                nexus_faction(),
                $className,
                $role
            ]);
        }


        /*
         * ====================================================
         * CONTADOR
         * ====================================================
         */

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM event_attendees
             WHERE event_id = ?'
        );

        $countStmt->execute([
            $eventId
        ]);

        $count = (int)$countStmt->fetchColumn();


        /*
         * ====================================================
         * SINCRONIZACIÓN WEB -> DISCORD
         * ====================================================
         *
         * IMPORTANTE:
         * La función necesita $pdo + $eventId.
         *
         * Antes se llamaba únicamente con $eventId,
         * provocando un error que después se ocultaba.
         * ====================================================
         */

        $discordSync = [
            'ok' => false,
            'error' => null
        ];

        try {

            if (function_exists(
                'sync_web_event_to_discord'
            )) {

                sync_web_event_to_discord(
                    $pdo,
                    $eventId
                );

                $discordSync['ok'] = true;


            } elseif (function_exists(
                'sync_discord_event'
            )) {

                sync_discord_event(
                    $pdo,
                    $eventId,
                    true
                );

                $discordSync['ok'] = true;

            } else {

                $discordSync['error'] =
                    'No existe ninguna función de sincronización de Discord.';
            }

        } catch (Throwable $discordError) {

            /*
             * La inscripción permanece guardada aunque Discord falle.
             */
            $discordSync['error'] =
                $discordError->getMessage();
            try { $pdo->prepare('UPDATE events SET discord_sync_error = ? WHERE id = ?')->execute([mb_substr($discordSync['error'], 0, 500), $eventId]); } catch (Throwable $ignore) {}
        }


        /*
         * ====================================================
         * RESPUESTA
         * ====================================================
         */

        event_signup_json([
            'success' =>
                true,

            'registered' =>
                true,

            'count' =>
                $count,

            'character_id' =>
                $characterId,

            'character_name' =>
                $character['name'],

            'race' =>
                $race,

            'class_name' =>
                $className,

            'role' =>
                $role,

            'discord_sync' =>
                $discordSync
        ]);
    }


    /*
     * ========================================================
     * DELETE
     * ========================================================
     */

    if ($method === 'DELETE') {
        // Retirar la inscripción web no retira el interés ni la asistencia registrada en Discord.
        $pdo->prepare('UPDATE event_attendees SET registered_via_web=0 WHERE event_id=? AND member_discord_id=?')->execute([$eventId,$discordId]);
        $pdo->prepare('DELETE FROM event_attendees WHERE event_id=? AND member_discord_id=? AND discord_interested=0 AND attended IS NULL')->execute([$eventId,$discordId]);

        /*
         * ====================================================
         * SINCRONIZACIÓN WEB -> DISCORD
         * ====================================================
         */

        $discordSync = [
            'ok' => false,
            'error' => null
        ];

        try {

            if (function_exists(
                'sync_web_event_to_discord'
            )) {

                sync_web_event_to_discord(
                    $pdo,
                    $eventId
                );

                $discordSync['ok'] = true;

            } elseif (function_exists(
                'sync_discord_event'
            )) {

                sync_discord_event(
                    $pdo,
                    $eventId,
                    true
                );

                $discordSync['ok'] = true;

            } else {

                $discordSync['error'] =
                    'No existe ninguna función de sincronización de Discord.';
            }

        } catch (Throwable $discordError) {

            $discordSync['error'] =
                $discordError->getMessage();
            try {
                $pdo->prepare('UPDATE events SET discord_sync_error = ? WHERE id = ?')
                    ->execute([mb_substr($discordSync['error'], 0, 500), $eventId]);
            } catch (Throwable $ignore) {}
        }


        /*
         * ====================================================
         * CONTADOR
         * ====================================================
         */

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM event_attendees
             WHERE event_id = ?'
        );

        $countStmt->execute([
            $eventId
        ]);

        $count = (int)$countStmt->fetchColumn();


        event_signup_json([
            'success' =>
                true,

            'registered' =>
                false,

            'count' =>
                $count,

            'race' =>
                '',

            'class_name' =>
                '',

            'role' =>
                '',

            'discord_sync' =>
                $discordSync
        ]);
    }


    event_signup_json([
        'error' =>
            'Solicitud no válida.'
    ], 400);


} catch (Throwable $e) {
    error_log('NEXUS inscripción: error interno al procesar la solicitud.');
    event_signup_json(['error' => 'No se pudo completar la inscripción. Inténtalo de nuevo.'], 500);
}
