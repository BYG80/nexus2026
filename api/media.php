<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $type = strtolower(trim((string)($_GET['type'] ?? 'image')));
    if ($type !== 'image') $type = 'image';

    $stmt = db()->prepare('SELECT title, description, category, url FROM media WHERE type = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$type]);
    echo json_encode(['success' => true, 'images' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'images' => [], 'error' => 'No se pudo cargar la galería.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
