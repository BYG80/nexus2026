<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

function nexus_award_icons(): array
{
    return [
        'trophy' => ['label'=>'Trofeo','symbol'=>'🏆'],
        'swords' => ['label'=>'Combate','symbol'=>'⚔️'],
        'shield' => ['label'=>'Protección','symbol'=>'🛡️'],
        'flame' => ['label'=>'Proeza','symbol'=>'🔥'],
        'star' => ['label'=>'Estrella','symbol'=>'⭐'],
    ];
}

function nexus_awards_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS member_awards (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function nexus_award_profile(PDO $pdo, int $profileId): array
{
    $stmt=$pdo->prepare('SELECT mp.id,mp.name,mp.discord_id,mp.faction FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1 WHERE mp.id=? AND mp.faction=? LIMIT 1');
    $stmt->execute([$profileId,nexus_faction()]);
    $profile=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$profile)throw new InvalidArgumentException('Selecciona un personaje de un miembro verificado de la hermandad.');
    return $profile;
}

function nexus_award_record(PDO $pdo, int $profileId, string $title, string $description, string $icon, string $source, string $sourceKey, ?DateTimeImmutable $date=null, bool $update=false): int
{
    $profile=nexus_award_profile($pdo,$profileId);
    $title=trim($title);$description=trim($description);
    if(!mb_check_encoding($title.$description,'UTF-8')||$title===''||mb_strlen($title)>120||mb_strlen($description)>700)throw new InvalidArgumentException('Indica un título de hasta 120 caracteres y un detalle de hasta 700.');
    if(!isset(nexus_award_icons()[$icon]))throw new InvalidArgumentException('Elige un icono válido.');
    if(!in_array($source,['manual','weekly'],true)||strlen($sourceKey)>160)throw new InvalidArgumentException('Origen del reconocimiento no válido.');
    $stamp=($date??new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    if($source==='manual'){
        $existing=$pdo->prepare('SELECT id,profile_id,title,description,icon,awarded_at FROM member_awards WHERE source_key=?');$existing->execute([$sourceKey]);$saved=$existing->fetch(PDO::FETCH_ASSOC);
        if($saved){
            if((int)$saved['profile_id']!==$profileId||$saved['title']!==$title||(string)$saved['description']!==$description||$saved['icon']!==$icon||$saved['awarded_at']!==$stamp)throw new InvalidArgumentException('Este formulario ya se utilizó. Recarga la página para otorgar otro reconocimiento.');
            return (int)$saved['id'];
        }
    }
    $sql='INSERT INTO member_awards (member_discord_id,profile_id,faction,character_name,title,description,icon,source,source_key,awarded_at,granted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';
    if($update)$sql.=',title=VALUES(title),description=VALUES(description),icon=VALUES(icon),character_name=VALUES(character_name),revoked_at=NULL,revoked_by=NULL';
    $pdo->prepare($sql)->execute([$profile['discord_id'],$profileId,$profile['faction'],$profile['name'],$title,$description,$icon,$source,$sourceKey,$stamp,mb_substr((string)($_SESSION['admin_user']??'Administración'),0,120)]);
    return (int)$pdo->lastInsertId();
}

function nexus_award_weekly(PDO $pdo,array $player,bool $update=true): int
{
    $date=new DateTimeImmutable((string)($player['selected_at']??'now'));
    $week=$date->setTimezone(app_timezone())->format('o-\WW');
    return nexus_award_record($pdo,(int)$player['profile_id'],(string)(($player['feat']??'')?:'Jugador de la semana'),(string)($player['description']??''),(string)$player['icon'],'weekly','weekly:'.(int)$player['profile_id'].':'.$week,$date,$update);
}

/** Recuperar el reconocimiento actual creado antes de disponer de historial, sin duplicarlo ni reactivarlo. */
function nexus_awards_import_current(PDO $pdo): void
{
    $stmt=$pdo->prepare('SELECT value FROM nexus_settings WHERE setting_key=?');$stmt->execute(['player_of_week']);
    $player=json_decode((string)$stmt->fetchColumn(),true);
    if(!is_array($player)||empty($player['profile_id'])||!isset($player['icon'])||!is_string($player['selected_at']??null))return;
    try{nexus_award_weekly($pdo,$player,false);}catch(InvalidArgumentException $ignored){}
}

function nexus_member_awards(PDO $pdo,string $discordId,string $faction): array
{
    try{
        $stmt=$pdo->prepare('SELECT id,character_name,title,description,icon,source,awarded_at FROM member_awards WHERE member_discord_id=? AND faction=? AND revoked_at IS NULL ORDER BY awarded_at DESC,id DESC');
        $stmt->execute([$discordId,$faction]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(PDOException $error){return [];}
}

function nexus_award_revoke(PDO $pdo,int $id,bool $restore=false): void
{
    $stmt=$pdo->prepare('SELECT id FROM member_awards WHERE id=?');$stmt->execute([$id]);
    if(!$stmt->fetchColumn())throw new InvalidArgumentException('El reconocimiento no existe.');
    $sql=$restore?'UPDATE member_awards SET revoked_at=NULL,revoked_by=NULL WHERE id=?':'UPDATE member_awards SET revoked_at=UTC_TIMESTAMP(),revoked_by=? WHERE id=?';
    $pdo->prepare($sql)->execute($restore?[$id]:[mb_substr((string)($_SESSION['admin_user']??'Administración'),0,120),$id]);
}
