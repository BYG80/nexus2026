<?php
require_once __DIR__.'/_common.php'; require_admin();
$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS achievements (id INT UNSIGNED NOT NULL AUTO_INCREMENT,title VARCHAR(180) NOT NULL,description TEXT NULL,category VARCHAR(80) NOT NULL DEFAULT 'General',icon_url VARCHAR(500) NULL,achievement_date DATE NULL,published TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_ach_date(achievement_date),KEY idx_ach_pub(published)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$msg=''; $edit=null;
$uploadDir=__DIR__.'/../img/uploads/achievements'; if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf($_POST['csrf']??null); $action=$_POST['action']??'';
  if($action==='save'){
   $id=(int)($_POST['id']??0); $title=trim($_POST['title']??''); $description=trim($_POST['description']??''); $category=trim($_POST['category']??'General') ?: 'General'; $date=trim($_POST['achievement_date']??''); $date=$date!==''?$date:null; $published=(int)($_POST['published']??1)===1?1:0; $icon=trim($_POST['icon_url']??'');
   if(!preg_match('/^https?:\/\//i',$icon) && !str_starts_with($icon,'/')) $icon='';
   if(!empty($_FILES['icon_file']['tmp_name']) && is_uploaded_file($_FILES['icon_file']['tmp_name'])){
    $f=$_FILES['icon_file']; if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el icono.'); if($f['size']>5*1024*1024) throw new RuntimeException('El icono supera 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg'][$mime]??null; if(!$ext) throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP, GIF o SVG.');
    $name='achievement-'.bin2hex(random_bytes(8)).'.'.$ext; if(!move_uploaded_file($f['tmp_name'],$uploadDir.'/'.$name)) throw new RuntimeException('No se pudo guardar el icono.'); $icon='/img/uploads/achievements/'.$name;
   }
   if(!$title) throw new RuntimeException('El título es obligatorio.');
   if($id){$s=$pdo->prepare('UPDATE achievements SET title=?,description=?,category=?,icon_url=?,achievement_date=?,published=? WHERE id=?');$s->execute([$title,$description,$category,$icon?:null,$date,$published,$id]);}
   else{$s=$pdo->prepare('INSERT INTO achievements(title,description,category,icon_url,achievement_date,published) VALUES(?,?,?,?,?,?)');$s->execute([$title,$description,$category,$icon?:null,$date,$published]);}
   $msg='Logro guardado correctamente.';
  } elseif($action==='delete') { $s=$pdo->prepare('DELETE FROM achievements WHERE id=?'); $s->execute([(int)$_POST['id']]); $msg='Logro eliminado.'; }
 }catch(Throwable $e){$msg='Error: '.$e->getMessage();}
}
if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM achievements WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$rows=$pdo->query('SELECT * FROM achievements ORDER BY achievement_date DESC,id DESC')->fetchAll();
admin_header('Logros','achievements');
?><div class="eyebrow">NEXUS · PROGRESIÓN</div><h1>Gestión de logros</h1><p class="muted">Crea los logros de la hermandad, añade su icono y establece o modifica la fecha de consecución. No necesitas reinstalar la base de datos: esta pantalla crea la tabla automáticamente si no existe.</p>
<?php if($msg):?><div class="flash"><?=e($msg)?></div><?php endif;?>
<div class="admin-card"><h2><?=$edit?'Editar logro':'Añadir logro'?></h2><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)($edit['id']??0))?>">
<label>Nombre del logro<input name="title" value="<?=e($edit['title']??'')?>" required maxlength="180" placeholder="Ej.: Primeros pasos en Naxxramas"></label>
<label>Categoría<select name="category"><?php foreach(['General','Raids','Mazmorras','PvP','Exploración','Hermandad','Profesiones','Eventos'] as $c):?><option value="<?=e($c)?>" <?=($edit['category']??'General')===$c?'selected':''?>><?=e($c)?></option><?php endforeach;?></select></label>
<label>Descripción<textarea name="description" placeholder="Describe el logro..."><?=e($edit['description']??'')?></textarea></label>
<label>Fecha del logro<input type="date" name="achievement_date" value="<?=e($edit['achievement_date']??'')?>"></label>
<label>Icono por URL <span class="small">(opcional)</span><input name="icon_url" value="<?=e($edit['icon_url']??'')?>" placeholder="https://.../icono.png"></label>
<label>O subir icono <span class="small">(JPG, PNG, WEBP, GIF o SVG · máx. 5 MB)</span><input type="file" name="icon_file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"></label>
<?php if(!empty($edit['icon_url'])):?><div style="display:flex;gap:12px;align-items:center"><img src="<?=e($edit['icon_url'])?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid #444"><span class="small">Icono actual</span></div><?php endif;?>
<label>Publicar<select name="published"><option value="1" <?=((int)($edit['published']??1)===1?'selected':'')?>>Sí</option><option value="0" <?=((int)($edit['published']??1)===0?'selected':'')?>>No, borrador</option></select></label>
<div class="admin-actions"><button class="btn primary" type="submit">Guardar logro</button><?php if($edit):?><a class="btn ghost" href="/Admin/logros.php">Cancelar</a><?php endif;?></div></form></div>
<br><div class="admin-card"><h2>Logros (<?=count($rows)?>)</h2><div style="overflow:auto"><table class="admin-table"><thead><tr><th>Icono</th><th>Fecha</th><th>Logro</th><th>Categoría</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6">Todavía no hay logros.</td></tr><?php endif;?><?php foreach($rows as $r):?><tr><td><?php if(!empty($r['icon_url'])):?><img src="<?=e($r['icon_url'])?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:8px;vertical-align:middle"><?php else:?>🏆<?php endif;?></td><td><?=!empty($r['achievement_date'])?e(date('d/m/Y',strtotime($r['achievement_date']))):'—'?></td><td><strong><?=e($r['title'])?></strong><br><span class="small"><?=e(mb_strimwidth((string)$r['description'],0,80,'…','UTF-8'))?></span></td><td><?=e($r['category'])?></td><td><?=$r['published']?'Publicado':'Borrador'?></td><td><a href="?edit=<?=$r['id']?>">Editar</a> <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar logro?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger" style="padding:5px 9px" type="submit">Eliminar</button></form></td></tr><?php endforeach;?></tbody></table></div></div><?php admin_footer(); ?>
