-- NEXUS WoW Forever — migración de facción global
-- Segura para ejecutar varias veces.
CREATE TABLE IF NOT EXISTS nexus_settings (
  setting_key VARCHAR(80) NOT NULL,
  value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO nexus_settings (setting_key,value) VALUES ('faction','horda');

ALTER TABLE roster ADD COLUMN faction ENUM('horda','alianza') NOT NULL DEFAULT 'horda';
ALTER TABLE professions ADD COLUMN faction ENUM('horda','alianza') NULL AFTER race;
ALTER TABLE event_attendees ADD COLUMN faction ENUM('horda','alianza') NULL AFTER race;

UPDATE roster SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche');
UPDATE roster SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto');
UPDATE professions SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche');
UPDATE professions SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto','Cielonato') AND (faction IS NULL OR faction='');
UPDATE event_attendees SET faction='alianza' WHERE race IN ('Humano','Enano','Gnomo','Elfo de la noche');
UPDATE event_attendees SET faction='horda' WHERE race IN ('Orco','Troll','Tauren','No-muerto','Cielonato') AND (faction IS NULL OR faction='');

ALTER TABLE members ADD COLUMN IF NOT EXISTS is_member TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE members ADD COLUMN IF NOT EXISTS member_role_name VARCHAR(120) NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL;
