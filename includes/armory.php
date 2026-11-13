<?php
declare(strict_types=1);
require_once __DIR__ . '/seo.php';

function nexus_armory_slug(string $name): string
{
    $slug = mb_strtolower((string)preg_replace('/\s+/u', '-', trim($name)), 'UTF-8');
    if (mb_strlen($slug) > 100 || !preg_match('/^[\p{L}\p{M}\d]+(?:-[\p{L}\p{M}\d]+)+$/u', $slug)) throw new InvalidArgumentException('Indica el nombre completo del personaje, incluido el apellido.');
    return $slug;
}

/** Solo perfiles de miembros registrados y verificados en la web; nunca importa todo el roster de Blizzard. */
function nexus_armory_members(?PDO $pdo = null): array
{
    if ($pdo === null && !nexus_is_configured()) return [];
    try {
        $pdo ??= db();
        $stmt=$pdo->prepare('SELECT mp.id,mp.name FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1 WHERE mp.faction=? ORDER BY mp.name');
        $stmt->execute([nexus_faction()]);
        $profiles=$stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $error) { return []; }
    $bindings=[];
    try { foreach($pdo->query('SELECT profile_id,slug,name,active FROM armory_characters')->fetchAll(PDO::FETCH_ASSOC)as$row)$bindings[(int)$row['profile_id']]=$row; }
    catch(Throwable $ignored) {}
    $result=[];
    foreach($profiles as $profile){
        $name=(string)$profile['name']; $slug=''; $display=$name;
        $binding=$bindings[(int)$profile['id']]??null;
        if($binding){
            if((int)$binding['active']===1){$slug=(string)$binding['slug'];$display=(string)$binding['name'];}
        }else{
            // Nombre confirmado por el propietario, solo si existe su perfil registrado en la web.
            if(mb_strtolower(trim($name))==='melkir')$display='Melkir Vossgrave';
            try{$slug=nexus_armory_slug($display);}catch(InvalidArgumentException $ignored){}
        }
        $result[]=['profile_id'=>(int)$profile['id'],'name'=>$name,'display_name'=>$display,'slug'=>$slug,'unavailable'=>$binding&&(int)$binding['active']===0];
    }
    return $result;
}

function nexus_armory_characters(): array
{
    $characters=[];
    foreach(nexus_armory_members() as $profile)if($profile['slug']!=='')$characters[$profile['slug']]=$profile['display_name'];
    return $characters;
}

function nexus_armory_official_url(string $slug): string
{
    return 'https://worldofwarcraft.blizzard.com/es-es/forever/eu/armory/character/' . rawurlencode(nexus_armory_slug($slug));
}

function nexus_armory_image($url): string
{
    return is_string($url) && preg_match('~^https://render\.worldofwarcraft\.com/[A-Za-z0-9_./%-]+$~', $url) ? $url : '';
}

/** Leer datos JSON de la página pública; nunca ejecutar el JavaScript recibido. */
function nexus_armory_parse(string $html, string $slug): array
{
    if (!class_exists('DOMDocument')) throw new RuntimeException('El servidor necesita la extensión PHP DOM/XML para leer la armería.');
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $script = $document->getElementById('character-profile-mount-initial-state');
    if (!$script || !preg_match('/^\s*var\s+characterProfileInitialState\s*=\s*(\{.*\})\s*;?\s*$/s', $script->textContent, $match)) throw new RuntimeException('La página oficial no ofrece el perfil en el formato esperado. Consulta la armería oficial.');
    $data = json_decode($match[1], true, 128, JSON_THROW_ON_ERROR);
    $character = $data['character'] ?? null;
    if (!is_array($character) || ($character['slug'] ?? '') !== $slug || !is_array($character['gear'] ?? null)) throw new RuntimeException('Blizzard no ha publicado todavía el equipo de este personaje.');
    $guild = mb_strtolower((string)app_config('armory_guild_slug', 'nexusforever'));
    if (mb_strtolower((string)($character['guild']['slug'] ?? '')) !== $guild) throw new RuntimeException('El perfil oficial no figura como miembro de la hermandad configurada. Revisa nombre, apellido y hermandad.');
    if (($character['faction']['enum'] ?? '') !== 'HORDE') throw new RuntimeException('El perfil oficial no pertenece a la Horda.');
    $updated=(string)($character['lastUpdatedTimestamp']['iso8601']??'');
    try { if($updated!=='')new DateTimeImmutable($updated); } catch(Exception $ignored){$updated='';}
    $result = [
        'slug' => $slug,
        'name' => mb_substr(trim(($character['name'] ?? '') . ' ' . ($character['surname'] ?? '')), 0, 120),
        'level' => (int)($character['level'] ?? 0),
        'class' => mb_substr((string)($character['class']['name'] ?? ''),0,80),
        'race' => mb_substr((string)($character['race']['name'] ?? ''),0,80),
        'faction' => 'Horda',
        'guild_slug' => $guild,
        'guild' => mb_substr((string)($character['guild']['name'] ?? ''),0,120),
        'item_level' => (int)($character['averageItemLevel'] ?? 0),
        'updated' => $updated,
        'portrait' => nexus_armory_image($character['render']['foreground']['url'] ?? $character['media']['main-raw']['url'] ?? ''),
        'avatar' => nexus_armory_image($character['avatar']['url'] ?? ''),
        'equipment' => [],
    ];
    foreach (array_slice($character['gear'], 0, 24, true) as $slot => $item) {
        if (!is_array($item)) continue;
        $icon = '';
        foreach ($item['media']['content']['assets'] ?? [] as $asset) if (($asset['key'] ?? '') === 'icon') $icon = nexus_armory_image($asset['value'] ?? '');
        $stats = [];
        foreach (array_slice($item['stats'] ?? [],0,12) as $stat) if (!empty($stat['display']['display_string'])) $stats[] = mb_substr((string)$stat['display']['display_string'],0,200);
        $enchants = [];
        foreach (array_slice($item['enchantments'] ?? [],0,6) as $enchant) if (!empty($enchant['display_string'])) $enchants[] = mb_substr((string)$enchant['display_string'],0,200);
        $rarity = strtoupper((string)($item['quality']['type'] ?? 'COMMON'));
        if (!in_array($rarity,['POOR','COMMON','UNCOMMON','RARE','EPIC','LEGENDARY','ARTIFACT','HEIRLOOM'],true)) $rarity = 'COMMON';
        $result['equipment'][] = ['slot' => mb_substr((string)($item['slot']['name'] ?? $slot),0,80), 'name' => mb_substr((string)($item['name'] ?? 'Objeto'),0,200), 'id' => (int)($item['id'] ?? 0), 'icon' => $icon, 'rarity' => strtolower($rarity), 'level' => (int)($item['level']['value'] ?? 0), 'armor' => (int)($item['armor']['value'] ?? 0), 'stats' => $stats, 'enchants' => $enchants];
    }
    return $result;
}

function nexus_armory_fetch(string $url): string
{
    if (!function_exists('curl_init')) throw new RuntimeException('El servidor necesita PHP cURL para consultar la armería.');
    $body = '';
    $handle = curl_init($url);
    curl_setopt_array($handle, [CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'NEXUS-Guild-Armory/1.0', CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_WRITEFUNCTION => static function ($handle,string $chunk) use (&$body): int { if (strlen($body)+strlen($chunk)>3145728) return 0; $body.=$chunk; return strlen($chunk); }]);
    try {
        $ok=curl_exec($handle); $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
        if ($ok===false || $status!==200) throw new RuntimeException($status===404 ? 'El personaje aún no tiene un perfil público en Blizzard. Comprueba nombre y apellido, su nivel y si ha cerrado sesión en el juego.' : 'La armería oficial no está disponible en este momento.');
        return $body;
    } finally { curl_close($handle); }
}

/** Caché y bloqueo por personaje: las visitas no generan peticiones continuas a Blizzard. */
function nexus_armory_profile(string $slug, ?callable $fetch = null): array
{
    $slug = nexus_armory_slug($slug);
    $folder = sys_get_temp_dir() . '/nexus-armory-' . substr(hash('sha256', NEXUS_ROOT),0,20);
    if (is_link($folder) || (!is_dir($folder) && !@mkdir($folder,0700,true))) throw new RuntimeException('No se puede acceder a la caché de la armería.');
    $file = $folder . '/' . hash('sha256',$slug) . '.json';
    if (is_link($file)) throw new RuntimeException('No se puede acceder a la caché de la armería.');
    $handle = @fopen($file,'c+');
    if (!$handle) throw new RuntimeException('No se puede acceder a la caché de la armería.');
    @chmod($file,0600);
    try {
        if (!flock($handle,LOCK_EX)) throw new RuntimeException('La armería está ocupada. Reintenta en unos segundos.');
        $state = json_decode((string)stream_get_contents($handle,524288),true) ?: [];
        $now=time();
        if(isset($state['profile']) && (($state['profile']['guild_slug']??'')!==mb_strtolower((string)app_config('armory_guild_slug','nexusforever')) || ($state['profile']['faction']??'')!=='Horda'))$state=[];
        if (!empty($state['profile']) && (int)($state['fetched']??0)>$now-600) return [...$state['profile'],'cached_at'=>$state['fetched'],'stale'=>false];
        if ((int)($state['attempted']??0)<=$now-120) {
            try {
                $profile=nexus_armory_parse(($fetch??'nexus_armory_fetch')(nexus_armory_official_url($slug)),$slug);
                $state=['profile'=>$profile,'fetched'=>$now,'attempted'=>$now];
            } catch (Throwable $error) { $state['attempted']=$now; $state['error']=$error->getMessage(); }
            $encoded=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            rewind($handle);ftruncate($handle,0);fwrite($handle,$encoded);fflush($handle);
        }
        if (!empty($state['profile']) && (int)($state['fetched']??0)>$now-86400) return [...$state['profile'],'cached_at'=>$state['fetched'],'stale'=>!empty($state['error'])];
        throw new RuntimeException($state['error']??'Todavía no hay una copia disponible del personaje.');
    } finally { flock($handle,LOCK_UN);fclose($handle); }
}
