-- Ejecutar una sola vez en la base de datos de NEXUS antes de subir los archivos.
-- Añade el selector de dos rosters sin eliminar los personajes existentes (todos pasan a Roster A).
ALTER TABLE roster ADD COLUMN roster_group ENUM('A','B','20','40') NOT NULL DEFAULT 'A' AFTER notes;

-- La tabla recruitment_applications se crea automáticamente desde reclutamiento.php y Admin/recruitment.php.
-- Si el usuario MySQL no tiene permisos CREATE, ejecutar también el CREATE TABLE de esas páginas como administrador de base de datos.
