-- Historial de reconocimientos: no elimina datos ni modifica personajes.
CREATE TABLE IF NOT EXISTS member_awards (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        member_discord_id VARCHAR(32) NOT NULL,
        profile_id INT UNSIGNED NOT NULL,
        faction VARCHAR(16) NOT NULL,
        character_name VARCHAR(120) NOT NULL,
        title VARCHAR(120) NOT NULL,
        description TEXT NULL,
        icon VARCHAR(20) NOT NULL DEFAULT 'trophy',
        source VARCHAR(20) NOT NULL DEFAULT 'manual',
        source_key VARCHAR(160) NOT NULL,
        awarded_at DATETIME NOT NULL,
        granted_by VARCHAR(120) NULL,
        revoked_at DATETIME NULL,
        revoked_by VARCHAR(120) NULL,
        UNIQUE KEY uq_member_award_source (source_key),
        KEY idx_member_award_history (member_discord_id,faction,awarded_at),
        KEY idx_member_award_profile (profile_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
