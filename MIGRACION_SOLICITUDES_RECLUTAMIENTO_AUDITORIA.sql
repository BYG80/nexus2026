-- NEXUS: soporte para estado visto/aceptado/cancelado e historial de gestión.
-- Las páginas reclutamiento.php y Admin/recruitment.php ejecutan estas comprobaciones automáticamente.
-- Este script es opcional; no borra solicitudes ni reinstala la base de datos.
ALTER TABLE recruitment_applications MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new';
ALTER TABLE recruitment_applications ADD COLUMN viewed_at DATETIME NULL;
ALTER TABLE recruitment_applications ADD COLUMN updated_at DATETIME NULL;
ALTER TABLE recruitment_applications ADD COLUMN updated_by VARCHAR(120) NULL;
CREATE TABLE IF NOT EXISTS recruitment_application_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_id INT UNSIGNED NOT NULL,
 old_status VARCHAR(20) NULL, new_status VARCHAR(20) NOT NULL, changed_by VARCHAR(120) NOT NULL,
 changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, note VARCHAR(255) NULL,
 PRIMARY KEY(id), KEY idx_recruitment_history_app(application_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
