<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
start_secure_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $member = require_discord_member();
    $pdo = db();
    $faction = nexus_faction();
    $stmt = $pdo->prepare(
        'SELECT id, name, race, class_name, role
         FROM member_profiles
         WHERE discord_id = ? AND faction = ?
         ORDER BY name ASC, id ASC'
    );
    $stmt->execute([$member['discord_id'], $faction]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'characters'=>$characters], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>$e->getMessage() ?: 'No se pudieron cargar tus personajes.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
