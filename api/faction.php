<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$config = nexus_faction_config();
echo json_encode([
 'faction'=>$config['faction'],
 'label'=>$config['label'],
 'races'=>$config['races'],
 'classes'=>nexus_wow_classes()
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
