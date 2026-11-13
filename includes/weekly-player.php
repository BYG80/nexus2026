<?php
declare(strict_types=1);
require_once __DIR__ . '/armory.php';

function nexus_weekly_icons(): array
{
    return [
        'trophy' => ['label' => 'Trofeo', 'symbol' => '🏆'],
        'swords' => ['label' => 'Combate', 'symbol' => '⚔️'],
        'shield' => ['label' => 'Protección', 'symbol' => '🛡️'],
        'flame' => ['label' => 'Proeza', 'symbol' => '🔥'],
        'star' => ['label' => 'Estrella', 'symbol' => '⭐'],
    ];
}

/** La portada usa la imagen verificada al guardar: no consulta Blizzard en cada visita. */
function nexus_weekly_player(?PDO $pdo = null): ?array
{
    if ($pdo === null && !nexus_is_configured()) return null;
    try {
        $pdo ??= db();
        $stmt = $pdo->prepare('SELECT value FROM nexus_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['player_of_week']);
        $player = json_decode((string)$stmt->fetchColumn(), true);
        if (!is_array($player) || empty($player['profile_id']) || !is_string($player['name'] ?? null) || !is_string($player['slug'] ?? null) || !is_string($player['portrait'] ?? null)) return null;
        if (nexus_armory_image($player['portrait']) === '' || !isset(nexus_weekly_icons()[$player['icon'] ?? ''])) return null;
        if (($player['guild_slug'] ?? '') !== mb_strtolower((string)app_config('armory_guild_slug', 'nexusforever')) || ($player['faction'] ?? '') !== 'Horda') return null;
        $stmt = $pdo->prepare('SELECT mp.id FROM member_profiles mp INNER JOIN members m ON m.discord_id = mp.discord_id AND m.is_member = 1 WHERE mp.id = ? AND mp.faction = ? LIMIT 1');
        $stmt->execute([(int)$player['profile_id'], nexus_faction()]);
        if (!$stmt->fetchColumn()) return null;
        $player['feat'] = is_string($player['feat'] ?? null) ? $player['feat'] : '';
        $player['description'] = is_string($player['description'] ?? null) ? $player['description'] : '';
        return $player;
    } catch (Throwable $error) {
        return null;
    }
}

/** Asociar el nombre introducido con un miembro de la web sin pedir otro selector al administrador. */
function nexus_weekly_member(PDO $pdo, string $slug): array
{
    $exact = [];
    $firstName = [];
    foreach (nexus_armory_members($pdo) as $member) {
        if (!empty($member['unavailable'])) continue;
        $registered = mb_strtolower((string)preg_replace('/[\s_-]+/u', '-', trim($member['name'])), 'UTF-8');
        if ($member['slug'] === $slug || $registered === $slug) $exact[] = $member;
        elseif (!str_contains($registered, '-') && $registered === explode('-', $slug, 2)[0]) $firstName[] = $member;
    }
    $matches = $exact ?: $firstName;
    if (count($matches) !== 1) throw new InvalidArgumentException(count($matches) > 1
        ? 'Hay varios perfiles con ese nombre. Completa su nombre y apellido en Mi panel o vincúlalo desde Admin → Armería.'
        : 'No se ha encontrado un personaje de un miembro verificado de la web con ese nombre. Revisa Mi panel o Admin → Armería.');
    return $matches[0];
}

function nexus_weekly_save(PDO $pdo, string $name, string $feat, string $description, string $icon, ?callable $loadProfile = null): array
{
    foreach ([$name, $feat, $description] as $text) if (!mb_check_encoding($text, 'UTF-8')) throw new InvalidArgumentException('El texto introducido no es válido.');
    $slug = nexus_armory_slug($name);
    $feat = trim($feat);
    $description = trim($description);
    if (mb_strlen($feat) > 120 || mb_strlen($description) > 700) throw new InvalidArgumentException('La hazaña admite hasta 120 caracteres y el detalle hasta 700.');
    if (!isset(nexus_weekly_icons()[$icon])) throw new InvalidArgumentException('Selecciona uno de los iconos disponibles.');
    $member = nexus_weekly_member($pdo, $slug);
    $profile = ($loadProfile ?? 'nexus_armory_profile')($slug);
    if (($profile['slug'] ?? '') !== $slug || ($profile['guild_slug'] ?? '') !== mb_strtolower((string)app_config('armory_guild_slug', 'nexusforever')) || ($profile['faction'] ?? '') !== 'Horda') throw new InvalidArgumentException('El perfil oficial debe confirmar la identidad, la Horda y la hermandad NexusForever.');
    if (!empty($profile['stale'])) throw new RuntimeException('No se ha podido actualizar el perfil de Blizzard. Reintenta más tarde; el jugador anterior sigue publicado.');
    $portrait = nexus_armory_image($profile['portrait'] ?? '');
    if ($portrait === '') throw new RuntimeException('Blizzard aún no muestra una imagen de cuerpo completo para este personaje. Reintenta cuando su armería esté actualizada.');
    $player = [
        'profile_id' => $member['profile_id'],
        'slug' => $slug,
        'name' => (string)$profile['name'],
        'portrait' => $portrait,
        'guild_slug' => $profile['guild_slug'],
        'faction' => 'Horda',
        'feat' => $feat,
        'description' => $description,
        'icon' => $icon,
        'selected_at' => gmdate('c'),
    ];
    $stmt = $pdo->prepare('INSERT INTO nexus_settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute(['player_of_week', json_encode($player, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    return $player;
}

function nexus_weekly_clear(PDO $pdo): void
{
    $pdo->prepare('DELETE FROM nexus_settings WHERE setting_key = ?')->execute(['player_of_week']);
}
