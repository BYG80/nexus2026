<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin();
header('Location: /Admin/rsp.php#rsp-addon-sync', true, 302);
exit;
