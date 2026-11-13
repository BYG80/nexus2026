<?php
/** Ejecutar por cron: php /ruta/a/la/web/tools/sync-discord.php */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/bootstrap.php';
$failures=0;
try {
    $pdo=db();
    $lock=$pdo->query("SELECT GET_LOCK('nexus_discord_cron',0)")->fetchColumn();
    if ((int)$lock!==1) { echo "Ya hay una sincronización en curso.\n"; exit; }
    try {
        $events=$pdo->query('SELECT id,discord_event_id,event_date FROM events WHERE event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY event_date,id LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
        foreach($events as $event){
            try{
                if($event['event_date']>=date('Y-m-d')){
                    $result=nexus_publish_event($pdo,(int)$event['id']);
                    if(!$result['ok']) throw new RuntimeException($result['error']);
                }
                if(!empty($event['discord_event_id']) || $event['event_date']>=date('Y-m-d')) sync_discord_event($pdo,(int)$event['id']);
                echo 'Evento #'.(int)$event['id'].": sincronizado.\n";
            }catch(Throwable $error){$failures++;echo 'Evento #'.(int)$event['id'].': '.$error->getMessage()."\n";}
        }
        nexus_media_discord_schema($pdo);
        $queue=$pdo->query("SELECT media_id,status FROM discord_media_publications WHERE status IN ('pending','delete_pending') AND attempts<10 ORDER BY updated_at LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        foreach($queue as $item){try{if($item['status']==='delete_pending')nexus_remove_media_discord($pdo,(int)$item['media_id']);else nexus_publish_media($pdo,(int)$item['media_id']);echo 'Imagen #'.(int)$item['media_id'].": sincronizada.\n";}catch(Throwable $error){$failures++;echo 'Imagen #'.(int)$item['media_id'].': '.$error->getMessage()."\n";}}
    } finally { $pdo->query("SELECT RELEASE_LOCK('nexus_discord_cron')"); }
}catch(Throwable $error){$failures++;fwrite(STDERR,"No se pudo iniciar la sincronización.\n");}
exit($failures?1:0);
