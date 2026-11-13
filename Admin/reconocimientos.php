<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');
require_once __DIR__ . '/../includes/member-awards.php';
$pdo=db();$error='';$ready=true;
try{nexus_awards_schema($pdo);nexus_awards_import_current($pdo);}catch(Throwable $exception){$ready=false;$error='No se puede preparar el historial. Aplica MIGRACION_RECONOCIMIENTOS.sql si MySQL no permite crear tablas.';}
$profiles=$pdo->prepare('SELECT mp.id,mp.name,m.discord_username FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1 WHERE mp.faction=? ORDER BY mp.name');$profiles->execute([nexus_faction()]);$profiles=$profiles->fetchAll(PDO::FETCH_ASSOC);
$values=['profile_id'=>'','title'=>'','description'=>'','icon'=>'trophy','date'=>date('Y-m-d')];
$requestKey=is_string($_POST['request_key']??null)?$_POST['request_key']:bin2hex(random_bytes(16));
$message=(string)($_SESSION['award_flash']??'');unset($_SESSION['award_flash']);
if($_SERVER['REQUEST_METHOD']==='POST'&&$ready){
 try{
  check_csrf(is_string($_POST['csrf']??null)?$_POST['csrf']:null);
  $action=(string)($_POST['action']??'grant');
  if(in_array($action,['revoke','restore'],true)){
   $id=(int)($_POST['id']??0);nexus_award_revoke($pdo,$id,$action==='restore');
   $_SESSION['award_flash']=$action==='restore'?'Reconocimiento reactivado.':'Reconocimiento retirado del perfil. Se conserva el registro administrativo.';
  }elseif($action==='grant'){
   foreach(array_keys($values)as$key){if(!is_string($_POST[$key]??null))throw new InvalidArgumentException('Revisa los campos del formulario.');$values[$key]=trim($_POST[$key]);}
   if(!preg_match('/^[1-9][0-9]{0,9}$/',$values['profile_id'])||!preg_match('/^[a-f0-9]{32}$/',$requestKey))throw new InvalidArgumentException('Selecciona un miembro y recarga el formulario si ha caducado.');
   $date=DateTimeImmutable::createFromFormat('!Y-m-d',$values['date'],app_timezone());
   if(!$date||$date->format('Y-m-d')!==$values['date']||$values['date']>date('Y-m-d'))throw new InvalidArgumentException('Indica una fecha válida que no sea futura.');
   nexus_award_record($pdo,(int)$values['profile_id'],$values['title'],$values['description'],$values['icon'],'manual','manual:'.$requestKey,$date->setTime(12,0));
   $_SESSION['award_flash']='Reconocimiento otorgado. Ya aparece en el perfil del miembro.';
  }else throw new InvalidArgumentException('Acción no válida.');
  header('Location: /Admin/reconocimientos.php');exit;
 }catch(InvalidArgumentException $exception){$error=$exception->getMessage();}
 catch(Throwable $exception){$error='No se ha podido guardar el reconocimiento. Revisa la base de datos.';}
}
$history=[];
if($ready)$history=$pdo->query('SELECT id,profile_id,character_name,title,description,icon,source,awarded_at,revoked_at FROM member_awards ORDER BY awarded_at DESC,id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
admin_header('Reconocimientos','awards');
?>
<div class="eyebrow">NEXUS · HISTORIAL DE LOS MIEMBROS</div><h1>Reconocimientos y hazañas</h1>
<p>Otorga reconocimientos propios de la hermandad. Se mostrarán en el perfil público del miembro y en Mi panel. No modifican los logros del juego.</p>
<?php if($message): ?><div class="flash"><?=e($message)?></div><?php endif; ?><?php if($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-card"><h2>Otorgar reconocimiento</h2><form class="admin-form" method="post">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="grant"><input type="hidden" name="request_key" value="<?=e($requestKey)?>">
<label>Personaje del miembro<select name="profile_id" required><option value="">Selecciona un personaje…</option><?php foreach($profiles as $profile): ?><option value="<?=(int)$profile['id']?>" <?=$values['profile_id']===(string)$profile['id']?'selected':''?>><?=e($profile['name'])?> · <?=e($profile['discord_username'])?></option><?php endforeach; ?></select></label>
<label>Hazaña o logro<input name="title" maxlength="120" value="<?=e($values['title'])?>" placeholder="Ej.: Compañero incansable" required></label>
<label>Descripción<textarea name="description" maxlength="700" rows="4"><?=e($values['description'])?></textarea></label>
<label>Icono<select name="icon"><?php foreach(nexus_award_icons()as$key=>$icon): ?><option value="<?=e($key)?>" <?=$values['icon']===$key?'selected':''?>><?=e($icon['symbol'].' '.$icon['label'])?></option><?php endforeach; ?></select></label>
<label>Fecha de concesión<input type="date" name="date" max="<?=e(date('Y-m-d'))?>" value="<?=e($values['date'])?>" required></label>
<button class="btn primary" type="submit" <?=$ready?'':'disabled'?>>Otorgar reconocimiento</button>
</form><p class="small">Los reconocimientos de Jugador de la semana se guardan automáticamente. Cambiar el jugador destacado o retirarlo de la portada no borra su historial.</p></div>
<div class="admin-card" style="margin-top:24px"><h2>Últimos 100 reconocimientos</h2><p class="small">Retirar un reconocimiento lo oculta del perfil y de Mi panel. Para quitar el destacado de la portada utiliza Admin → Jugador de la semana.</p><div style="max-width:100%;overflow:auto"><table class="admin-table"><thead><tr><th>Personaje</th><th>Hazaña</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($history as $award): ?><tr>
<td><?=e($award['character_name'])?></td><td><?=e(nexus_award_icons()[$award['icon']]['symbol']??'🏆')?> <?=e($award['title'])?><br><small><?=$award['source']==='weekly'?'Jugador de la semana':'Otorgado desde Admin'?></small></td><td><?=e((new DateTimeImmutable($award['awarded_at'],new DateTimeZone('UTC')))->setTimezone(app_timezone())->format('d/m/Y'))?></td><td><?=$award['revoked_at']?'Retirado':'Activo'?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$award['id']?>"><input type="hidden" name="action" value="<?=$award['revoked_at']?'restore':'revoke'?>"><button class="btn ghost" type="submit"><?=$award['revoked_at']?'Reactivar':'Retirar'?></button></form></td></tr><?php endforeach; ?><?php if(!$history): ?><tr><td colspan="5">Todavía no hay reconocimientos.</td></tr><?php endif; ?></tbody></table></div></div>
<?php admin_footer(); ?>
