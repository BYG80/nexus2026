-- NEXUS: roster compatible con Raid 10 A/B, Raid 20 y Raid 40.
-- Se usa VARCHAR(2) en lugar de ENUM para evitar que instalaciones MySQL
-- antiguas rechacen los nuevos valores 20/40.
ALTER TABLE roster MODIFY roster_group VARCHAR(2) NOT NULL DEFAULT 'A';
UPDATE roster SET roster_group='A' WHERE roster_group IS NULL OR roster_group NOT IN ('A','B','20','40');
