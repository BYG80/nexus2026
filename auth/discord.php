<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (!discord_configured()) { http_response_code(503); exit('El acceso con Discord aún no está configurado en el servidor.'); }
$callback = discord_redirect_uri();
$parts = parse_url($callback);
if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) { http_response_code(503); exit('La URL de retorno de Discord debe ser una URL HTTPS válida.'); }
$callbackHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== $callbackHost) {
    header('Location: ' . $callback . ($_GET ? '?' . http_build_query($_GET) : ''), true, 302);
    exit;
}
start_secure_session();
$returnTo = nexus_safe_return_to(is_string($_GET['return_to'] ?? null) ? $_GET['return_to'] : '/panel');
$code = is_string($_GET['code'] ?? null) ? trim($_GET['code']) : '';
$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
if ($code === '' && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(32));
    $_SESSION['discord_oauth_state'] = $state;
    $_SESSION['discord_oauth_created'] = time();
    $_SESSION['discord_oauth_return_to'] = $returnTo;
    header('Location: https://discord.com/oauth2/authorize?' . http_build_query([
        'client_id' => app_config('discord_client_id'), 'redirect_uri' => $callback,
        'response_type' => 'code', 'scope' => 'identify', 'state' => $state,
    ]), true, 302);
    exit;
}
if ($state === '' || !hash_equals((string)($_SESSION['discord_oauth_state'] ?? ''), $state) || (int)($_SESSION['discord_oauth_created'] ?? 0) < time() - 600) {
    http_response_code(400); exit('La sesión de acceso ha caducado o no es válida. Vuelve a iniciar sesión desde la web.');
}
$returnTo = nexus_safe_return_to((string)($_SESSION['discord_oauth_return_to'] ?? '/panel'));
unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_created'], $_SESSION['discord_oauth_return_to']);
if (isset($_GET['error'])) { http_response_code(400); exit('Has cancelado el acceso con Discord. Puedes volver a intentarlo desde la web.'); }

function nexus_oauth_http(string $url, array $headers, ?array $form = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL no disponible.');
    $handle = curl_init($url);
    $options = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false];
    if ($form !== null) { $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=http_build_query($form); }
    curl_setopt_array($handle,$options);
    try {
        $body=curl_exec($handle); $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
        $data=$body===false?null:json_decode($body,true);
        if ($body===false || $status!==200 || !is_array($data)) throw new RuntimeException('Discord no ha completado la autenticación.');
        return $data;
    } finally { curl_close($handle); }
}
try {
    $token=nexus_oauth_http('https://discord.com/api/oauth2/token',['Content-Type: application/x-www-form-urlencoded'],[
        'client_id'=>app_config('discord_client_id'),'client_secret'=>app_config('discord_client_secret'),
        'grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$callback,
    ]);
    if (empty($token['access_token'])) throw new RuntimeException('Token OAuth no válido.');
    $user=nexus_oauth_http('https://discord.com/api/v10/users/@me',['Authorization: Bearer '.$token['access_token']]);
    unset($token);
    $id=(string)($user['id']??'');
    if (!preg_match('/^\d{5,25}$/',$id)) throw new RuntimeException('Cuenta de Discord no válida.');
    $role=discord_member_role_info($id);
    if (!$role) {
        unset($_SESSION['member_discord_id'],$_SESSION['member_username'],$_SESSION['member_avatar'],$_SESSION['member_role']);
        http_response_code(403); exit('Tu cuenta debe pertenecer al servidor de NEXUS y tener el rol de miembro configurado. Consulta a los oficiales en Discord.');
    }
    $username=mb_substr((string)($user['global_name']??$user['username']??'Discord'),0,100);
    $avatarHash=(string)($user['avatar']??'');
    $avatar=preg_match('/^(?:a_)?[a-f0-9]+$/',$avatarHash)?'https://cdn.discordapp.com/avatars/'.$id.'/'.$avatarHash.'.png':null;
    $pdo=db();
    $pdo->prepare('INSERT INTO members (discord_id,discord_username,discord_avatar,is_member,member_role_name,verified_at) VALUES (?,?,?,1,?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE discord_username=VALUES(discord_username),discord_avatar=VALUES(discord_avatar),is_member=1,member_role_name=VALUES(member_role_name),verified_at=CURRENT_TIMESTAMP')->execute([$id,$username,$avatar,$role['name']]);
    session_regenerate_id(true);
    $_SESSION['member_discord_id']=$id; $_SESSION['member_username']=$username;
    $_SESSION['member_avatar']=$avatar; $_SESSION['member_role']=$role['name'];
    header('Location: '.$returnTo,true,302); exit;
} catch (Throwable $error) {
    error_log('NEXUS OAuth: autenticación no completada; '.$error::class);
    http_response_code(503); exit('No se ha podido completar el acceso con Discord. Inténtalo más tarde o consulta con la administración.');
}
