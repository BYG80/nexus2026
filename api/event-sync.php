<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
start_secure_session();
if (empty($_SESSION['admin_user'])) json_response(['error' => 'No autorizado'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(['error' => 'Método no permitido'], 405);
if (!csrf_check($_POST['csrf'] ?? null)) json_response(['error' => 'Recarga la página: token de sesión no válido.'], 403);
$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId < 1) json_response(['error' => 'Evento no válido'], 400);
try {
    $pdo = db();
    $publication = nexus_publish_event($pdo, $eventId);
    if (!$publication['ok']) json_response(['error' => $publication['error'], 'publication' => $publication], 502);
    $sync = sync_discord_event($pdo, $eventId, true);
    json_response([...$sync, 'synced' => $sync['imported'] ?? 0, 'message' => 'Evento publicado y participantes sincronizados.']);
} catch (Throwable $error) { json_response(['error' => $error->getMessage()], 502); }
