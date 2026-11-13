<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
start_secure_session();
$method=$_SERVER['REQUEST_METHOD']??'GET';
$pdo=null;
try{
    if($method==='GET'){
        if(!nexus_is_configured())json_response([]);
        $pdo=db();$discordId=(string)($_SESSION['member_discord_id']??'');
        $rows=$pdo->query("SELECT e.id,e.event_date AS date,e.event_time AS time,e.title,e.type,e.description,e.discord_event_url,(SELECT COUNT(*) FROM event_attendees a WHERE a.event_id=e.id) AS attendee_count,(SELECT COUNT(*) FROM event_attendees a WHERE a.event_id=e.id AND a.attended=1) AS attended_count FROM events e ORDER BY event_date,event_time,id")->fetchAll(PDO::FETCH_ASSOC);
        $mine=[];
        if($discordId!==''){$stmt=$pdo->prepare('SELECT event_id,character_id,race,class_name,registered_via_web FROM event_attendees WHERE member_discord_id=?');$stmt->execute([$discordId]);foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row)$mine[(int)$row['event_id']]=$row;}
        foreach($rows as &$event){$own=$mine[(int)$event['id']]??null;$event['user_registered']=$own&&(int)$own['registered_via_web']===1;$event['user_race']=$own['race']??'';$event['user_class']=$own['class_name']??'';$event['character_id']=$own['character_id']??null;}unset($event);
        json_response($rows);
    }
    if(empty($_SESSION['admin_user']))json_response(['error'=>'No autorizado.'],401);
    if(!in_array($method,['POST','PUT','DELETE'],true)){header('Allow: GET, POST, PUT, DELETE');json_response(['error'=>'Método no permitido.'],405);}
    $raw=file_get_contents('php://input',false,null,0,32769);
    if($raw===false||strlen($raw)>32768)json_response(['error'=>'Solicitud demasiado larga.'],413);
    $input=json_decode($raw,true);
    if(!is_array($input))$input=[];
    $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??$input['csrf']??null;
    if(!is_string($csrf)||!csrf_check($csrf))json_response(['error'=>'Recarga la página: token de sesión no válido.'],403);
    $pdo=db();
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'';
    $id=(int)($_GET['event_id']??$input['id']??0);
    if(preg_match('~/api/events/(\d+)$~',$path,$match))$id=(int)$match[1];
    if($method==='DELETE'){
        if($id<1)json_response(['error'=>'Evento no válido.'],400);
        nexus_delete_web_event($pdo,$id);json_response(['ok'=>true]);
    }
    $date=trim((string)($input['date']??''));$time=trim((string)($input['time']??''));$title=trim((string)($input['title']??''));$type=trim((string)($input['type']??'Evento'));$description=trim((string)($input['description']??''));
    nexus_validate_event_input($date,$time,$title,$type,$description);
    $values=[$date,$time?:null,$title,$type,$description];
    if($method==='POST'){$pdo->prepare('INSERT INTO events(event_date,event_time,title,type,description) VALUES(?,?,?,?,?)')->execute($values);$id=(int)$pdo->lastInsertId();}
    else{
        if($id<1)json_response(['error'=>'Evento no válido.'],400);
        $exists=$pdo->prepare('SELECT id FROM events WHERE id=?');$exists->execute([$id]);if(!$exists->fetchColumn())json_response(['error'=>'El evento no existe.'],404);
        $pdo->prepare('UPDATE events SET event_date=?,event_time=?,title=?,type=?,description=? WHERE id=?')->execute([...$values,$id]);
    }
    $sync=nexus_publish_event($pdo,$id);
    json_response(['ok'=>true,'id'=>$id,'discord_sync'=>$sync],$method==='POST'?201:200);
}catch(InvalidArgumentException $error){json_response(['error'=>$error->getMessage()],400);}
catch(NexusDiscordException $error){json_response(['error'=>$error->getMessage()],502);}
catch(Throwable $error){json_response(['error'=>'No se ha podido completar la operación. La creación y la sincronización se comprueban en el panel de eventos.'],503);}
