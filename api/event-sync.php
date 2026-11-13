<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
start_secure_session();
if(empty($_SESSION['admin_user'])) json_response(['error'=>'No autorizado'],401);
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['error'=>'Método no permitido'],405);
$eventId=(int)($_POST['event_id']??0); if($eventId<1) json_response(['error'=>'Evento no válido'],400);
try { json_response(sync_discord_event(db(),$eventId,true)); } catch(Throwable $e) { json_response(['error'=>$e->getMessage()],500); }
