<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
function require_admin_api(): void { start_secure_session(); if (empty($_SESSION['admin_user'])) json_response(['error'=>'No autorizado'],401); }
$method=$_SERVER['REQUEST_METHOD'];
try {
    if (!nexus_is_configured()) {
        if ($method !== 'GET') require_admin_api();
        $file=__DIR__.'/../datos/eventos.json';
        $events=is_file($file)?json_decode(file_get_contents($file),true):[];
        $events=is_array($events)?$events:[];
        start_secure_session();
        $discordId=(string)($_SESSION['member_discord_id']??'');
        $attendeesFile=__DIR__.'/../datos/inscripciones.json';
        $attendees=is_file($attendeesFile)?json_decode((string)file_get_contents($attendeesFile),true):[];
        $attendees=is_array($attendees)?$attendees:[];
        foreach($events as &$event){
            $event['id']=(int)($event['id']??0);
            $count=0; $mine=null;
            foreach($attendees as $a){
                if((int)($a['event_id']??0)===$event['id']){
                    $count++;
                    if($discordId && (string)($a['member_discord_id']??'')===$discordId) $mine=$a;
                }
            }
            $event['attendee_count']=$count;
            $event['user_registered']=!!$mine;
            $event['user_race']=$mine['race']??'';
            $event['user_class']=$mine['class_name']??'';
        }
        unset($event);
        json_response($events);
    }
    $pdo=db();
    if ($method==='GET') { start_secure_session(); $discordId=(string)($_SESSION['member_discord_id']??''); $rows=$pdo->query("SELECT e.id,e.event_date AS date,e.event_time AS time,e.title,e.type,e.description,e.discord_event_url,(SELECT COUNT(*) FROM event_attendees a WHERE a.event_id=e.id) AS attendee_count,(SELECT COUNT(*) FROM event_attendees a WHERE a.event_id=e.id AND a.attended=1) AS attended_count FROM events e ORDER BY event_date,event_time,id")->fetchAll(); if($discordId){$q=$pdo->prepare('SELECT event_id,race,class_name FROM event_attendees WHERE member_discord_id=?');$q->execute([$discordId]);$mine=[];foreach($q->fetchAll() as $r)$mine[(int)$r['event_id']]=$r;foreach($rows as &$row){$m=$mine[(int)$row['id']]??null;$row['user_registered']=!!$m;$row['user_race']=$m['race']??'';$row['user_class']=$m['class_name']??'';}unset($row);} json_response($rows); }
    require_admin_api();
    $input=json_decode(file_get_contents('php://input'),true) ?: [];
    if ($method==='POST') { $stmt=$pdo->prepare('INSERT INTO events(event_date,event_time,title,type,description) VALUES(?,?,?,?,?)'); $stmt->execute([$input['date']??'', $input['time']??null, trim($input['title']??''), trim($input['type']??'Evento'), trim($input['description']??'')]); json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201); }
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'';
    if (preg_match('#/api/events/(\d+)$#',$path,$m)) { $id=(int)$m[1]; if($method==='PUT'){ $s=$pdo->prepare('UPDATE events SET event_date=?,event_time=?,title=?,type=?,description=? WHERE id=?'); $s->execute([$input['date']??'', $input['time']??null, trim($input['title']??''), trim($input['type']??'Evento'), trim($input['description']??''),$id]); json_response(['ok'=>true]); } if($method==='DELETE'){ $pdo->prepare('DELETE FROM events WHERE id=?')->execute([$id]); json_response(['ok'=>true]); } }
    json_response(['error'=>'Método no permitido'],405);
} catch(Throwable $e) { json_response(['error'=>'Error del servidor'],500); }
