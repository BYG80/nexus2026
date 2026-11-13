-- NEXUS RSP / PVP
-- Ejecutar si la instalación existente no pasa por bootstrap.php automáticamente.
INSERT INTO dkp_rules (code,label,amount,active,sort_order) VALUES
 ('mentor','Mentor de clase',20,1,100),
 ('guide','Guía general',10,1,110),
 ('pvp_premade','PvP Premade',20,1,120),
 ('pvp_bg_group','BG en grupo de 3 a 5 miembros',5,1,130)
ON DUPLICATE KEY UPDATE label=VALUES(label), amount=VALUES(amount), active=1, sort_order=VALUES(sort_order);
