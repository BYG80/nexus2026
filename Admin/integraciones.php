<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');
$checks=[]; $error='';
$channels=['Voz para raids'=>(string)app_config('discord_voice_channel_id',''),'Eventos (avisos)'=>(string)app_config('discord_event_channel_id',''),'Galería'=>nexus_media_discord_channel(),'Profesiones'=>(string)app_config('discord_profession_channel_id',''),'RSP'=>(string)app_config('rsp_discord_channel_id',''),'Pedidos'=>(string)app_config('discord_order_channel_id',''),'Administración'=>(string)app_config('discord_admin_channel_id','')];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf($_POST['csrf']??null);
  $guild=discord_api('GET',nexus_discord_guild_url());
  $checks[]=['label'=>'Servidor','ok'=>true,'detail'=>(string)($guild['name']??'Accesible')];
  try{$events=discord_api('GET',nexus_discord_guild_url().'/scheduled-events');$checks[]=['label'=>'Eventos programados','ok'=>true,'detail'=>count($events).' eventos visibles. Esta lectura no comprueba el permiso de crear eventos.'];}catch(Throwable $e){$checks[]=['label'=>'Eventos programados','ok'=>false,'detail'=>$e->getMessage()];}
  $lookups=[];
  foreach($channels as $label=>$channel){
   if($channel===''){$checks[]=['label'=>$label,'ok'=>$label==='Voz para raids','detail'=>$label==='Voz para raids'?'Sin canal de voz: se crearán eventos externos de WoW Forever.':'Canal no configurado.'];continue;}
   try{
    $data=$lookups[$channel]??=discord_api('GET','https://discord.com/api/v10/channels/'.rawurlencode($channel));
    $same=(string)($data['guild_id']??'')===(string)app_config('discord_guild_id','');
    $checks[]=['label'=>$label,'ok'=>$same,'detail'=>$same?'#'.($data['name']??$channel).' visible para el bot. Comprueba Enviar mensajes e Insertar enlaces.':'Este canal no pertenece al servidor configurado.'];
   }catch(Throwable $e){$checks[]=['label'=>$label,'ok'=>false,'detail'=>$e->getMessage()];}
  }
 }catch(Throwable $e){$error=$e->getMessage();}
}
admin_header('Integraciones Discord','integrations');
?>
<h1>Integraciones con Discord</h1><p>Comprueba la configuración y el acceso del bot sin publicar mensajes ni crear eventos de prueba.</p>
<?php if($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-card"><h2>Configuración del servidor</h2><p>Bot: <?=trim((string)app_config('discord_bot_token',''))!==''?'configurado (token oculto)':'pendiente'?> · Servidor: <?=e(app_config('discord_guild_id','sin configurar'))?></p><p>Rol de miembro: <?=e(app_config('discord_member_role_name','Miembros'))?>. <?=app_config('discord_member_role_id','')?'Se valida el ID exacto configurado.':'Se busca el nombre configurado; se recomienda indicar también su ID.'?></p><p>Canal de voz para eventos: <?=e(app_config('discord_voice_channel_id','')?:'sin configurar; se crearán eventos externos de WoW Forever')?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="btn primary" type="submit">Comprobar conexión y canales</button></form></div>
<?php foreach($checks as $check): ?><div class="admin-card" style="margin-top:12px"><strong><?=$check['ok']?'✓':'⚠'?> <?=e($check['label'])?></strong><p><?=e($check['detail'])?></p></div><?php endforeach; ?>
<div class="admin-card" style="margin-top:20px"><h2>Permisos que necesita el bot</h2><ul><li>Eventos de Discord: Crear eventos y, para editar o retirar eventos de otros usuarios, Gestionar eventos. En eventos de voz también debe ver el canal.</li><li>Canales de avisos y galería: Ver canal, Enviar mensajes e Insertar enlaces.</li><li>Las imágenes se publican como vistas previas desde la URL HTTPS pública de la galería. Discord debe poder acceder a esa imagen.</li><li>La integración comprueba roles mediante la API del bot. Configura el ID real del rol de miembros en el archivo privado.</li></ul><p>Configura los IDs y el token en <code>config/config.php</code>. La galería usa <code>discord_gallery_channel_id</code>; si está vacío, usa el canal de eventos.</p><p>Para actualizar interesados y reintentar publicaciones periódicamente, configura la tarea CLI indicada en <code>LEEME_SEO_IA_DISCORD.md</code>. La web no puede recibir en tiempo real todas las acciones de Discord sin un servicio de eventos o esa tarea periódica.</p></div>
<?php admin_footer(); ?>
