-- NEXUS RSP / Miembros Discord
-- Seguro para instalaciones existentes. Ejecutar una vez en phpMyAdmin si se desea migrar manualmente.
CREATE TABLE IF NOT EXISTS nexus_settings (setting_key VARCHAR(80) NOT NULL,value TEXT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO nexus_settings(setting_key,value) VALUES ('faction','horda');
ALTER TABLE members ADD COLUMN IF NOT EXISTS is_member TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE members ADD COLUMN IF NOT EXISTS member_role_name VARCHAR(120) NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL;


-- RSP NEXUS: mentoría, guías y PvP
INSERT INTO dkp_rules (code,label,amount,active,sort_order) VALUES
 ('mentor','Mentor de clase',20,1,100),
 ('guide','Guía general',10,1,110),
 ('pvp_premade','PvP Premade',20,1,120),
 ('pvp_bg_group','BG en grupo de 3 a 5 miembros',5,1,130)
ON DUPLICATE KEY UPDATE label=VALUES(label), amount=VALUES(amount), sort_order=VALUES(sort_order);
