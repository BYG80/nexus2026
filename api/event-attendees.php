<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

start_secure_session();

if (empty($_SESSION['admin_user'])) {
    json_response([
        'error' => 'No autorizado'
    ], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response([
        'error' => 'Método no permitido'
    ], 405);
}

$eventId = (int)(
    $_GET['event_id'] ?? 0
);

if ($eventId < 1) {
    json_response([
        'error' => 'Evento no válido'
    ], 400);
}

try {

    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT
            id,
            member_discord_id,
            member_username,
            race,
            class_name,
            `role`,
            registered_via_web,
            discord_interested,
            discord_interested_at,
            attended,
            attendance_source,
            attendance_at,
            synced_at,
            created_at

         FROM event_attendees

         WHERE event_id = ?

         ORDER BY
            created_at ASC,
            id ASC'
    );

    $stmt->execute([
        $eventId
    ]);

    $attendees = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    json_response([
        'ok' => true,
        'event_id' => $eventId,
        'count' => count($attendees),
        'attendees' => $attendees
    ]);

} catch (Throwable $e) {

    json_response([
        'error' =>
            'Error al cargar participantes: ' .
            $e->getMessage()
    ], 500);
}