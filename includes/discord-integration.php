<?php
declare(strict_types=1);

class NexusDiscordException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }
}

/** Transporte común: plazos acotados y errores útiles, sin revelar el token. */
function nexus_discord_request(string $method, string $url, ?array $payload = null, ?string $token = null, ?callable $transport = null): array
{
    $token = trim($token ?? (string)app_config('discord_bot_token', ''));
    if ($token === '') throw new NexusDiscordException('Falta configurar discord_bot_token.');
    if (!preg_match('~^https://discord\.com/api/v10/~', $url)) throw new NexusDiscordException('Destino de la API de Discord no permitido.');
    $method = strtoupper($method);
    if ($payload !== null && preg_match('~/messages(?:/[^/?]+)?(?:\?|$)~', $url)) $payload['allowed_mentions'] ??= ['parse' => []];
    $perform = $transport ?? static function (string $method, string $url, ?array $payload, string $token): array {
        if (!function_exists('curl_init')) throw new NexusDiscordException('El servidor necesita PHP cURL para conectar con Discord.');
        $handle = curl_init($url);
        if ($handle === false) throw new NexusDiscordException('No se pudo iniciar la conexión con Discord.');
        $headers = ['Authorization: Bot ' . $token, 'Accept: application/json', 'User-Agent: NEXUS-Guild/2.0'];
        $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($handle, $options);
        try {
            $body = curl_exec($handle);
            if ($body === false) throw new NexusDiscordException('No se pudo confirmar la operación con Discord. Reintenta desde el panel; no vuelvas a crear el contenido.');
            return ['status' => (int)curl_getinfo($handle, CURLINFO_HTTP_CODE), 'data' => $body === '' ? [] : json_decode($body, true)];
        } finally { curl_close($handle); }
    };
    $result = $perform($method, $url, $payload, $token);
    $status = (int)($result['status'] ?? 0);
    $data = $result['data'] ?? [];
    if ($status >= 200 && $status < 300) {
        if (!is_array($data)) throw new NexusDiscordException('Discord devolvió una respuesta no válida.', $status);
        return $data;
    }
    $message = match ($status) {
        400 => 'Discord rechazó los datos. Comprueba fecha futura, hora, canal y descripción.',
        401 => 'El token del bot de Discord no es válido o ha caducado.',
        403 => 'El bot no tiene permisos en Discord. Revisa Crear/Gestionar eventos, Ver canal, Enviar mensajes e Insertar enlaces según la operación.',
        404 => 'El evento, canal o mensaje ya no existe, o el bot no tiene acceso.',
        429 => 'Discord ha limitado las peticiones. Espera ' . max(1, (int)ceil((float)($data['retry_after'] ?? 10))) . ' segundos antes de reintentar.',
        default => 'Discord no está disponible. La información permanece guardada en la web.',
    };
    throw new NexusDiscordException('Discord API HTTP ' . $status . ': ' . $message, $status);
}

function nexus_discord_event_payload(array $event, bool $includeDates = true): array
{
    $payload = ['name' => mb_substr(nexus_event_title($event), 0, 100), 'privacy_level' => 2];
    $base = rtrim((string)app_config('app_url', 'https://www.nexus-forever.es'), '/');
    $link = $base . '/calendario#evento-' . (int)($event['id'] ?? 0);
    $payload['description'] = mb_substr(nexus_event_description($event), 0, 800) . "\n\nMás información: " . $link;
    if (!$includeDates) return $payload;
    $date = nexus_event_date($event);
    $time = nexus_event_time($event);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time)) throw new NexusDiscordException('El evento se ha guardado en la web. Para publicarlo en Eventos de Discord indica una fecha y una hora válidas.');
    $start = event_start_utc($event);
    if ($start->setTimezone(app_timezone())->format('Y-m-d H:i') !== $date . ' ' . substr($time, 0, 5)) throw new NexusDiscordException('La fecha u hora del evento no es válida en la zona horaria configurada.');
    if ($start->getTimestamp() <= time() + 60) throw new NexusDiscordException('Discord necesita una fecha futura, al menos un minuto después de ahora.');
    $duration = max(15, min(1440, (int)app_config('discord_event_duration_minutes', 120)));
    $payload['scheduled_start_time'] = $start->format('Y-m-d\TH:i:s\Z');
    $payload['scheduled_end_time'] = $start->modify('+' . $duration . ' minutes')->format('Y-m-d\TH:i:s\Z');
    $voice = trim((string)app_config('discord_voice_channel_id', ''));
    if ($voice !== '') {
        $payload['entity_type'] = 2;
        $payload['channel_id'] = $voice;
        $payload['entity_metadata'] = null;
    } else {
        // Discord también admite eventos externos, sin depender de un canal de voz.
        $payload['entity_type'] = 3;
        $payload['channel_id'] = null;
        $payload['entity_metadata'] = ['location' => mb_substr((string)app_config('discord_event_location', 'World of Warcraft Forever — NEXUS'), 0, 100)];
    }
    return $payload;
}

function nexus_discord_guild_url(): string
{
    $guild = trim((string)app_config('discord_guild_id', ''));
    if (!preg_match('/^\d{5,25}$/', $guild)) throw new NexusDiscordException('Configura el ID del servidor en discord_guild_id.');
    return 'https://discord.com/api/v10/guilds/' . $guild;
}

function nexus_discord_lock(PDO $pdo, string $resource, callable $work)
{
    $name = 'nexus:' . substr(hash('sha256', NEXUS_ROOT . ':' . $resource), 0, 48);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() !== 1) throw new NexusDiscordException('Ya se está sincronizando este contenido. Reintenta en unos segundos.');
    try { return $work(); }
    finally { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]); }
}

function nexus_sync_scheduled_event(PDO $pdo, int $eventId, ?callable $api = null): array
{
    $api ??= 'discord_api';
    return nexus_discord_lock($pdo, 'scheduled-event:' . $eventId, function () use ($pdo, $eventId, $api) {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) throw new NexusDiscordException('El evento no existe en la web.');
        $guildUrl = nexus_discord_guild_url();
        $remoteId = trim((string)($event['discord_event_id'] ?? ''));
        $remote = null;
        if ($remoteId !== '') {
            try { $remote = $api('GET', $guildUrl . '/scheduled-events/' . rawurlencode($remoteId)); }
            catch (NexusDiscordException $error) { if ($error->httpStatus !== 404) throw $error; $remoteId = ''; }
        }
        $created = false;
        if ($remoteId === '') {
            $payload = nexus_discord_event_payload($event);
            // Recuperar un evento cuyo POST se completó pero cuya respuesta se perdió.
            $marker = rtrim((string)app_config('app_url', 'https://www.nexus-forever.es'), '/') . '/calendario#evento-' . $eventId;
            foreach ($api('GET', $guildUrl . '/scheduled-events') as $candidate) {
                if (!empty($candidate['id']) && (int)($candidate['status'] ?? 0) === 1 && preg_match('~' . preg_quote($marker, '~') . '(?=$|\\s)~u', (string)($candidate['description'] ?? ''))) { $remote = $candidate; $remoteId = (string)$candidate['id']; break; }
            }
            if ($remoteId === '') {
                $remote = $api('POST', $guildUrl . '/scheduled-events', $payload);
                $remoteId = (string)($remote['id'] ?? '');
                $created = true;
            }
            if ($remoteId === '') throw new NexusDiscordException('Discord no devolvió el ID del evento programado.');
            $pdo->prepare('UPDATE events SET discord_event_id = ?, discord_event_url = ? WHERE id = ?')->execute([$remoteId, 'https://discord.com/events/' . app_config('discord_guild_id') . '/' . $remoteId, $eventId]);
        }
        if (!$created) {
            $remoteStatus = (int)($remote['status'] ?? 1);
            if (in_array($remoteStatus, [3, 4], true)) throw new NexusDiscordException('El evento de Discord ya ha terminado o se ha cancelado. Crea un evento nuevo para otra fecha.');
            $payload = nexus_discord_event_payload($event, $remoteStatus === 1);
            $api('PATCH', $guildUrl . '/scheduled-events/' . rawurlencode($remoteId), $payload);
            $pdo->prepare('UPDATE events SET discord_event_url = ? WHERE id = ?')->execute(['https://discord.com/events/' . app_config('discord_guild_id') . '/' . $remoteId, $eventId]);
        }
        return ['ok' => true, 'created' => $created, 'discord_event_id' => $remoteId, 'url' => 'https://discord.com/events/' . app_config('discord_guild_id') . '/' . $remoteId];
    });
}

/** Intentar las dos publicaciones independientemente; conservar siempre el evento web. */
function nexus_publish_event(PDO $pdo, int $eventId, ?callable $api = null): array
{
    $results = [];
    foreach (['scheduled' => fn() => nexus_sync_scheduled_event($pdo, $eventId, $api), 'message' => fn() => sync_web_event_to_discord($pdo, $eventId, $api)] as $key => $work) {
        try { $results[$key] = $work(); }
        catch (Throwable $error) { $results[$key] = ['ok' => false, 'error' => $error->getMessage()]; }
    }
    $errors = [];
    foreach ($results as $key => $result) if (empty($result['ok'])) $errors[] = ($key === 'scheduled' ? 'Eventos de Discord: ' : 'Canal de eventos: ') . ($result['error'] ?? 'No disponible');
    $error = $errors ? mb_substr(implode(' | ', $errors), 0, 500) : null;
    $pdo->prepare('UPDATE events SET discord_sync_error = ?, discord_last_synced_at = NOW() WHERE id = ?')->execute([$error, $eventId]);
    return ['ok' => !$errors, ...$results, 'error' => $error];
}

function discord_delete_event(string $eventId): array
{
    try { return discord_api('DELETE', nexus_discord_guild_url() . '/scheduled-events/' . rawurlencode($eventId)); }
    catch (NexusDiscordException $error) { if ($error->httpStatus !== 404) throw $error; return []; }
}

function nexus_validate_event_input(string $date, string $time, string $title, string $type, string $description): void
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, app_timezone());
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Indica una fecha válida.');
    if ($time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) throw new InvalidArgumentException('Indica una hora válida.');
    if ($title === '' || mb_strlen($title) > 180 || mb_strlen($type) > 80 || mb_strlen($description) > 5000) throw new InvalidArgumentException('Revisa el título (1–180 caracteres), el tipo y la descripción (hasta 5000 caracteres).');
}

function nexus_media_discord_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS discord_media_publications (
        media_id INT UNSIGNED NOT NULL PRIMARY KEY,
        channel_id VARCHAR(32) NULL, message_id VARCHAR(32) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending', attempts INT UNSIGNED NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function nexus_media_discord_channel(): string
{
    return trim((string)(app_config('discord_gallery_channel_id', '') ?: app_config('discord_event_channel_id', '')));
}

function nexus_publish_media(PDO $pdo, int $mediaId, ?callable $api = null): array
{
    $api ??= 'discord_api';
    nexus_media_discord_schema($pdo);
    return nexus_discord_lock($pdo, 'media:' . $mediaId, function () use ($pdo, $mediaId, $api) {
        $stmt = $pdo->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$media || $media['type'] !== 'image') throw new NexusDiscordException('Solo se pueden publicar imágenes existentes de la galería.');
        $channel = nexus_media_discord_channel();
        $pdo->prepare('INSERT IGNORE INTO discord_media_publications (media_id) VALUES (?)')->execute([$mediaId]);
        $state = $pdo->prepare('SELECT * FROM discord_media_publications WHERE media_id = ?');
        $state->execute([$mediaId]);
        $stored = $state->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE discord_media_publications SET attempts=attempts+1, status='pending' WHERE media_id=?")->execute([$mediaId]);
        try {
            if (!preg_match('/^\d{5,25}$/', $channel)) throw new NexusDiscordException('Configura discord_gallery_channel_id o discord_event_channel_id.');
            if (!preg_match('~^/img/uploads/[a-f0-9]{24}\.(?:jpg|png|webp|gif)$~', (string)$media['url'])) throw new NexusDiscordException('La imagen no pertenece a la carpeta de subidas permitida.');
            $base = rtrim((string)app_config('app_url', 'https://www.nexus-forever.es'), '/');
            if (!preg_match('~^https://[^/]+$~', $base)) throw new NexusDiscordException('Para publicar imágenes configura app_url con el dominio HTTPS público.');
            $imageUrl = $base . $media['url'];
            $payload = ['embeds' => [[
                'title' => mb_substr((string)$media['title'], 0, 200),
                'description' => mb_substr((string)($media['description'] ?? ''), 0, 1000),
                'url' => $base . '/galeria',
                'color' => 0xD7AD58,
                'image' => ['url' => $imageUrl],
                'footer' => ['text' => 'NEXUS · ' . mb_substr((string)($media['category'] ?? 'Galería'), 0, 120)],
            ]], 'allowed_mentions' => ['parse' => []]];
            $messageId = (string)($stored['message_id'] ?? '');
            $storedChannel = (string)($stored['channel_id'] ?? '');
            // Actualizar el mensaje ya vinculado aunque el canal por defecto cambie después.
            if ($messageId !== '' && $storedChannel !== '') $channel = $storedChannel;
            $url = 'https://discord.com/api/v10/channels/' . $channel . '/messages';
            if ($messageId === '' && (int)($stored['attempts'] ?? 0) > 0) {
                foreach ($api('GET', $url . '?limit=100') as $candidate) {
                    foreach ($candidate['embeds'] ?? [] as $embed) {
                        if (($embed['image']['url'] ?? '') === $imageUrl) { $messageId = (string)$candidate['id']; break 2; }
                    }
                }
            }
            if ($messageId !== '') {
                try { $api('PATCH', $url . '/' . $messageId, $payload); }
                catch (NexusDiscordException $error) { if ($error->httpStatus !== 404) throw $error; $messageId = ''; }
            }
            if ($messageId === '') {
                $payload['nonce'] = substr(hash('sha256', NEXUS_ROOT), 0, 10) . '-' . $mediaId;
                $payload['enforce_nonce'] = true;
                $response = $api('POST', $url, $payload);
                $messageId = (string)($response['id'] ?? '');
            }
            if ($messageId === '') throw new NexusDiscordException('Discord no devolvió el ID del mensaje de la imagen.');
            $pdo->prepare("UPDATE discord_media_publications SET channel_id=?, message_id=?, status='published', last_error=NULL WHERE media_id=?")->execute([$channel, $messageId, $mediaId]);
            return ['ok' => true, 'message_id' => $messageId];
        } catch (Throwable $error) {
            $pdo->prepare("UPDATE discord_media_publications SET status='pending', last_error=? WHERE media_id=?")->execute([mb_substr($error->getMessage(), 0, 500), $mediaId]);
            throw $error;
        }
    });
}

function nexus_remove_media_discord(PDO $pdo, int $mediaId, ?callable $api = null): void
{
    nexus_media_discord_schema($pdo);
    $api ??= 'discord_api';
    nexus_discord_lock($pdo, 'media:' . $mediaId, function () use ($pdo, $mediaId, $api) {
        $stmt = $pdo->prepare('SELECT * FROM discord_media_publications WHERE media_id=?');
        $stmt->execute([$mediaId]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$state) return;
        try {
            if (!empty($state['message_id']) && !empty($state['channel_id'])) {
                try { $api('DELETE', 'https://discord.com/api/v10/channels/' . rawurlencode((string)$state['channel_id']) . '/messages/' . rawurlencode((string)$state['message_id'])); }
                catch (NexusDiscordException $error) { if ($error->httpStatus !== 404) throw $error; }
            }
            $pdo->prepare("UPDATE discord_media_publications SET status='deleted', last_error=NULL WHERE media_id=?")->execute([$mediaId]);
        } catch (Throwable $error) {
            $pdo->prepare("UPDATE discord_media_publications SET status='delete_pending', attempts=attempts+1, last_error=? WHERE media_id=?")->execute([mb_substr($error->getMessage(),0,500), $mediaId]);
            throw $error;
        }
    });
}

function nexus_safe_return_to(string $path): string
{
    $decoded = rawurldecode(rawurldecode($path));
    if ($decoded === '' || $decoded[0] !== '/' || str_starts_with($decoded, '//') || preg_match('/[\\\\\x00-\x20\x7f]/', $decoded) || preg_match('~^/auth(?:/|$)~i', $decoded)) return '/panel';
    return $path;
}

function nexus_delete_web_event(PDO $pdo, int $eventId): void
{
    nexus_discord_lock($pdo,'delete-event:'.$eventId,function()use($pdo,$eventId){
        $stmt=$pdo->prepare('SELECT * FROM events WHERE id=?'); $stmt->execute([$eventId]); $event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event) throw new InvalidArgumentException('El evento no existe.');
        // Si Discord falla, el registro web se conserva para poder reintentar la retirada.
        if(!empty($event['discord_event_id'])) discord_delete_event((string)$event['discord_event_id']);
        if(!empty($event['discord_message_id'])){
            $channel=trim((string)app_config('discord_event_channel_id',''));
            if($channel==='') throw new NexusDiscordException('Configura el canal de eventos antes de retirar su mensaje.');
            try{discord_delete_channel_message($channel,(string)$event['discord_message_id']);}catch(NexusDiscordException $error){if($error->httpStatus!==404)throw $error;}
        }
        $pdo->beginTransaction();
        try{$pdo->prepare('DELETE FROM event_attendees WHERE event_id=?')->execute([$eventId]);$pdo->prepare('DELETE FROM events WHERE id=?')->execute([$eventId]);$pdo->commit();}
        catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    });
}
