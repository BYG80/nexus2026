CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 username VARCHAR(80) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','editor') NOT NULL DEFAULT 'admin',
 discord_id VARCHAR(32) NULL,
 discord_username VARCHAR(120) NULL,
 discord_avatar VARCHAR(255) NULL,
 is_member TINYINT(1) NOT NULL DEFAULT 0,
 member_role_name VARCHAR(120) NULL,
 verified_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 event_date DATE NOT NULL,
 event_time TIME NULL,
 title VARCHAR(180) NOT NULL,
 type VARCHAR(80) NOT NULL DEFAULT 'Evento',
 description TEXT NULL,
 discord_event_id VARCHAR(32) NULL,
 discord_event_url VARCHAR(500) NULL,
 discord_last_synced_at DATETIME NULL,
 discord_sync_error VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_events_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roster (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(80) NOT NULL,
 race VARCHAR(80) NOT NULL DEFAULT '',
 faction ENUM('horda','alianza') NOT NULL DEFAULT 'horda',
 class_name VARCHAR(80) NOT NULL DEFAULT '',
 class_color VARCHAR(20) NOT NULL DEFAULT '#d8ad55',
 level SMALLINT UNSIGNED NULL,
 role VARCHAR(40) NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 notes VARCHAR(255) NULL,
 roster_group ENUM('A','B','20','40') NOT NULL DEFAULT 'A',
 discord_id VARCHAR(32) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_roster_name (name), KEY idx_roster_discord (discord_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 published TINYINT(1) NOT NULL DEFAULT 1,
 published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_news_published (published,published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 type ENUM('image','youtube') NOT NULL,
 title VARCHAR(180) NOT NULL,
 description TEXT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'General',
 url VARCHAR(500) NOT NULL,
 thumbnail_url VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_media_type (type),
 KEY idx_media_category (category),
 KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achievements (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(180) NOT NULL,
 description TEXT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'General',
 icon_url VARCHAR(500) NULL,
 achievement_date DATE NULL,
 published TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_ach_date (achievement_date), KEY idx_ach_pub (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_attendees (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 event_id INT UNSIGNED NOT NULL,
 member_discord_id VARCHAR(32) NOT NULL,
 character_id INT UNSIGNED NULL,
 member_username VARCHAR(120) NOT NULL,
 race VARCHAR(80) NOT NULL DEFAULT '',
 faction ENUM('horda','alianza') NOT NULL DEFAULT 'horda',
 class_name VARCHAR(80) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 registered_via_web TINYINT(1) NOT NULL DEFAULT 1,
 discord_interested TINYINT(1) NOT NULL DEFAULT 0,
 discord_interested_at DATETIME NULL,
 attended TINYINT(1) NULL DEFAULT NULL,
 attendance_source ENUM('manual','discord_voice','unknown') NULL,
 attendance_at DATETIME NULL,
 synced_at DATETIME NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_event_member (event_id,member_discord_id),
 KEY idx_attendees_event (event_id),
 KEY idx_attendees_discord (member_discord_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PROFESIONES DE LA HERMANDAD
-- ============================================================
CREATE TABLE IF NOT EXISTS professions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 member_discord_id VARCHAR(32) NULL,
 member_username VARCHAR(120) NULL,
 character_name VARCHAR(80) NOT NULL,
 race VARCHAR(80) NULL,
 faction ENUM('horda','alianza') NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profession_discord_state (
 id TINYINT UNSIGNED NOT NULL,
 message_id VARCHAR(64) NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO profession_discord_state (id) VALUES (1);


-- ============================================================
-- SISTEMA RSP NEXUS · HORDA · WOW FOREVER
-- ============================================================

CREATE TABLE IF NOT EXISTS dkp_rules (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 code VARCHAR(60) NOT NULL,
 label VARCHAR(120) NOT NULL,
 amount INT NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_dkp_rules_code (code),
 KEY idx_dkp_rules_active (active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dkp_ledger (
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
 PRIMARY KEY (id),
 UNIQUE KEY uq_dkp_source (source_key),
 KEY idx_dkp_member (member_discord_id,created_at),
 KEY idx_dkp_event (event_id),
 KEY idx_dkp_roster (roster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dkp_rules (code,label,amount,active,sort_order) VALUES
 ('raid_attendance','Asistencia a raid',10,1,10),
 ('raid_ontime','Puntualidad',5,1,20),
 ('full_consumables','Preparación completa (frasco + comida + consumibles)',10,1,30),
 ('flask','Frasco',3,1,40),
 ('food','Comida / festín',3,1,50),
 ('potions','Pociones',2,1,60),
 ('oil_sharpening','Aceites / afiladores',2,1,70),
 ('materials','Materiales / utilidades de raid',2,1,80),
 ('extra_help','Ayuda extra a la raid',5,1,90),
 ('mentor','Mentor de clase',20,1,100),
 ('guide','Guía general',10,1,110),
 ('pvp_premade','PvP Premade',20,1,120),
 ('pvp_bg_group','BG en grupo de 3 a 5 miembros',5,1,130)
ON DUPLICATE KEY UPDATE label=VALUES(label);


CREATE TABLE IF NOT EXISTS member_orders (
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
 PRIMARY KEY (id), KEY idx_member_orders_member (member_discord_id,status), KEY idx_member_orders_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
