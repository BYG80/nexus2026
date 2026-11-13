<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/seo.php';

function nexus_guild_info(bool $refresh = false): array
{
    static $info = null;
    if ($info !== null && !$refresh) return $info;
    $info = require NEXUS_ROOT . '/config/guild-info.php';
    if (nexus_is_configured()) {
        try {
            $stmt = db()->prepare('SELECT value FROM nexus_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute(['ai_guild_info']);
            $saved = json_decode((string)$stmt->fetchColumn(), true);
            if (is_array($saved)) $info = array_replace($info, array_intersect_key($saved, $info));
        } catch (Throwable $error) { /* La información pública del archivo sigue disponible. */ }
    }
    return $info;
}

function nexus_guild_validate(array $input): array
{
    $limits = ['discord_invite' => 200, 'raid_schedule' => 1200, 'raid_timezone' => 80, 'raid_voice_channel' => 150, 'recruitment' => 1500, 'extra_info' => 3000];
    $values = [];
    foreach ($limits as $key => $limit) {
        if (!isset($input[$key]) || !is_string($input[$key])) throw new InvalidArgumentException('Falta el campo ' . $key . '.');
        $values[$key] = trim($input[$key]);
        if (mb_strlen($values[$key], 'UTF-8') > $limit) throw new InvalidArgumentException('El campo ' . $key . ' es demasiado largo.');
    }
    if (!preg_match('~^https://(?:discord\.gg/[A-Za-z0-9-]+|discord\.com/invite/[A-Za-z0-9-]+)/?$~', $values['discord_invite'])) throw new InvalidArgumentException('Usa un enlace de invitación HTTPS válido de Discord.');
    if (!in_array($values['raid_timezone'], DateTimeZone::listIdentifiers(), true)) throw new InvalidArgumentException('La zona horaria no es válida. Usa, por ejemplo, Europe/Madrid.');
    return $values;
}

function nexus_guild_context(): array
{
    $context = [
        'today' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d'),
        'guild' => 'NEXUS — Veteranos de Azeroth',
        'in_game_guild' => (string)app_config('guild_ingame_name', 'NexusForever'),
        'game' => 'World of Warcraft Forever',
        'language' => 'español',
        'faction' => nexus_faction_label(),
        'information' => nexus_guild_info(),
        'rules' => 'Respeto: sin insultos, acoso ni discriminación. Ayuda entre miembros. Para raids: puntualidad, preparación y comunicación. En Discord: sin spam y respetando sus normas.',
        'rsp' => 'NEXUS utiliza RSP para recompensar la participación. La tabla vigente se consulta en la página Sistema RSP.',
        'links' => [],
        'upcoming_events' => [],
        'latest_news' => [],
    ];
    foreach (['/reclutamiento','/calendario','/normas','/rsp','/roster','/noticias','/hermandad-wow-forever'] as $path) $context['links'][$path] = nexus_canonical($path);
    // Solo datos ya públicos; nunca cuentas Discord, asistentes, solicitudes ni mensajes privados.
    if (nexus_is_configured()) {
        try {
            $stmt = db()->prepare('SELECT title, event_date, event_time, type FROM events WHERE event_date >= ? AND event_date <= ? ORDER BY event_date, event_time LIMIT 5');
            $stmt->execute([$context['today'], (new DateTimeImmutable($context['today']))->modify('+30 days')->format('Y-m-d')]);
            $context['upcoming_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rows = db()->query('SELECT title, body FROM news WHERE published = 1 ORDER BY published_at DESC, id DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) $context['latest_news'][] = ['title' => mb_substr((string)$row['title'], 0, 200), 'summary' => mb_substr(html_entity_decode(strip_tags((string)$row['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 400)];
        } catch (Throwable $error) { /* No inventar eventos si no se puede consultar la base de datos. */ }
    }
    foreach ($context['upcoming_events'] as &$event) $event['title'] = mb_substr((string)$event['title'], 0, 200);
    unset($event);
    return $context;
}
