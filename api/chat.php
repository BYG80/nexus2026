<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/ai-chat.php';
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
start_secure_session();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $history=(int)($_SESSION['nexus_ai_updated']??0)>time()-1800?($_SESSION['nexus_ai_history']??[]):[];
    if(!$history)unset($_SESSION['nexus_ai_history'],$_SESSION['nexus_ai_updated']);
    json_response(['available'=>nexus_chat_available(),'csrf'=>csrf_token(),'history'=>$history]);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(['error' => 'Método no permitido.'], 405);
}
$pending = false;
$status = 200;
try {
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') throw new NexusChatException('Recarga la página antes de continuar.', 403);
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN']);
        $originHost = strtolower(($origin['host'] ?? '') . (isset($origin['port']) ? ':' . $origin['port'] : ''));
        if (!in_array($origin['scheme'] ?? '', ['http', 'https'], true) || $originHost !== strtolower($_SERVER['HTTP_HOST'] ?? '')) throw new NexusChatException('Origen no autorizado.', 403);
    }
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') throw new NexusChatException('Envía el mensaje en formato JSON.', 415);
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) throw new NexusChatException('El mensaje es demasiado largo.', 413);
    $body = json_decode($raw, true);
    if (!is_array($body)) throw new NexusChatException('El mensaje no es válido.', 400);
    if (!csrf_check(is_string($body['csrf'] ?? null) ? $body['csrf'] : null)) throw new NexusChatException('La sesión ha caducado. Cierra y abre el chat para continuar.', 403);
    if ((int)($_SESSION['nexus_ai_pending'] ?? 0) > time() - 40) throw new NexusChatException('Espera a que termine la respuesta anterior.', 409);
    if (($body['action'] ?? '') === 'clear') {
        unset($_SESSION['nexus_ai_history'], $_SESSION['nexus_ai_updated']);
        json_response(['ok' => true]);
    }
    $message = nexus_chat_validate_message($body['message'] ?? null);
    if (!nexus_chat_available()) throw new NexusChatException('El asistente IA todavía no está activo. Puedes contactar con la hermandad en Discord.');
    $now = time();
    $times = array_values(array_filter($_SESSION['nexus_ai_times'] ?? [], fn($time) => is_int($time) && $time > $now - 600));
    if (count($times) >= 8) throw new NexusChatException('Has enviado muchas preguntas. Espera unos minutos.', 429, max(1, $times[0] + 600 - $now));
    nexus_chat_take_quota((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $now);
    $times[] = $now;
    $_SESSION['nexus_ai_times'] = $times;
    $history = (int)($_SESSION['nexus_ai_updated'] ?? 0) > $now - 1800 ? ($_SESSION['nexus_ai_history'] ?? []) : [];
    $_SESSION['nexus_ai_pending'] = $now;
    $pending = true;
    session_write_close();
    $reply = nexus_chat_reply($history, $message, nexus_guild_context());
    start_secure_session();
    $_SESSION['nexus_ai_history'] = array_slice([...$history, ['role' => 'user', 'content' => $message], ['role' => 'assistant', 'content' => $reply]], -10);
    $_SESSION['nexus_ai_updated'] = time();
    $result = ['reply' => $reply];
} catch (NexusChatException $error) {
    $status = $error->status;
    if ($error->retryAfter) header('Retry-After: ' . $error->retryAfter);
    $result = ['error' => $error->getMessage()];
} catch (Throwable $error) {
    $status = 503;
    $result = ['error' => 'El asistente no está disponible en este momento. Prueba más tarde o consulta Discord.'];
} finally {
    if ($pending) {
        start_secure_session();
        unset($_SESSION['nexus_ai_pending']);
    }
}
json_response($result, $status);
