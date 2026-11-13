<?php

/*
|--------------------------------------------------------------------------
| NEXUS - Bootstrap
|--------------------------------------------------------------------------
*/

if (!defined('NEXUS_ROOT')) {
    define('NEXUS_ROOT', dirname(__DIR__));
}

if (!defined('NEXUS_CONFIG_FILE')) {
    define(
        'NEXUS_CONFIG_FILE',
        NEXUS_ROOT . '/config/config.php'
    );
}


/*
|--------------------------------------------------------------------------
| CARGAR CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

$GLOBALS['NEXUS_CONFIG'] = [];

if (is_file(NEXUS_CONFIG_FILE)) {
    $config = require NEXUS_CONFIG_FILE;

    if (is_array($config)) {
        $GLOBALS['NEXUS_CONFIG'] = $config;
    }
}


function app_config(string $key, $default = null)
{
    return $GLOBALS['NEXUS_CONFIG'][$key] ?? $default;
}


function app_timezone(): DateTimeZone
{
    try { return new DateTimeZone((string)app_config('app_timezone', 'Europe/Madrid')); }
    catch (Exception $error) { return new DateTimeZone('Europe/Madrid'); }
}

/*
|--------------------------------------------------------------------------
| ESCAPAR HTML
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| Esta función es utilizada por index.php, galería,
| noticias y otras páginas públicas.
|
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| DISCORD OAUTH
|--------------------------------------------------------------------------
*/

function discord_configured(): bool
{
    return
        trim((string)app_config('discord_client_id', '')) !== '' &&
        trim((string)app_config('discord_client_secret', '')) !== '' &&
        trim((string)app_config('discord_redirect_uri', '')) !== '';
}


function discord_redirect_uri(): string
{
    return trim(
        (string)app_config(
            'discord_redirect_uri',
            ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| ZONA HORARIA
|--------------------------------------------------------------------------
*/

date_default_timezone_set(
    (string)app_config(
        'app_timezone',
        'Europe/Madrid'
    )
);


/*
|--------------------------------------------------------------------------
| RESPUESTA JSON
|--------------------------------------------------------------------------
*/

function json_response(
    array $data,
    int $status = 200
): never {

    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SESIONES
|--------------------------------------------------------------------------
*/

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    $secure =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $forwardedProto === 'https';

    // Versioned session name avoids conflicts with cookies created by older releases.
    session_name('NEXUSSESSID_V2');

    // OAuth de Discord puede volver al host canónico (www) aunque el
    // usuario haya iniciado el flujo desde el dominio sin www. Compartimos
    // la cookie de sesión entre ambos hosts para no perder el estado OAuth.
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $cookieDomain = '';
    if (preg_match('/(^|\.)nexus-forever\.es(?::\d+)?$/', $host)) {
        $cookieDomain = '.nexus-forever.es';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    start_secure_session();

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function csrf_check(?string $token): bool
{
    start_secure_session();

    return
        !empty($_SESSION['csrf_token']) &&
        is_string($token) &&
        hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
}


function check_csrf(?string $token): void
{
    if (!csrf_check($token)) {

        http_response_code(403);

        throw new RuntimeException(
            'Token CSRF inválido o sesión caducada. Recarga la página e inténtalo de nuevo.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| MYSQL
|--------------------------------------------------------------------------
*/

function nexus_is_configured(): bool
{
    return
        trim((string)app_config('db_host', '')) !== '' &&
        trim((string)app_config('db_name', '')) !== '' &&
        trim((string)app_config('db_user', '')) !== '';
}


function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host =
        (string)app_config(
            'db_host',
            'localhost'
        );

    $name =
        (string)app_config(
            'db_name',
            ''
        );

    $user =
        (string)app_config(
            'db_user',
            ''
        );

    $pass =
        (string)app_config(
            'db_pass',
            ''
        );

    $charset =
        (string)app_config(
            'db_charset',
            'utf8mb4'
        );

    if (
        $name === '' ||
        $user === ''
    ) {
        throw new RuntimeException(
            'La configuración de MySQL está incompleta.'
        );
    }

    $dsn =
        "mysql:host={$host};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        // No mostramos usuario, contraseña, DSN ni rutas internas al visitante.
        throw new RuntimeException(
            'No se puede conectar con MySQL. Comprueba usuario, contraseña, base de datos y permisos del usuario MySQL.',
            0,
            $e
        );
    }

    nexus_migrate_schema($pdo);

    return $pdo;
}


/*
|--------------------------------------------------------------------------
| COMPROBAR COLUMNA
|--------------------------------------------------------------------------
*/

function nexus_table_exists(PDO $pdo, string $table): bool
{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}



/**
 * RSP ganado esta semana por un miembro. La semana RSP comienza el lunes a las 00:00.
 * Solo cuentan movimientos positivos de actividades; los ajustes administrativos y
 * saldos iniciales no se consideran para el límite semanal.
 */
function rsp_weekly_earned(PDO $pdo, string $discordId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0)
        FROM dkp_ledger
        WHERE member_discord_id=?
          AND amount > 0
          AND COALESCE(category,'') <> 'opening_balance'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)");
    $stmt->execute([$discordId]);
    return (int)$stmt->fetchColumn();
}

function rsp_weekly_cap(): int
{
    return 50;
}

function nexus_column_exists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt = $pdo->query(
        "SHOW COLUMNS FROM `{$table}` LIKE " .
        $pdo->quote($column)
    );

    return (bool)$stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| MIGRACIONES
|--------------------------------------------------------------------------
*/

function nexus_migrate_schema(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS nexus_settings (
            setting_key VARCHAR(80) NOT NULL,
            value TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO nexus_settings (setting_key,value) VALUES ('faction','horda')");
    } catch (Throwable $e) {}


    /* Mensajería administrativa: historial de mensajes publicados en Discord. */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS discord_admin_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel_id VARCHAR(32) NOT NULL,
            discord_message_id VARCHAR(32) NULL,
            content TEXT NOT NULL,
            admin_user VARCHAR(120) NULL,
            status ENUM('sent','deleted') NOT NULL DEFAULT 'sent',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_discord_admin_messages_created (created_at),
            KEY idx_discord_admin_messages_status (status),
            KEY idx_discord_admin_messages_discord_id (discord_message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}

    /* RSP: crear/actualizar el esquema automáticamente para instalaciones existentes. */
    try {
        if (!nexus_table_exists($pdo, 'roster')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS roster (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(80) NOT NULL,
                race VARCHAR(80) NOT NULL DEFAULT '',
                class_name VARCHAR(80) NOT NULL DEFAULT '',
                class_color VARCHAR(20) NOT NULL DEFAULT '#d8ad55',
                level SMALLINT UNSIGNED NULL,
                role VARCHAR(40) NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                notes VARCHAR(255) NULL,
                roster_group VARCHAR(2) NOT NULL DEFAULT 'A',
                discord_id VARCHAR(32) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY idx_roster_name (name), KEY idx_roster_discord (discord_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } elseif (!nexus_column_exists($pdo, 'roster', 'discord_id')) {
            $pdo->exec("ALTER TABLE roster ADD COLUMN discord_id VARCHAR(32) NULL AFTER notes");
        }
        try { $pdo->exec("ALTER TABLE roster ADD INDEX idx_roster_discord (discord_id)"); } catch (Throwable $e) {}
        if (!nexus_column_exists($pdo, 'roster', 'faction')) {
            try { $pdo->exec("ALTER TABLE roster ADD COLUMN faction ENUM('horda','alianza') NOT NULL DEFAULT 'horda' AFTER race"); } catch (Throwable $e) {}
        }
        try {
            $pdo->exec("UPDATE roster SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche')");
            $pdo->exec("UPDATE roster SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto')");
        } catch (Throwable $e) {}

        if (!nexus_column_exists($pdo, 'roster', 'source')) {
            try { $pdo->exec("ALTER TABLE roster ADD COLUMN source ENUM('admin','member') NOT NULL DEFAULT 'admin' AFTER faction"); } catch (Throwable $e) {}
            /* Los registros antiguos creados desde Mi panel se consideran perfiles de miembro, no roster público. */
            try { $pdo->exec("UPDATE roster SET source='member' WHERE discord_id IS NOT NULL AND discord_id<>''"); } catch (Throwable $e) {}
        }

        if (!nexus_column_exists($pdo, 'roster', 'roster_group')) {
            try { $pdo->exec("ALTER TABLE roster ADD COLUMN roster_group VARCHAR(2) NOT NULL DEFAULT 'A' AFTER notes"); } catch (Throwable $e) {}
        } else {
            try { $pdo->exec("ALTER TABLE roster MODIFY roster_group VARCHAR(2) NOT NULL DEFAULT 'A'"); } catch (Throwable $e) {}
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS member_profiles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                discord_id VARCHAR(32) NOT NULL,
                faction ENUM('horda','alianza') NOT NULL,
                name VARCHAR(80) NOT NULL,
                race VARCHAR(80) NOT NULL DEFAULT '',
                class_name VARCHAR(80) NOT NULL DEFAULT '',
                level SMALLINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_member_profile (discord_id,faction),
                KEY idx_member_profile_faction (faction)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {}
        /* Un miembro puede registrar varios personajes. La versión anterior
         * limitaba member_profiles a un único personaje por Discord. */
        try { $pdo->exec("ALTER TABLE member_profiles DROP INDEX uq_member_profile"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE member_profiles ADD INDEX idx_member_profile_owner (discord_id,faction)"); } catch (Throwable $e) {}
        /* Rol de combate elegido por el miembro para cada personaje. */
        try {
            if (!nexus_column_exists($pdo, 'member_profiles', 'role')) {
                $pdo->exec("ALTER TABLE member_profiles ADD COLUMN role VARCHAR(20) NULL AFTER class_name");
            }
        } catch (Throwable $e) {}

        /* Pedidos de miembros: se publican automáticamente en Discord. */
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS member_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_discord_id VARCHAR(32) NOT NULL,
                member_username VARCHAR(120) NULL,
                faction ENUM('horda','alianza') NOT NULL DEFAULT 'horda',
                character_name VARCHAR(80) NULL,
                title VARCHAR(180) NOT NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'General',
                quantity VARCHAR(80) NULL,
                details TEXT NOT NULL,
                priority ENUM('normal','alta','urgente') NOT NULL DEFAULT 'normal',
                status ENUM('active','closed') NOT NULL DEFAULT 'active',
                discord_message_id VARCHAR(32) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_member_orders_member (member_discord_id,status),
                KEY idx_member_orders_status (status,created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!nexus_column_exists($pdo, 'member_orders', 'recipient_discord_id')) {
                $pdo->exec("ALTER TABLE member_orders ADD COLUMN recipient_discord_id VARCHAR(32) NULL AFTER member_discord_id");
            }
            if (!nexus_column_exists($pdo, 'member_orders', 'recipient_username')) {
                $pdo->exec("ALTER TABLE member_orders ADD COLUMN recipient_username VARCHAR(120) NULL AFTER member_username");
            }
            if (!nexus_column_exists($pdo, 'member_orders', 'recipient_character_name')) {
                $pdo->exec("ALTER TABLE member_orders ADD COLUMN recipient_character_name VARCHAR(80) NULL AFTER character_name");
            }
            if (!nexus_column_exists($pdo, 'member_orders', 'item_name')) {
                $pdo->exec("ALTER TABLE member_orders ADD COLUMN item_name VARCHAR(180) NULL AFTER title");
            }
        } catch (Throwable $e) {}

        if (!nexus_table_exists($pdo, 'dkp_rules')) {
            $pdo->exec("CREATE TABLE dkp_rules (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(60) NOT NULL,
                label VARCHAR(120) NOT NULL,
                amount INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 100,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY uq_dkp_rules_code (code), KEY idx_dkp_rules_active (active,sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if (!nexus_table_exists($pdo, 'dkp_ledger')) {
            $pdo->exec("CREATE TABLE dkp_ledger (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_discord_id VARCHAR(32) NOT NULL,
                roster_id INT UNSIGNED NULL,
                character_name VARCHAR(80) NULL,
                event_id INT UNSIGNED NULL,
                amount INT NOT NULL,
                category VARCHAR(60) NOT NULL DEFAULT 'manual',
                reason VARCHAR(255) NOT NULL,
                source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
                source_key VARCHAR(180) NULL,
                admin_user VARCHAR(120) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY uq_dkp_source (source_key), KEY idx_dkp_member (member_discord_id,created_at), KEY idx_dkp_event (event_id), KEY idx_dkp_roster (roster_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $rules = [
            ['raid_attendance','Asistencia a raid',10,10],
            ['raid_ontime','Puntualidad',5,20],
            ['full_consumables','Preparación completa (frasco + comida + consumibles)',10,30],
            ['flask','Frasco',3,40],
            ['food','Comida / festín',3,50],
            ['potions','Pociones',2,60],
            ['oil_sharpening','Aceites / afiladores',2,70],
            ['materials','Materiales / utilidades de raid',2,80],
            ['extra_help','Ayuda extra a la raid',5,90],
            ['mentor','Mentor de clase',20,100],
            ['guide','Guía general',10,110],
            ['pvp_premade','PvP Premade',20,120],
            ['pvp_bg_group','BG en grupo de 3 a 5 miembros',5,130],
        ];
        $ruleIns=$pdo->prepare("INSERT INTO dkp_rules (code,label,amount,active,sort_order) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)");
        foreach($rules as $r){ $ruleIns->execute([$r[0],$r[1],$r[2],1,$r[3]]); }
    } catch (Throwable $e) { /* La app sigue funcionando aunque una migración opcional falle. */ }

    try {
        if (!nexus_table_exists($pdo, 'members')) {
            $pdo->exec("CREATE TABLE members (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                discord_id VARCHAR(32) NOT NULL,
                discord_username VARCHAR(120) NOT NULL,
                discord_avatar VARCHAR(255) NULL,
                is_member TINYINT(1) NOT NULL DEFAULT 0,
                member_role_name VARCHAR(120) NULL,
                verified_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY uq_members_discord (discord_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            if (!nexus_column_exists($pdo,'members','is_member')) $pdo->exec("ALTER TABLE members ADD COLUMN is_member TINYINT(1) NOT NULL DEFAULT 0 AFTER discord_avatar");
            if (!nexus_column_exists($pdo,'members','member_role_name')) $pdo->exec("ALTER TABLE members ADD COLUMN member_role_name VARCHAR(120) NULL AFTER is_member");
            if (!nexus_column_exists($pdo,'members','verified_at')) $pdo->exec("ALTER TABLE members ADD COLUMN verified_at DATETIME NULL AFTER member_role_name");
        }
    } catch (Throwable $e) {}

    try {

        if (
            nexus_column_exists(
                $pdo,
                'events',
                'discord_message_id'
            ) === false
        ) {

            $pdo->exec(
                "ALTER TABLE events
                 ADD COLUMN discord_message_id VARCHAR(64) NULL"
            );
        }

    } catch (Throwable $e) {}


    try {

        if (
            nexus_column_exists(
                $pdo,
                'events',
                'discord_sync_error'
            ) === false
        ) {

            $pdo->exec(
                "ALTER TABLE events
                 ADD COLUMN discord_sync_error VARCHAR(500) NULL"
            );
        }

    } catch (Throwable $e) {}


    try {

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS professions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_discord_id VARCHAR(32) NULL,
                member_username VARCHAR(120) NULL,
                character_name VARCHAR(80) NOT NULL,
                race VARCHAR(80) NULL,
                class_name VARCHAR(80) NULL,
                level SMALLINT UNSIGNED NULL,
                profession_name VARCHAR(80) NOT NULL,
                profession_type ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
                skill_level SMALLINT UNSIGNED NULL,
                notes VARCHAR(255) NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_profession_name (profession_name),
                KEY idx_profession_member (member_discord_id),
                KEY idx_profession_character (character_name),
                KEY idx_profession_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!nexus_column_exists($pdo, 'professions', 'faction')) {
            try { $pdo->exec("ALTER TABLE professions ADD COLUMN faction ENUM('horda','alianza') NULL AFTER race"); } catch (Throwable $e) {}
        }
        try {
            $pdo->exec("UPDATE professions SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche')");
            $pdo->exec("UPDATE professions SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto','Cielonato') AND (faction IS NULL OR faction='')");
        } catch (Throwable $e) {}

        if (!nexus_column_exists($pdo, 'professions', 'member_username')) {
            $pdo->exec("ALTER TABLE professions ADD COLUMN member_username VARCHAR(120) NULL AFTER member_discord_id");
        }
        if (!nexus_column_exists($pdo, 'professions', 'updated_at')) {
            $pdo->exec("ALTER TABLE professions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        if (!nexus_column_exists($pdo, 'professions', 'notes')) {
            try { $pdo->exec("ALTER TABLE professions ADD COLUMN notes VARCHAR(255) NULL AFTER skill_level"); } catch (Throwable $e) {}
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS profession_discord_state (
                id TINYINT UNSIGNED NOT NULL,
                message_id VARCHAR(2048) NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("INSERT IGNORE INTO profession_discord_state (id) VALUES (1)");
        try { $pdo->exec("ALTER TABLE profession_discord_state MODIFY message_id VARCHAR(2048) NULL"); } catch (Throwable $e) {}
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rsp_discord_state (id TINYINT UNSIGNED NOT NULL, message_id VARCHAR(2048) NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO rsp_discord_state (id) VALUES (1)");
        try { $pdo->exec("ALTER TABLE rsp_discord_state MODIFY message_id VARCHAR(2048) NULL"); } catch (Throwable $e) {}
    } catch (Throwable $e) {}


    try {
        if (!nexus_column_exists($pdo, 'event_attendees', 'faction')) {
            try { $pdo->exec("ALTER TABLE event_attendees ADD COLUMN faction ENUM('horda','alianza') NULL AFTER race"); } catch (Throwable $e) {}
        }
        try {
            $pdo->exec("UPDATE event_attendees SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche')");
            $pdo->exec("UPDATE event_attendees SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto','Cielonato') AND (faction IS NULL OR faction='')");
        } catch (Throwable $e) {}

        if (
            nexus_column_exists(
                $pdo,
                'event_attendees',
                'role'
            ) === false
        ) {

            $pdo->exec(
                "ALTER TABLE event_attendees
                 ADD COLUMN role VARCHAR(20) NULL
                 AFTER class_name"
            );
        }

    } catch (Throwable $e) {}
}


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN GLOBAL DE FACCIÓN
|--------------------------------------------------------------------------
*/

function nexus_faction_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $faction = 'horda';
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT value FROM nexus_settings WHERE setting_key='faction' LIMIT 1");
        $value = strtolower(trim((string)$stmt->fetchColumn()));
        if (in_array($value, ['horda', 'alianza'], true)) {
            $faction = $value;
        }
    } catch (Throwable $e) {
        // En una instalación antigua, Horda sigue siendo el valor seguro por defecto.
    }

    $file = NEXUS_ROOT . '/config/wow-forever-' . $faction . '.php';
    $races = is_file($file) ? require $file : [];
    if (!is_array($races) || !$races) {
        $races = require NEXUS_ROOT . '/config/wow-forever-horda.php';
        $faction = 'horda';
    }

    $config = [
        'faction' => $faction,
        'label' => $faction === 'alianza' ? 'Alianza' : 'Horda',
        'races' => $races,
    ];
    return $config;
}

function nexus_faction(): string
{
    return nexus_faction_config()['faction'];
}

function nexus_faction_label(): string
{
    return nexus_faction_config()['label'];
}

function nexus_wow_races(): array
{
    return nexus_faction_config()['races'];
}

function nexus_wow_classes(): array
{
    return array_values(array_unique(array_merge(...array_values(nexus_wow_races()))));
}

function nexus_faction_emoji(): string
{
    return nexus_faction() === 'alianza' ? '🛡️' : '⚔️';
}

function nexus_faction_save(PDO $pdo, string $faction): void
{
    $faction = strtolower(trim($faction));
    if (!in_array($faction, ['horda', 'alianza'], true)) {
        throw new InvalidArgumentException('Facción no válida.');
    }
    $stmt = $pdo->prepare("INSERT INTO nexus_settings (setting_key,value) VALUES ('faction',?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute([$faction]);
}


/*
|--------------------------------------------------------------------------
| FECHA/HORA DE EVENTOS
|--------------------------------------------------------------------------
*/

function event_start_utc(array $event): DateTimeImmutable
{
    $date =
        trim(
            (string)(
                $event['event_date']
                ?? $event['date']
                ?? ''
            )
        );

    $time =
        trim(
            (string)(
                $event['event_time']
                ?? $event['time']
                ?? '00:00'
            )
        );

    if ($date === '') {

        throw new RuntimeException(
            'El evento no tiene fecha.'
        );
    }

    $local = new DateTimeImmutable(
        $date . ' ' . $time,
        new DateTimeZone(
            (string)app_config(
                'app_timezone',
                'Europe/Madrid'
            )
        )
    );

    return $local->setTimezone(
        new DateTimeZone('UTC')
    );
}


/*
|--------------------------------------------------------------------------
| DATOS DEL EVENTO
|--------------------------------------------------------------------------
*/

function nexus_event_date(array $event): string
{
    return trim(
        (string)(
            $event['event_date']
            ?? $event['date']
            ?? ''
        )
    );
}


function nexus_event_time(array $event): string
{
    return trim(
        (string)(
            $event['event_time']
            ?? $event['time']
            ?? ''
        )
    );
}


function nexus_event_title(array $event): string
{
    return trim(
        (string)(
            $event['title']
            ?? $event['name']
            ?? 'Evento NEXUS'
        )
    );
}


function nexus_event_description(array $event): string
{
    return trim(
        (string)(
            $event['description']
            ?? ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| DISCORD API
|--------------------------------------------------------------------------
*/

function discord_api(string $method, string $url, ?array $payload = null, ?string $token = null): array
{
    return nexus_discord_request($method, $url, $payload, $token);
}

function discord_create_event(array $event): array
{
    return discord_api('POST', nexus_discord_guild_url() . '/scheduled-events', nexus_discord_event_payload($event));
}

/*
|--------------------------------------------------------------------------
| LISTAR USUARIOS INTERESADOS
|--------------------------------------------------------------------------
*/

function discord_list_event_users(string $guildId, string $eventId, ?callable $api = null): array
{
    if ($guildId === '' || $eventId === '') return [];
    $api ??= 'discord_api';
    $users = [];
    $after = null;
    for ($page = 0; $page < 20; $page++) {
        $url = 'https://discord.com/api/v10/guilds/' . rawurlencode($guildId) . '/scheduled-events/' . rawurlencode($eventId) . '/users?limit=100';
        if ($after !== null) $url .= '&after=' . rawurlencode($after);
        $batch = $api('GET', $url);
        foreach ($batch as $item) $users[] = $item;
        if (count($batch) < 100) return $users;
        $last = end($batch);
        $next = (string)($last['user']['id'] ?? '');
        if ($next === '' || $next === $after) throw new NexusDiscordException('No se pudo completar la lista de interesados en Discord.');
        $after = $next;
    }
    throw new NexusDiscordException('El evento supera el límite de participantes que se puede sincronizar en una petición.');
}

/*
|--------------------------------------------------------------------------
| MENSAJES DISCORD
|--------------------------------------------------------------------------
*/

function discord_send_channel_message(
    string $channelId,
    array $payload
): array {

    if ($channelId === '') {

        throw new RuntimeException(
            'No se ha indicado un canal de Discord.'
        );
    }

    return discord_api(
        'POST',
        'https://discord.com/api/v10/channels/' .
        rawurlencode($channelId) .
        '/messages',
        $payload
    );
}


function discord_edit_channel_message(
    string $channelId,
    string $messageId,
    array $payload
): array {

    if (
        $channelId === '' ||
        $messageId === ''
    ) {

        throw new RuntimeException(
            'Faltan datos para editar el mensaje de Discord.'
        );
    }

    return discord_api(
        'PATCH',
        'https://discord.com/api/v10/channels/' .
        rawurlencode($channelId) .
        '/messages/' .
        rawurlencode($messageId),
        $payload
    );
}


/*
|--------------------------------------------------------------------------
| PROFESIONES — DISCORD
|--------------------------------------------------------------------------
*/

function discord_guild_member(string $discordId): ?array
{
    $guildId = trim((string)app_config('discord_guild_id', ''));
    $discordId = trim($discordId);

    if ($guildId === '' || $discordId === '') {
        return null;
    }

    try {
        return discord_api(
            'GET',
            'https://discord.com/api/v10/guilds/' .
            rawurlencode($guildId) .
            '/members/' .
            rawurlencode($discordId)
        );
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'HTTP 404')) {
            return null;
        }
        throw $e;
    }
}

function discord_user_is_guild_member(string $discordId): bool
{
    return discord_guild_member($discordId) !== null;
}

function discord_guild_roles(): array
{
    $guildId=trim((string)app_config('discord_guild_id',''));
    if($guildId==='') return [];
    return discord_api('GET','https://discord.com/api/v10/guilds/'.rawurlencode($guildId).'/roles');
}

function discord_member_role_info(string $discordId): ?array
{
    $member = discord_guild_member($discordId);
    if (!$member) return null;
    $roleId = trim((string)app_config('discord_member_role_id', ''));
    $roleName = trim((string)app_config('discord_member_role_name', 'Miembros'));
    $memberRoles = array_map('strval', $member['roles'] ?? []);
    if ($roleId !== '') return in_array($roleId, $memberRoles, true) ? ['id' => $roleId, 'name' => $roleName] : null;
    foreach (discord_guild_roles() as $role) {
        if (isset($role['id'], $role['name']) && in_array((string)$role['id'], $memberRoles, true) && mb_strtolower((string)$role['name']) === mb_strtolower($roleName)) return ['id' => (string)$role['id'], 'name' => (string)$role['name']];
    }
    return null;
}

function discord_user_is_member(string $discordId): bool
{
    return discord_member_role_info($discordId)!==null;
}

function require_discord_member(): array
{
    start_secure_session();
    $id=trim((string)($_SESSION['member_discord_id']??''));
    if($id===''){
        if(str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/api/')) json_response(['error'=>'Debes iniciar sesión con Discord.'],401);
        header('Location: /auth/discord.php?return_to=/panel'); exit;
    }
    $info=discord_member_role_info($id);
    if(!$info){
        unset($_SESSION['member_discord_id'], $_SESSION['member_username'], $_SESSION['member_avatar'], $_SESSION['member_role']);
        try { db()->prepare('UPDATE members SET is_member=0 WHERE discord_id=?')->execute([$id]); } catch (Throwable $ignore) {}
        if(str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/api/')) json_response(['error'=>'Necesitas el rol de miembro de NEXUS.'],403);
        http_response_code(403);
        exit('Necesitas pertenecer al servidor de Discord y tener el rol Miembros para acceder al panel.');
    }
    $pdo=db();
    try{$st=$pdo->prepare('UPDATE members SET is_member=1,member_role_name=?,verified_at=NOW() WHERE discord_id=?');$st->execute([$info['name'],$id]);}catch(Throwable $e){}
    return ['discord_id'=>$id,'username'=>(string)($_SESSION['member_username']??'Discord'),'role'=>$info['name']];
}

function rsp_discord_channel_id(): string { return trim((string)app_config('rsp_discord_channel_id','')); }

function rsp_publish_discord_award(PDO $pdo,int $eventId,string $eventTitle,string $label,int $amount,array $memberIds,int $count): void
{
    $channel=rsp_discord_channel_id();
    if($channel==='') throw new RuntimeException('No está configurado rsp_discord_channel_id.');
    $memberIds=array_values(array_unique(array_map('strval',$memberIds)));
    if(!$memberIds) return;
    $ph=implode(',',array_fill(0,count($memberIds),'?'));
    $st=$pdo->prepare("SELECT DISTINCT COALESCE(NULLIF(r.name,''),m.discord_username) name,r.class_name,r.race,r.discord_id FROM members m LEFT JOIN roster r ON r.discord_id=m.discord_id AND r.faction=? WHERE m.discord_id IN ($ph) ORDER BY name");
    $st->execute(array_merge([nexus_faction()],$memberIds));
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    $balanceStmt=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=?');
    $fields=[];
    foreach($rows as $row){
        $balanceStmt->execute([(string)$row['discord_id']]);
        $balance=(int)$balanceStmt->fetchColumn();
        $movement=($amount>=0?'+':'').$amount;
        $fields[]=['name'=>(string)($row['name']??'Discord'),'value'=>'**'.$movement.' RSP** · **Total: '.$balance.' RSP**'.($row['class_name']?' · '.$row['class_name']:'').' · '.nexus_faction_label(),'inline'=>true];
    }
    if(!$rows){
        $fields=[['name'=>'Participantes','value'=>$count.' participante(s) · '.(($amount>=0?'+':'').$amount).' RSP','inline'=>false]];
    }
    if(!$fields) $fields=[['name'=>'Participantes','value'=>$count.' participante(s) · +'.$amount.' RSP','inline'=>false]];
    $embeds=[];
    foreach(array_chunk($fields,25) as $i=>$chunk){
        $embeds[]=['title'=>'RSP · '.mb_substr($eventTitle,0,200),'description'=>'**'.mb_substr($label,0,200).'** · '.($amount>=0?'+':'').$amount.' RSP por participante','color'=>0xD8AD55,'fields'=>$chunk,'footer'=>['text'=>'NEXUS · World of Warcraft Forever · '.nexus_faction_label()]];
    }
    foreach(array_chunk($embeds,1) as $chunkIndex=>$embedChunk){
        discord_send_channel_message($channel,['content'=>$chunkIndex===0?'📋 **RSP registrado**':'📋 **RSP — continuación**','embeds'=>$embedChunk]);
    }
}

function discord_delete_channel_message(string $channelId, string $messageId): array
{
    if ($channelId === '' || $messageId === '') {
        throw new RuntimeException('Faltan datos para eliminar el mensaje de Discord.');
    }

    return discord_api(
        'DELETE',
        'https://discord.com/api/v10/channels/' . rawurlencode($channelId) . '/messages/' . rawurlencode($messageId)
    );
}

function rsp_wow_class_icon_url(string $className): string
{
    $class = mb_strtolower(trim($className), 'UTF-8');
    $class = strtr($class, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
    $map = [
        'warrior'=>'warrior.jpg', 'guerrero'=>'warrior.jpg',
        'paladin'=>'paladin.jpg', 'paladín'=>'paladin.jpg',
        'hunter'=>'hunter.jpg', 'cazador'=>'hunter.jpg',
        'rogue'=>'rogue.jpg', 'picaro'=>'rogue.jpg', 'pícaro'=>'rogue.jpg',
        'priest'=>'priest.jpg', 'sacerdote'=>'priest.jpg',
        'shaman'=>'shaman.jpg', 'chaman'=>'shaman.jpg', 'chamán'=>'shaman.jpg',
        'mage'=>'mage.jpg', 'mago'=>'mage.jpg',
        'warlock'=>'warlock.jpg', 'brujo'=>'warlock.jpg',
        'druid'=>'druid.jpg', 'druida'=>'druid.jpg',
        'death knight'=>'deathknight.jpg', 'caballero de la muerte'=>'deathknight.jpg',
        'monk'=>'monk.jpg', 'monje'=>'monk.jpg',
        'demon hunter'=>'demonhunter.jpg', 'cazador de demonios'=>'demonhunter.jpg',
        'evoker'=>'evoker.jpg', 'evocador'=>'evoker.jpg'
    ];
    $file = $map[$class] ?? 'inv_misc_questionmark.jpg';
    return 'https://wow.zamimg.com/images/wow/icons/large/' . $file;
}

function sync_rsp_ranking_to_discord(PDO $pdo): array
{
    $channelId = rsp_discord_channel_id();
    if ($channelId === '') throw new RuntimeException('No está configurado rsp_discord_channel_id.');

    // Usar la misma fuente que la pestaña Miembros: member_profiles.
    // El roster administrativo no contiene necesariamente los perfiles creados por los miembros.
    // Así se incluyen todos los perfiles verificados de la facción, también con 0 RSP.
    $st = $pdo->prepare("SELECT mp.name,mp.race,mp.class_name,mp.discord_id,
            COALESCE(SUM(dl.amount),0) AS balance
        FROM member_profiles mp
        INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1
        LEFT JOIN dkp_ledger dl ON dl.member_discord_id=mp.discord_id
        WHERE mp.faction=?
        GROUP BY mp.id,mp.name,mp.race,mp.class_name,mp.discord_id
        ORDER BY balance DESC,mp.name ASC");
    $st->execute([nexus_faction()]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Discord limita el contenido de cada mensaje a 2000 caracteres. Dividir
    // el ranking en varios mensajes para que no se pierda ningún jugador.
    $header = '🏆 **RANKING RSP NEXUS** · Clasificación actual de la hermandad';
    $chunks = [];
    $current = $header;
    if (!$rows) $current .= "\nTodavía no hay personajes activos en el roster.";
    foreach ($rows as $index => $row) {
        $position = $index + 1;
        $medal = [1=>'🥇', 2=>'🥈', 3=>'🥉'][$position] ?? ('**#' . $position . '**');
        $name = trim((string)($row['name'] ?? '')) ?: 'Jugador';
        $className = trim((string)($row['class_name'] ?? ''));
        $race = trim((string)($row['race'] ?? ''));
        $details = implode(' · ', array_values(array_filter([$className, $race], static fn($v) => $v !== '')));
        $line = "\n" . $medal . ' **' . $name . '** — **' . number_format((int)$row['balance'], 0, ',', '.') . ' RSP**' . ($details !== '' ? ' · ' . $details : '');
        if (mb_strlen($current . $line, 'UTF-8') > 1850) {
            $chunks[] = $current;
            $current = $header . ' · continuación';
        }
        $current .= $line;
    }
    if ($current !== '') $chunks[] = $current;

    $state = $pdo->query("SELECT message_id FROM rsp_discord_state WHERE id=1 LIMIT 1")->fetch();
    $stored = trim((string)($state['message_id'] ?? ''));
    $oldIds = [];
    if ($stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) foreach ($decoded as $id) { $id = trim((string)$id); if ($id !== '') $oldIds[] = $id; }
        else $oldIds[] = $stored;
    }

    $newIds = [];
    $created = 0; $updated = 0;
    foreach ($chunks as $i => $content) {
        $payload = ['content'=>$content, 'allowed_mentions'=>['parse'=>[]]];
        $oldId = $oldIds[$i] ?? '';
        if ($oldId !== '') {
            try {
                $message = discord_edit_channel_message($channelId, $oldId, $payload);
                $newIds[] = trim((string)($message['id'] ?? $oldId));
                $updated++;
                continue;
            } catch (NexusDiscordException $e) { if ($e->httpStatus !== 404) throw $e; }
        }
        $message = discord_send_channel_message($channelId, $payload);
        $messageId = trim((string)($message['id'] ?? ''));
        if ($messageId === '') throw new RuntimeException('Discord no devolvió el ID del mensaje RSP.');
        $newIds[] = $messageId;
        $created++;
    }
    // Borrar únicamente los mensajes sobrantes del ranking anterior.
    foreach (array_slice($oldIds, count($chunks)) as $oldExtra) {
        try { discord_delete_channel_message($channelId, $oldExtra); } catch (Throwable $e) {}
    }
    $up = $pdo->prepare('INSERT INTO rsp_discord_state (id,message_id) VALUES (1,?) ON DUPLICATE KEY UPDATE message_id=VALUES(message_id)');
    $up->execute([json_encode($newIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    return ['ok'=>true,'created'=>$created>0,'updated'=>$updated>0,'created_count'=>$created,'updated_count'=>$updated,'message_ids'=>$newIds,'channel_id'=>$channelId,'players_count'=>count($rows)];
}

function member_orders_discord_channel_id(): string
{
    return trim((string)app_config('discord_order_channel_id', ''));
}

function publish_member_order_to_discord(PDO $pdo, int $orderId): string
{
    $channelId = member_orders_discord_channel_id();
    if ($channelId === '') throw new RuntimeException('No está configurado discord_order_channel_id.');

    $st = $pdo->prepare("SELECT * FROM member_orders WHERE id=? AND status='active' LIMIT 1");
    $st->execute([$orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new RuntimeException('El pedido no existe o ya está cerrado.');

    $priority = ['normal'=>'🟢 Normal','alta'=>'🟠 Alta','urgente'=>'🔴 Urgente'][$order['priority']] ?? '🟢 Normal';
    $requester = trim((string)($order['member_username'] ?? '')) ?: 'Miembro NEXUS';
    $requesterCharacter = trim((string)($order['character_name'] ?? ''));
    $recipient = trim((string)($order['recipient_username'] ?? '')) ?: 'Miembro de la hermandad';
    $recipientCharacter = trim((string)($order['recipient_character_name'] ?? ''));
    $item = trim((string)($order['item_name'] ?? '')) ?: trim((string)($order['title'] ?? ''));

    $requesterLabel = $requester . ($requesterCharacter !== '' ? ' · ' . $requesterCharacter : '');
    $recipientLabel = $recipient . ($recipientCharacter !== '' ? ' · ' . $recipientCharacter : '');

    $fields = [
        ['name'=>'🙋 Quién lo solicita','value'=>mb_substr($requesterLabel,0,1024),'inline'=>true],
        ['name'=>'🎯 A quién se lo solicita','value'=>mb_substr($recipientLabel,0,1024),'inline'=>true],
        ['name'=>'📦 Item solicitado','value'=>mb_substr($item,0,1024) ?: 'Sin especificar.','inline'=>false],
        ['name'=>'📋 Detalles','value'=>mb_substr(trim((string)$order['details']),0,1024) ?: 'Sin detalles.','inline'=>false],
        ['name'=>'📂 Categoría','value'=>mb_substr(trim((string)$order['category']),0,256),'inline'=>true],
        ['name'=>'🔢 Cantidad','value'=>mb_substr(trim((string)$order['quantity']),0,256) ?: 'No indicada','inline'=>true],
        ['name'=>'⚡ Prioridad','value'=>$priority,'inline'=>true],
    ];

    $payload=[
        'content'=>'📦 **NUEVO PEDIDO NEXUS**',
        'embeds'=>[[
            'title'=>'📦 '.mb_substr(trim((string)$order['title']),0,240),
            'description'=>'Un miembro de NEXUS solicita ayuda a la hermandad.',
            'color'=>$order['priority']==='urgente'?0xD9534F:($order['priority']==='alta'?0xF0AD4E:0xD8AD55),
            'fields'=>$fields,
            'footer'=>['text'=>'NEXUS · Pedido #' . (int)$orderId . ' · nexus-forever.es'],
            'timestamp'=>date('c'),
        ]],
    ];

    $old=trim((string)($order['discord_message_id']??''));
    if ($old!=='') {
        try {
            $edited=discord_edit_channel_message($channelId,$old,$payload);
            $newId=trim((string)($edited['id']??$old));
            $pdo->prepare('UPDATE member_orders SET discord_message_id=? WHERE id=?')->execute([$newId,$orderId]);
            return $newId;
        } catch(NexusDiscordException $e) { if ($e->httpStatus !== 404) throw $e; }
    }
    $created=discord_send_channel_message($channelId,$payload);
    $newId=trim((string)($created['id']??''));
    if($newId==='') throw new RuntimeException('Discord no devolvió el ID del mensaje del pedido.');
    $pdo->prepare('UPDATE member_orders SET discord_message_id=? WHERE id=?')->execute([$newId,$orderId]);
    return $newId;
}

function close_member_order(PDO $pdo, int $orderId, string $discordId): void
{
    $st=$pdo->prepare("SELECT discord_message_id FROM member_orders WHERE id=? AND member_discord_id=? AND status='active' LIMIT 1");
    $st->execute([$orderId,$discordId]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('El pedido no existe o no pertenece a tu cuenta.');
    close_member_order_row($pdo,$orderId,$row,$discordId);
}

function complete_received_order(PDO $pdo, int $orderId, string $discordId): void
{
    $st=$pdo->prepare("SELECT * FROM member_orders WHERE id=? AND recipient_discord_id=? AND status='active' LIMIT 1");
    $st->execute([$orderId,$discordId]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('El pedido no existe, ya está cerrado o no está dirigido a tu cuenta.');
    close_member_order_row($pdo,$orderId,$row,$discordId);
}

function close_member_order_row(PDO $pdo, int $orderId, array $row, string $actorDiscordId): void
{
    $messageId=trim((string)($row['discord_message_id']??''));
    if($messageId!=='') {
        try { discord_delete_channel_message(member_orders_discord_channel_id(),$messageId); } catch(NexusDiscordException $e) {
            if ($e->httpStatus !== 404) throw new RuntimeException('El pedido no se cerró porque Discord no pudo retirar su mensaje: '.$e->getMessage());
        }
    }
    $pdo->prepare("UPDATE member_orders SET status='closed',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='active'")->execute([$orderId]);
}

function profession_discord_channel_id(): string
{
    return trim((string)app_config('discord_profession_channel_id', ''));
}

function profession_discord_payload(PDO $pdo): array
{
$stmt = $pdo->prepare("
        SELECT
            mp.discord_id AS member_discord_id,
            COALESCE(NULLIF(p.member_username,''), NULLIF(m.discord_username,''), mp.name) AS member_username,
            mp.name AS character_name,
            mp.race,
            mp.class_name,
            mp.level,
            p.profession_name,
            p.profession_type,
            p.skill_level,
            p.notes
        FROM member_profiles mp
        LEFT JOIN members m ON m.discord_id = mp.discord_id
        LEFT JOIN professions p
            ON p.member_discord_id = mp.discord_id
           AND p.faction = mp.faction
           AND p.status = 'active'
        WHERE mp.faction = :faction
        ORDER BY mp.name ASC, p.profession_type ASC, p.profession_name ASC
    ");
    $stmt->execute([':faction' => nexus_faction()]);
    $rows = $stmt->fetchAll();

    /*
     * Discord is much easier to read when the directory is organised by
     * character instead of by profession. Each character becomes one field.
     */
    $characters = [];

    foreach ($rows as $row) {
        $key = trim((string)$row['member_discord_id']) . '|' . trim((string)$row['character_name']);
        if ($key === '|') {
            continue;
        }

        if (!isset($characters[$key])) {
            $characters[$key] = [
                'member_discord_id' => trim((string)$row['member_discord_id']),
                'member_username' => trim((string)$row['member_username']),
                'character_name' => trim((string)$row['character_name']),
                'race' => trim((string)$row['race']),
                'class_name' => trim((string)$row['class_name']),
                'level' => (int)($row['level'] ?? 0),
                'primaries' => [],
                'secondaries' => [],
                'notes' => trim((string)($row['notes'] ?? '')),
            ];
        }

        $profession = trim((string)$row['profession_name']);
        if ($profession === '') {
            continue;
        }

        $skill = trim((string)($row['skill_level'] ?? ''));
        $label = '**' . $profession . '**' . ($skill !== '' ? ' — ' . (int)$skill : '');

        if ($row['profession_type'] === 'secondary') {
            $characters[$key]['secondaries'][] = $label;
        } else {
            $characters[$key]['primaries'][] = $label;
        }
    }

    $fields = [];

    foreach ($characters as $character) {
$title = '⚔️ ' . (
    $character['character_name'] !== ''
        ? $character['character_name']
        : 'Personaje'
);

        $meta = [];
        if ($character['race'] !== '') {
            $meta[] = $character['race'];
        }
        if ($character['class_name'] !== '') {
            $meta[] = $character['class_name'];
        }
        if ($character['level'] > 0) {
            $meta[] = 'Nivel ' . $character['level'];
        }

        $value = $meta ? implode(' · ', $meta) . "\n" : '';

        if ($character['primaries']) {
            $value .= "\n🛠️ **Primarias**\n" . implode("\n", array_map(fn($v) => '• ' . $v, $character['primaries']));
        }

        if ($character['secondaries']) {
            $value .= "\n\n📚 **Secundarias**\n" . implode("\n", array_map(fn($v) => '• ' . $v, $character['secondaries']));
        }

        if ($character['notes'] !== '') {
            $value .= "\n\n📝 " . mb_substr($character['notes'], 0, 300);
        }

        if (trim($value) === '') {
            $value = 'Sin profesiones registradas.';
        }

        $fields[] = [
            'name' => $title,
            'value' => mb_substr($value, 0, 1024),
            'inline' => false,
        ];
    }

    if (!$fields) {
        return [
            'content' => "🛠️ **PROFESIONES NEXUS**\nDirectorio actualizado automáticamente desde la web.",
            'embeds' => [[
                'title' => '🛠️ Profesiones NEXUS',
                'description' => 'Todavía no hay profesiones registradas.',
                'color' => 0xD8AD55,
            ]],
        ];
    }

    /* Discord permite hasta 25 fields por embed. */
    $chunks = array_chunk($fields, 5);
    $embeds = [];
    $totalEmbeds = count($chunks);

    foreach ($chunks as $index => $chunk) {
        $embeds[] = [
            'title' => '🛠️ Profesiones NEXUS' . ($totalEmbeds > 1 ? ' · ' . ($index + 1) . '/' . $totalEmbeds : ''),
            'description' => $index === 0
                ? 'Directorio de profesiones de la hermandad. Cada bloque corresponde a un personaje.'
                : 'Continuación del directorio de profesiones.',
            'color' => 0xD8AD55,
            'fields' => $chunk,
            'footer' => [
                'text' => 'NEXUS · Actualizado automáticamente desde nexus-forever.es',
            ],
        ];
    }

    return [
        'content' => "🛠️ **PROFESIONES NEXUS**\nDirectorio actualizado automáticamente desde la web.",
        'embeds' => $embeds,
    ];
}

function sync_professions_to_discord(PDO $pdo): array
{
    $channelId = profession_discord_channel_id();

    if ($channelId === '') {
        throw new RuntimeException('No está configurado discord_profession_channel_id en config/config.php.');
    }

    $payload = profession_discord_payload($pdo);
    $embeds = $payload['embeds'] ?? [];

    /*
     * Discord limita cada mensaje a 10 embeds y a 6000 caracteres totales.
     * Dejamos 5 embeds por mensaje para mantener margen suficiente.
     */
    $payloads = [];
    foreach (array_chunk($embeds, 5) as $index => $embedChunk) {
        $payloads[] = [
            'content' => $index === 0
                ? ($payload['content'] ?? '🛠️ **PROFESIONES NEXUS**')
                : '🛠️ **PROFESIONES NEXUS — continuación**',
            'embeds' => $embedChunk,
        ];
    }

    if (!$payloads) {
        $payloads[] = [
            'content' => $payload['content'] ?? '🛠️ **PROFESIONES NEXUS**',
            'embeds' => [],
        ];
    }

    $state = $pdo->query("SELECT message_id FROM profession_discord_state WHERE id = 1 LIMIT 1")->fetch();
    $stored = trim((string)($state['message_id'] ?? ''));
    $messageIds = [];

    if ($stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            foreach ($decoded as $id) {
                $id = trim((string)$id);
                if ($id !== '') $messageIds[] = $id;
            }
        } else {
            /* Compatibilidad con la versión anterior que guardaba un único ID. */
            $messageIds[] = $stored;
        }
    }

    $newIds = [];
    $created = 0;
    $updated = 0;

    foreach ($payloads as $i => $messagePayload) {
        $existingId = $messageIds[$i] ?? '';

        if ($existingId !== '') {
            try {
                $edited = discord_edit_channel_message($channelId, $existingId, $messagePayload);
                $newIds[] = trim((string)($edited['id'] ?? $existingId));
                $updated++;
                continue;
            } catch (NexusDiscordException $e) { if ($e->httpStatus !== 404) throw $e; }
        }

        $createdMessage = discord_send_channel_message($channelId, $messagePayload);
        $newId = trim((string)($createdMessage['id'] ?? ''));
        if ($newId === '') {
            throw new RuntimeException('Discord no devolvió el ID del mensaje de profesiones.');
        }
        $newIds[] = $newId;
        $created++;
    }

    /* Elimina mensajes antiguos que ya no hacen falta. */
    foreach (array_slice($messageIds, count($payloads)) as $oldId) {
        try { discord_delete_channel_message($channelId, $oldId); } catch (Throwable $e) {}
    }

    $stmt = $pdo->prepare(
        'INSERT INTO profession_discord_state (id, message_id)
         VALUES (1, ?)
         ON DUPLICATE KEY UPDATE message_id = VALUES(message_id)'
    );
    $stmt->execute([json_encode($newIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

    return [
        'ok' => true,
        'created' => $created > 0 && $updated === 0,
        'updated' => $updated > 0,
        'created_count' => $created,
        'updated_count' => $updated,
        'message_ids' => $newIds,
        'channel_id' => $channelId,
    ];
}

/*
|--------------------------------------------------------------------------
| DATOS DE CLASES
|--------------------------------------------------------------------------
*/

function nexus_wow_class_data(string $class): array
{
    $classes = [

        'Guerrero' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_warrior.jpg',
            'color' =>
                0xC79C6E,
            'emoji' =>
                '⚔️'
        ],

        'Cazador' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_hunter.jpg',
            'color' =>
                0xABD473,
            'emoji' =>
                '🏹'
        ],

        'Pícaro' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_rogue.jpg',
            'color' =>
                0xFFF569,
            'emoji' =>
                '🗡️'
        ],

        'Sacerdote' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_priest.jpg',
            'color' =>
                0xFFFFFF,
            'emoji' =>
                '✨'
        ],

        'Chamán' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_shaman.jpg',
            'color' =>
                0x0070DE,
            'emoji' =>
                '🌩️'
        ],

        'Mago' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_mage.jpg',
            'color' =>
                0x69CCF0,
            'emoji' =>
                '🔥'
        ],

        'Brujo' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_warlock.jpg',
            'color' =>
                0x9482C9,
            'emoji' =>
                '☠️'
        ],

        'Paladín' => [
            'icon' => 'https://wow.zamimg.com/images/wow/icons/large/classicon_paladin.jpg',
            'color' => 0xF58CBA,
            'emoji' => '🛡️'
        ],

        'Druida' => [
            'icon' =>
                'https://wow.zamimg.com/images/wow/icons/large/classicon_druid.jpg',
            'color' =>
                0xFF7D0A,
            'emoji' =>
                '🌿'
        ]
    ];

    return $classes[$class] ?? [
        'icon' =>
            'https://wow.zamimg.com/images/wow/icons/large/inv_misc_questionmark.jpg',
        'color' =>
            0x5865F2,
        'emoji' =>
            '❓'
    ];
}


/*
|--------------------------------------------------------------------------
| ICONO DE RAZA
|--------------------------------------------------------------------------
*/

function nexus_race_emoji(string $race): string
{
    return match ($race) {
        'Orco' => '👹',
        'Troll' => '🗿',
        'Tauren' => '🐂',
        'No-muerto' => '💀',
        'Humano' => '🧑',
        'Enano' => '⛏️',
        'Gnomo' => '⚙️',
        'Elfo de la noche' => '🌙',
        'Cielonato' => '🌪️',
        default => '🧬'
    };
}


/*
|--------------------------------------------------------------------------
| ICONO DE ROL
|--------------------------------------------------------------------------
*/

function nexus_role_emoji(string $role): string
{
    return match ($role) {

        'Tanque' =>
            '🛡️',

        'Healer' =>
            '💚',

        'DPS' =>
            '⚔️',

        default =>
            '🎯'
    };
}


/*
|--------------------------------------------------------------------------
| MENSAJE DE INSCRITOS PARA DISCORD
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| Discord permite como máximo 10 embeds por mensaje.
|
| Por eso NO hacemos un embed por jugador.
|
| Hacemos:
|
| 1 embed principal
| + 1 embed por clase
|
| Hay 9 clases -> máximo 10 embeds.
|
| Así pueden existir muchos jugadores de la misma clase
| sin romper el mensaje.
|
*/

function build_discord_event_message(
    PDO $pdo,
    array $event
): array {

    $eventId =
        (int)($event['id'] ?? 0);

    if ($eventId < 1) {

        throw new RuntimeException(
            'Evento no válido.'
        );
    }


    /*
     * Obtener inscritos.
     */

    $stmt =
        $pdo->prepare(
            "SELECT
                member_username,
                race,
                class_name,
                role
             FROM event_attendees
             WHERE event_id = ?
             ORDER BY created_at ASC, id ASC"
        );

    $stmt->execute([
        $eventId
    ]);

    $attendees =
        $stmt->fetchAll();


    $count =
        count($attendees);


    /*
     * Fecha y hora.
     */

    $date =
        nexus_event_date($event);

    $time =
        nexus_event_time($event);

    $dateText =
        $date;

    if ($date !== '') {

        try {

            $dateObject =
                new DateTimeImmutable(
                    $date,
                    new DateTimeZone(
                        (string)app_config(
                            'app_timezone',
                            'Europe/Madrid'
                        )
                    )
                );

            $dateText =
                $dateObject->format(
                    'd/m/Y'
                );

        } catch (Throwable $ignore) {}
    }


    /*
     * URL de la web.
     */

    $appUrl =
        rtrim(
            (string)app_config(
                'app_url',
                'https://www.nexus-forever.es'
            ),
            '/'
        );


    /*
     * Embed principal.
     */

    $mainDescription =
        "📅 **{$dateText}**";

    if ($time !== '') {

        $mainDescription .=
            " · **{$time}**";
    }

    $mainDescription .=
        "\n\n" .
        "👥 **{$count} inscrito" .
        ($count === 1 ? '' : 's') .
        "**";


    $eventDescription =
        nexus_event_description($event);

    if ($eventDescription !== '') {

        $mainDescription .=
            "\n\n📝 **Descripción**\n" .
            mb_substr(
                $eventDescription,
                0,
                700
            );
    }


    $mainDescription .=
        "\n\n🔗 [Ver calendario NEXUS](" .
        $appUrl .
        "/calendario)";


    $embeds = [

        [
            'title' =>
                '🏰 ' .
                mb_substr(
                    nexus_event_title($event),
                    0,
                    240
                ),

            'description' =>
                $mainDescription,

            'color' =>
                0x5865F2,

            'footer' => [
                'text' =>
                    'NEXUS — World of Warcraft Forever'
            ]
        ]
    ];


    /*
     * Sin inscritos.
     */

    if ($count === 0) {

        $embeds[0]['description'] .=
            "\n\n⚪ **Todavía no hay inscritos.**";

        return [
            'embeds' => $embeds
        ];
    }


    /*
     * Agrupar por clase.
     */

    $groups = [];

    $classOrder = [

        'Guerrero',
        'Cazador',
        'Pícaro',
        'Sacerdote',
        'Chamán',
        'Mago',
        'Brujo',
        'Druida'
    ];


    foreach ($attendees as $attendee) {

        $class =
            trim(
                (string)(
                    $attendee['class_name']
                    ?? ''
                )
            );

        if ($class === '') {
            $class = 'Sin clase';
        }

        if (!isset($groups[$class])) {
            $groups[$class] = [];
        }

        $groups[$class][] =
            $attendee;
    }


    /*
     * Primero las clases oficiales en orden.
     */

    $orderedGroups = [];

    foreach ($classOrder as $class) {

        if (isset($groups[$class])) {

            $orderedGroups[$class] =
                $groups[$class];
        }
    }


    /*
     * Cualquier clase desconocida al final.
     */

    foreach ($groups as $class => $members) {

        if (!isset($orderedGroups[$class])) {

            $orderedGroups[$class] =
                $members;
        }
    }


    /*
     * Límite de seguridad para Discord.
     */

    $totalCharacters =
        mb_strlen($mainDescription);


    foreach ($orderedGroups as $class => $members) {

        /*
         * Discord permite máximo 10 embeds.
         *
         * El primero ya es la cabecera.
         */

        if (count($embeds) >= 10) {
            break;
        }


        $classData =
            nexus_wow_class_data(
                $class
            );


        $lines = [];


        foreach ($members as $member) {

            $username =
                trim(
                    (string)(
                        $member['member_username']
                        ?? 'Jugador'
                    )
                );

            $race =
                trim(
                    (string)(
                        $member['race']
                        ?? ''
                    )
                );

            $role =
                trim(
                    (string)(
                        $member['role']
                        ?? ''
                    )
                );


            $line =
                "🟢 **" .
                $username .
                "**";


            $details = [];


            if ($race !== '') {

                $details[] =
                    nexus_race_emoji($race) .
                    ' ' .
                    $race;
            }


            if ($role !== '') {

                $details[] =
                    nexus_role_emoji($role) .
                    ' ' .
                    $role;
            }


            if ($details) {

                $line .=
                    ' — ' .
                    implode(
                        ' · ',
                        $details
                    );
            }


            $lines[] =
                $line;
        }


        $groupDescription =
            implode(
                "\n",
                $lines
            );


        /*
         * Evitar sobrepasar el límite total
         * de caracteres de embeds de Discord.
         */

        $remainingBudget =
            5400 -
            $totalCharacters;


        if ($remainingBudget < 300) {
            break;
        }


        if (
            mb_strlen($groupDescription)
            >
            $remainingBudget
        ) {

            $groupDescription =
                mb_substr(
                    $groupDescription,
                    0,
                    max(
                        100,
                        $remainingBudget - 50
                    )
                ) .
                "\n…";
        }


        $classTitle =
            $class;


        if ($class !== 'Sin clase') {

            $classTitle =
                $classData['emoji'] .
                ' ' .
                $class;
        } else {

            $classTitle =
                '❓ Sin clase';
        }


        $embeds[] = [

            'title' =>
                $classTitle .
                ' · ' .
                count($members),

            'description' =>
                $groupDescription,

            'color' =>
                $classData['color'],

            'thumbnail' => [

                'url' =>
                    $classData['icon']
            ]
        ];


        $totalCharacters +=
            mb_strlen(
                $groupDescription
            );
    }


    /*
     * Si se alcanzó el límite de embeds,
     * indicarlo en la cabecera.
     */

    if (
        count($embeds) >= 10 &&
        count($orderedGroups) > 9
    ) {

        $embeds[0]['description'] .=
            "\n\n⚠️ Algunas categorías adicionales no se muestran en este mensaje.";
    }


    return [

        'embeds' =>
            $embeds
    ];
}


/*
|--------------------------------------------------------------------------
| WEB → DISCORD
|--------------------------------------------------------------------------
*/

function sync_web_event_to_discord(PDO $pdo, int $eventId, ?callable $api = null): array
{
    return nexus_discord_lock($pdo, 'event-message:' . $eventId, fn() => nexus_sync_event_message_unlocked($pdo, $eventId, $api));
}

function nexus_sync_event_message_unlocked(
    PDO $pdo,
    int $eventId,
    ?callable $api = null
): array {
    $api ??= 'discord_api';

    if ($eventId < 1) {

        throw new RuntimeException(
            'Evento no válido.'
        );
    }


    $stmt =
        $pdo->prepare(
            'SELECT *
             FROM events
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $eventId
    ]);

    $event =
        $stmt->fetch();

    if (!$event) {

        throw new RuntimeException(
            'No se encontró el evento.'
        );
    }


    // Canal de texto de Discord donde se publica/actualiza el evento.
    // El fallback evita que una instalación antigua sin esta clave
    // deje de sincronizar los eventos.
    $channelId =
        trim(
            (string)app_config(
                'discord_event_channel_id',
                ''
            )
        );

    if ($channelId === '') {

        throw new RuntimeException(
            'No está configurado discord_event_channel_id en config/config.php.'
        );
    }


    $payload =
        build_discord_event_message(
            $pdo,
            $event
        );


    $messageId =
        trim(
            (string)(
                $event['discord_message_id']
                ?? ''
            )
        );


    if ($messageId !== '') {

        try {

            $message =
                $api('PATCH', 'https://discord.com/api/v10/channels/' . rawurlencode($channelId) . '/messages/' . rawurlencode($messageId), $payload);


            try {

                $stmt =
                    $pdo->prepare(
                        'UPDATE events
                         SET discord_sync_error = NULL
                         WHERE id = ?'
                    );

                $stmt->execute([
                    $eventId
                ]);

            } catch (Throwable $ignore) {}


            return [

                'ok' =>
                    true,

                'created' =>
                    false,

                'updated' =>
                    true,

                'message_id' =>
                    $message['id']
                    ?? $messageId,

                'channel_id' =>
                    $channelId
            ];

        } catch (NexusDiscordException $e) {
            if ($e->httpStatus !== 404) throw $e;
        }
    }


    $message =
        $api('POST', 'https://discord.com/api/v10/channels/' . rawurlencode($channelId) . '/messages', $payload);


    $newMessageId =
        trim(
            (string)(
                $message['id']
                ?? ''
            )
        );


    if ($newMessageId === '') {

        throw new RuntimeException(
            'Discord no devolvió el ID del mensaje.'
        );
    }


    $stmt =
        $pdo->prepare(
            'UPDATE events
             SET discord_message_id = ?,
                 discord_sync_error = NULL
             WHERE id = ?'
        );

    $stmt->execute([
        $newMessageId,
        $eventId
    ]);


    return [

        'ok' =>
            true,

        'created' =>
            true,

        'updated' =>
            false,

        'message_id' =>
            $newMessageId,

        'channel_id' =>
            $channelId
    ];
}


/*
|--------------------------------------------------------------------------
| DISCORD → WEB
|--------------------------------------------------------------------------
*/

function sync_discord_event(
    PDO $pdo,
    int $eventId,
    bool $force = false,
    ?callable $api = null
): array {
    $api ??= 'discord_api';

    if ($eventId < 1) {

        throw new RuntimeException(
            'Evento no válido.'
        );
    }


    $stmt =
        $pdo->prepare(
            'SELECT *
             FROM events
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $eventId
    ]);

    $event =
        $stmt->fetch();

    if (!$event) {

        throw new RuntimeException(
            'No se encontró el evento.'
        );
    }


    $discordEventId = trim((string)($event['discord_event_id'] ?? ''));
    if ($discordEventId === '') $discordEventId = nexus_sync_scheduled_event($pdo, $eventId, $api)['discord_event_id'];

    $guildId =
        trim(
            (string)app_config(
                'discord_guild_id',
                ''
            )
        );


    if ($guildId === '') {

        throw new RuntimeException(
            'No está configurado discord_guild_id.'
        );
    }


    $users =
        discord_list_event_users(
            $guildId,
            $discordEventId,
            $api
        );


    $imported = 0;
    $pdo->beginTransaction();
    try {
    $pdo->prepare('UPDATE event_attendees SET discord_interested=0 WHERE event_id=?')->execute([$eventId]);


    foreach ($users as $user) {

        if (!is_array($user)) {
            continue;
        }


        $discordId =
            (string)(
                $user['user']['id']
                ??
                $user['id']
                ??
                ''
            );


        $username =
            (string)(
                $user['user']['global_name']
                ??
                $user['user']['username']
                ??
                $user['global_name']
                ??
                $user['username']
                ??
                ''
            );


        if ($discordId === '') {
            continue;
        }


        if ($username === '') {
            $username = 'Discord';
        }


        $check =
            $pdo->prepare(
                'SELECT id
                 FROM event_attendees
                 WHERE event_id = ?
                   AND member_discord_id = ?
                 LIMIT 1'
            );

        $check->execute([
            $eventId,
            $discordId
        ]);


        $existing =
            $check->fetch();


        if ($existing) {

            try {

                $stmt =
                    $pdo->prepare(
                        'UPDATE event_attendees
                         SET member_username = ?,
                             discord_interested = 1
                         WHERE id = ?'
                    );

                $stmt->execute([
                    $username,
                    $existing['id']
                ]);

            } catch (Throwable $e) {

                $stmt =
                    $pdo->prepare(
                        'UPDATE event_attendees
                         SET member_username = ?
                         WHERE id = ?'
                    );

                $stmt->execute([
                    $username,
                    $existing['id']
                ]);
            }

        } else {

            $stmt =
                $pdo->prepare(
                    'INSERT INTO event_attendees
                    (
                        event_id,
                        member_discord_id,
                        member_username,
                        registered_via_web,
                        discord_interested
                    )
                    VALUES (?, ?, ?, 0, 1)'
                );

            $stmt->execute([
                $eventId,
                $discordId,
                $username
            ]);
        }


        $imported++;
    }


    $pdo->prepare('DELETE FROM event_attendees WHERE event_id=? AND registered_via_web=0 AND discord_interested=0 AND attended IS NULL')->execute([$eventId]);
    $pdo->prepare('UPDATE events SET discord_last_synced_at=NOW() WHERE id=?')->execute([$eventId]);
    $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $messageSync = null;


    try {

        $messageSync =
            sync_web_event_to_discord(
                $pdo,
                $eventId,
                $api
            );

    } catch (Throwable $e) {

        try {

            $stmt =
                $pdo->prepare(
                    'UPDATE events
                     SET discord_sync_error = ?
                     WHERE id = ?'
                );

            $stmt->execute([
                mb_substr(
                    $e->getMessage(),
                    0,
                    500
                ),
                $eventId
            ]);

        } catch (Throwable $ignore) {}


        $messageSync = [

            'ok' =>
                false,

            'error' =>
                $e->getMessage()
        ];
    }


    return [

        'ok' =>
            true,

        'event_id' =>
            $eventId,

        'discord_event_id' =>
            $discordEventId,

        'imported' =>
            $imported,

        'discord_message' =>
            $messageSync
    ];
}
require_once __DIR__ . '/../includes/discord-integration.php';
