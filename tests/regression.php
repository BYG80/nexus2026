<?php
/** php tests/regression.php; NEXUS_TEST_DSN=mysql:...;dbname=nexus_test y credenciales de prueba. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../includes/ai-chat.php';
require_once __DIR__.'/../includes/armory.php';
require_once __DIR__.'/../includes/news-html.php';
require_once __DIR__.'/../includes/weekly-player.php';
$count=0;
function expect(bool $condition,string $message):void{global $count;if(!$condition)throw new RuntimeException($message);$count++;}
function fails(callable $work,string $message):Throwable{try{$work();}catch(Throwable $e){expect(true,$message);return $e;}throw new RuntimeException($message);}
$GLOBALS['NEXUS_CONFIG']=['app_url'=>'https://www.nexus-forever.es','app_timezone'=>'Europe/Madrid','discord_guild_id'=>'123456789012345678','discord_bot_token'=>'test-placeholder','discord_event_channel_id'=>'234567890123456789','armory_guild_slug'=>'nexusforever'];
expect(nexus_safe_return_to('/calendario?mes=1')==='/calendario?mes=1','Retorno local válido');
foreach(['//evil.example','/%5c%5cevil.example',"/\r\nLocation:evil",'/auth/discord.php','/auth%2fdiscord.php']as$path)expect(nexus_safe_return_to($path)==='/panel','Rechazar redirección externa o al login');
foreach([401,403,404,429,500]as$status){$error=fails(fn()=>nexus_discord_request('POST','https://discord.com/api/v10/channels/123/messages',[],'secret-test',fn()=>['status'=>$status,'data'=>['message'=>'secret-test']]),'Rechazar HTTP '.$status);expect($error instanceof NexusDiscordException&&$error->httpStatus===$status,'Conservar estado HTTP');expect(!str_contains($error->getMessage(),'secret-test'),'No filtrar token o respuesta privada');}
$observed=[];
nexus_discord_request('POST','https://discord.com/api/v10/channels/123/messages',['content'=>'@everyone'],'test',function($method,$url,$payload)use(&$observed){$observed=$payload;return['status'=>200,'data'=>['id'=>'456']];});
expect(($observed['allowed_mentions']['parse']??null)===[],'No activar menciones accidentales');
fails(fn()=>nexus_discord_request('GET','https://evil.example/api',null,'test',fn()=>[]),'No enviar token fuera de Discord');
fails(fn()=>nexus_validate_event_input('2027-02-30','22:00','Raid','Raid',''),'No aceptar fecha imposible');
fails(fn()=>nexus_discord_event_payload(['id'=>1,'date'=>'2020-01-01','time'=>'22:00','title'=>'Pasado']),'No programar en el pasado');
$future=(new DateTimeImmutable('+3 days'))->format('Y-m-d');
$payload=nexus_discord_event_payload(['id'=>1,'date'=>$future,'time'=>'22:00','title'=>'Raid','description'=>'Descripción']);
expect($payload['entity_type']===3&&$payload['channel_id']===null&&isset($payload['entity_metadata']['location']),'Crear evento externo si no hay canal de voz');
$reply=nexus_chat_reply([],'¿Horario?',nexus_guild_context(),function($payload){expect($payload['store']===false,'No almacenar respuestas en la API');expect(!isset($payload['tools']),'Chat sin capacidad de modificar Discord');expect(str_contains($payload['instructions'],'5TuTMhFtD'),'Usar Discord confirmado');return['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Consulta el calendario.']]]]];});
expect($reply==='Consulta el calendario.','Leer formato real de Responses API');
fails(fn()=>nexus_chat_validate_message(str_repeat('a',1201)),'Limitar longitud de entrada');
fails(fn()=>nexus_chat_reply([],'Hola',[],fn()=>['output'=>[]]),'No inventar respuesta cuando la API está vacía');
expect(!str_contains(nexus_jsonld(['name'=>'</script><script>alert(1)</script>']),'</script>'),'JSON-LD seguro en HTML');
expect(!str_contains(nexus_news_html('<a href=javascript:alert(1) onclick=alert(2)>X</a>'),'javascript'),'Limpiar noticias');
$fixture=['character'=>['slug'=>'prueba-nexus','name'=>'Prueba','surname'=>'Nexus','guild'=>['slug'=>'nexusforever','name'=>'NexusForever'],'faction'=>['enum'=>'HORDE'],'gear'=>['head'=>['id'=>1,'name'=>'Objeto de prueba','slot'=>['name'=>'Cabeza'],'quality'=>['type'=>'RARE'],'level'=>['value'=>10]]]]];
$html='<script id="character-profile-mount-initial-state">var characterProfileInitialState = '.json_encode($fixture).';</script>';
expect(count(nexus_armory_parse($html,'prueba-nexus')['equipment'])===1,'Leer equipo público de Forever');
fails(fn()=>nexus_armory_parse($html,'otro-personaje'),'No confundir identidades');
$fixture['character']['guild']['slug']='otra-guild';
fails(fn()=>nexus_armory_parse('<script id="character-profile-mount-initial-state">var characterProfileInitialState = '.json_encode($fixture).';</script>','prueba-nexus'),'Validar pertenencia a la hermandad');
expect(nexus_armory_image('https://evil.example/image.jpg')==='','No aceptar imágenes de un host ajeno');
// Cuotas persistentes: una nueva sesión no restablece el contador de IP o de toda la web.
$quota=sys_get_temp_dir().'/nexus-quota-test-'.bin2hex(random_bytes(8));
$GLOBALS['NEXUS_CONFIG']['ai_chat_storage_dir']=$quota;
$GLOBALS['NEXUS_CONFIG']['ai_chat_daily_limit']=2;
$GLOBALS['NEXUS_CONFIG']['ai_chat_ip_hourly_limit']=1;
try{nexus_chat_take_quota('test-ip-a');fails(fn()=>nexus_chat_take_quota('test-ip-a'),'Cuota de IP compartida');nexus_chat_take_quota('test-ip-b');fails(fn()=>nexus_chat_take_quota('test-ip-c'),'Cuota global diaria');}finally{@unlink($quota.'/quota.json');@rmdir($quota);}
$dsn=(string)getenv('NEXUS_TEST_DSN');
if($dsn==='')throw new RuntimeException('Falta NEXUS_TEST_DSN para las pruebas de integración.');
$pdo=new PDO($dsn,(string)getenv('NEXUS_TEST_USER'),(string)getenv('NEXUS_TEST_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
expect(str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test'),'Ejecutar solo en una base de datos terminada en _test');
$pdo->exec(file_get_contents(__DIR__.'/../database.sql'));nexus_migrate_schema($pdo);nexus_media_discord_schema($pdo);
$pdo->exec(file_get_contents(__DIR__.'/../MIGRACION_IA_DISCORD_ARMERIA.sql'));
$memberIds=['900000000000000001','900000000000000002'];
foreach($memberIds as $id){$pdo->prepare('DELETE FROM member_profiles WHERE discord_id=?')->execute([$id]);$pdo->prepare('DELETE FROM members WHERE discord_id=?')->execute([$id]);}
$pdo->prepare('INSERT INTO members (discord_id,discord_username,is_member) VALUES (?,?,1),(?,?,0)')->execute([$memberIds[0],'Miembro verificado',$memberIds[1],'Cuenta no verificada']);
$pdo->prepare("INSERT INTO member_profiles (discord_id,faction,name) VALUES (?,'horda',?),(?,'horda',?)")->execute([$memberIds[0],'Melkir',$memberIds[1],'Ajeno Nexus']);
$armory=nexus_armory_members($pdo);
$mine=array_values(array_filter($armory,fn($profile)=>$profile['name']==='Melkir'));
expect(count($mine)===1&&$mine[0]['slug']==='melkir-vossgrave','Vincular el apellido confirmado solo a un perfil registrado');
expect(!array_filter($armory,fn($profile)=>$profile['name']==='Ajeno Nexus'),'Excluir usuarios no verificados de la web');
$pdo->prepare('UPDATE member_profiles SET name=? WHERE discord_id=?')->execute(['Prueba Destacada',$memberIds[0]]);
// El reconocimiento solo se publica tras verificar un miembro y un retrato real.
$weeklyFixture=['slug'=>'prueba-destacada','name'=>'Prueba Destacada','guild_slug'=>'nexusforever','faction'=>'Horda','portrait'=>'https://render.worldofwarcraft.com/forever-eu/character/test-main-raw.png','stale'=>false];
$selected=nexus_weekly_save($pdo,'Prueba Destacada','Ayuda a sus compañeros','Una hazaña escrita por el administrador.','trophy',fn()=>$weeklyFixture);
expect(nexus_weekly_player($pdo)['slug']==='prueba-destacada','Publicar reconocimiento de un miembro registrado');
expect(nexus_weekly_player($pdo)['feat']==='Ayuda a sus compañeros','Conservar la hazaña del administrador');
$pdo->prepare('UPDATE member_profiles SET name=? WHERE discord_id=?')->execute(['Prueba_Destacada',$memberIds[0]]);
expect(nexus_weekly_save($pdo,'Prueba Destacada','Ayuda a sus compañeros','Una hazaña escrita por el administrador.','trophy',fn()=>$weeklyFixture)['profile_id']===$mine[0]['profile_id'],'Reconocer nombres registrados con guion bajo escribiendo nombre y apellido');
expect(nexus_weekly_save_member($pdo,$mine[0]['profile_id'],'Ayuda a sus compañeros','','trophy',fn()=>$weeklyFixture)['profile_id']===$mine[0]['profile_id'],'Seleccionar un miembro por ID desde el desplegable');
fails(fn()=>nexus_weekly_save_member($pdo,2147483647,'','','trophy',fn()=>$weeklyFixture),'Rechazar un ID ajeno al desplegable de miembros');
fails(fn()=>nexus_weekly_save($pdo,'Prueba Destacada','Otro título','','invalid',fn()=>$weeklyFixture),'Rechazar iconos desconocidos');
fails(fn()=>nexus_weekly_save($pdo,'Prueba Destacada','Otro título','','trophy',fn()=>[...$weeklyFixture,'portrait'=>'']),'No publicar un personaje sin retrato');
fails(fn()=>nexus_weekly_save($pdo,'Prueba Destacada','Otro título','','trophy',fn()=>[...$weeklyFixture,'guild_slug'=>'otra-guild']),'Comprobar la hermandad del jugador destacado');
expect(nexus_weekly_player($pdo)['feat']==='Ayuda a sus compañeros','No perder el reconocimiento anterior si falla el nuevo');
$unexpectedLookup=false;
fails(function()use($pdo,&$unexpectedLookup,$weeklyFixture){nexus_weekly_save($pdo,'Ajeno Nexus','','','trophy',function()use(&$unexpectedLookup,$weeklyFixture){$unexpectedLookup=true;return $weeklyFixture;});},'No destacar cuentas no verificadas');
expect(!$unexpectedLookup,'No consultar Blizzard para un personaje no autorizado');
$pdo->prepare('UPDATE members SET is_member=0 WHERE discord_id=?')->execute([$memberIds[0]]);
expect(!array_filter(nexus_armory_members($pdo),fn($profile)=>$profile['profile_id']===$mine[0]['profile_id']),'Retirar de la armería al dejar de ser miembro verificado');
expect(nexus_weekly_player($pdo)===null,'Ocultar el reconocimiento si pierde su condición de miembro');
nexus_weekly_clear($pdo);
expect(nexus_weekly_player($pdo)===null,'Retirar el reconocimiento sin borrar personajes');
foreach($memberIds as $id){$pdo->prepare('DELETE FROM member_profiles WHERE discord_id=?')->execute([$id]);$pdo->prepare('DELETE FROM members WHERE discord_id=?')->execute([$id]);}
$pdo->exec('DELETE FROM event_attendees');$pdo->exec('DELETE FROM events');$pdo->exec('DELETE FROM discord_media_publications');$pdo->exec('DELETE FROM media');
$pdo->prepare('INSERT INTO events (event_date,event_time,title,type,description) VALUES (?,?,?,?,?)')->execute([$future,'22:00','Raid de prueba','Raid','Solo prueba']);$eventId=(int)$pdo->lastInsertId();
$remote=[];$messages=[];$creates=0;$messageCreates=0;
$api=function($method,$url,$payload=null)use(&$remote,&$messages,&$creates,&$messageCreates){
 if(str_contains($url,'/scheduled-events')){
  if(preg_match('~/scheduled-events/([^/?]+)/users~',$url))return[];
  if(preg_match('~/scheduled-events/([^/?]+)$~',$url,$match)){
   if(!isset($remote[$match[1]]))throw new NexusDiscordException('Discord API HTTP 404',404);
   if($method==='PATCH')$remote[$match[1]]=array_replace($remote[$match[1]],$payload);
   return $remote[$match[1]];
  }
  if($method==='GET')return array_values($remote);
  if($method==='POST'){$id=(string)(90000+ ++$creates);return $remote[$id]=['id'=>$id,'status'=>1,...$payload];}
 }
 if(preg_match('~/messages/([^/?]+)$~',$url,$match)){
  if(!isset($messages[$match[1]]))throw new NexusDiscordException('Discord API HTTP 404',404);
  if($method==='DELETE'){unset($messages[$match[1]]);return[];}
  return $messages[$match[1]]=['id'=>$match[1],...$payload];
 }
 if(str_contains($url,'/messages?'))return array_values($messages);
 if(str_ends_with($url,'/messages')&&$method==='POST'){$id=(string)(80000+ ++$messageCreates);return $messages[$id]=['id'=>$id,...$payload];}
 throw new RuntimeException('Ruta simulada desconocida: '.$url);
};
$result=nexus_publish_event($pdo,$eventId,$api);expect($result['ok'],'Publicar evento programado y mensaje al crearlo');
expect($creates===1&&$messageCreates===1,'Crear una sola publicación de cada tipo');
$result=nexus_publish_event($pdo,$eventId,$api);expect($result['ok']&&$creates===1&&$messageCreates===1,'Reintentar actualiza sin duplicar');
$denied=function($method,$url,$payload=null)use($api){if(str_contains($url,'/scheduled-events'))throw new NexusDiscordException('Sin permisos',403);return $api($method,$url,$payload);};
$result=nexus_publish_event($pdo,$eventId,$denied);expect(!$result['ok']&&$result['message']['ok'],'El fallo de Eventos no impide actualizar el canal');
expect((bool)$pdo->query('SELECT id FROM events')->fetchColumn(),'El evento web permanece guardado si falla Discord');
expect($creates===1,'Un error de permisos no provoca crear duplicados');
$pdo->prepare("INSERT INTO media (type,title,description,category,url) VALUES ('image',?,?,?,?)")->execute(['Foto de prueba','Descripción','Pruebas','/img/uploads/aaaaaaaaaaaaaaaaaaaaaaaa.jpg']);$mediaId=(int)$pdo->lastInsertId();
$before=$messageCreates;nexus_publish_media($pdo,$mediaId,$api);nexus_publish_media($pdo,$mediaId,$api);
expect($messageCreates===$before+1,'La galería publica una imagen y reutiliza su mensaje al reintentar');
nexus_remove_media_discord($pdo,$mediaId,$api);
expect($pdo->query('SELECT status FROM discord_media_publications')->fetchColumn()==='deleted','Retirar el mensaje vinculado de una imagen');
// Los interesados de Discord no son inscripciones web; retirar interés conserva las web.
$pdo->prepare('INSERT INTO event_attendees (event_id,member_discord_id,member_username,registered_via_web,discord_interested) VALUES (?,?,?,1,1)')->execute([$eventId,'11111','Inscrito web']);
$pdo->prepare('INSERT INTO event_attendees (event_id,member_discord_id,member_username,registered_via_web,discord_interested) VALUES (?,?,?,0,1)')->execute([$eventId,'33333','Interés antiguo']);
$interested=[['user'=>['id'=>'22222','global_name'=>'Interés nuevo']]];
$usersApi=function($method,$url,$payload=null)use($api,&$interested){if(str_contains($url,'/users?'))return $interested;return $api($method,$url,$payload);};
sync_discord_event($pdo,$eventId,true,$usersApi);
$rows=$pdo->query('SELECT member_discord_id,registered_via_web,discord_interested FROM event_attendees')->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
expect(isset($rows['11111'])&&(int)$rows['11111']['registered_via_web']===1&&(int)$rows['11111']['discord_interested']===0,'Conservar inscripción web al retirar interés');
expect(isset($rows['22222'])&&(int)$rows['22222']['discord_interested']===1&&(int)$rows['22222']['registered_via_web']===0,'Nuevo interesado marcado correctamente');
expect(!isset($rows['33333']),'Retirar interés antiguo sin inscripción ni asistencia');
$interested=[];sync_discord_event($pdo,$eventId,true,$usersApi);
expect((int)$pdo->query('SELECT COUNT(*) FROM event_attendees')->fetchColumn()===1,'Una sincronización vacía conserva solo la inscripción web');
$pageCalls=0;
$users=discord_list_event_users('12345','67890',function($method,$url)use(&$pageCalls){$pageCalls++;if($pageCalls===1)return array_map(fn($i)=>['user'=>['id'=>(string)$i]],range(10000,10099));expect(str_contains($url,'after=10099'),'Paginar desde el último usuario');return[['user'=>['id'=>'10100']]];});
expect(count($users)===101&&$pageCalls===2,'Importar más de cien interesados');
// Eliminar una imagen local de la armería o un error de parser no debe fabricar objetos.
fails(fn()=>nexus_armory_parse('<html>Sin perfil</html>','prueba-nexus'),'Fallo explícito si Blizzard cambia su página');
$pdo->exec('DELETE FROM event_attendees');$pdo->exec('DELETE FROM events');$pdo->exec('DELETE FROM discord_media_publications');$pdo->exec('DELETE FROM media');
echo $count." comprobaciones correctas. Sin peticiones reales a OpenAI ni Discord.\n";
