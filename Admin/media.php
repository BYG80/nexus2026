<?php
require_once __DIR__.'/_common.php'; require_admin();
$pdo=db(); $msg=''; $error='';
$uploadDir=__DIR__.'/../img/uploads'; $uploadUrl='/img/uploads/';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);

function youtube_id(string $url): ?string {
    $url=trim($url);
    if(preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~i',$url,$m)) return $m[1];
    if(preg_match('~[?&]v=([A-Za-z0-9_-]{11})~i',$url,$m)) return $m[1];
    return null;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf($_POST['csrf']??null); $action=$_POST['action']??'';
  if($action==='image'){
    $title=trim($_POST['title']??''); $cat=trim($_POST['category']??'General'); $desc=trim($_POST['description']??'');
    if($title==='') throw new RuntimeException('Indica un título para la imagen.');
    if(empty($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Selecciona una imagen válida.');
    if($_FILES['image']['size']>10*1024*1024) throw new RuntimeException('La imagen supera el límite de 10 MB.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($_FILES['image']['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($allowed[$mime])) throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP o GIF.');
    $name=bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if(!move_uploaded_file($_FILES['image']['tmp_name'],$uploadDir.'/'.$name)) throw new RuntimeException('No se pudo guardar la imagen.');
    $s=$pdo->prepare('INSERT INTO media(type,title,description,category,url) VALUES(\'image\',?,?,?,?)');
    $s->execute([$title,$desc,$cat,$uploadUrl.$name]); $msg='Imagen subida correctamente.';
  } elseif($action==='youtube'){
    $title=trim($_POST['title']??''); $cat=trim($_POST['category']??'General'); $desc=trim($_POST['description']??''); $url=trim($_POST['url']??''); $yt=youtube_id($url);
    if($title==='' || !$yt) throw new RuntimeException('Indica un título y una URL válida de YouTube.');
    $thumb='https://i.ytimg.com/vi/'.$yt.'/hqdefault.jpg';
    $s=$pdo->prepare('INSERT INTO media(type,title,description,category,url,thumbnail_url) VALUES(\'youtube\',?,?,?,?,?)');
    $s->execute([$title,$desc,$cat,'https://www.youtube.com/watch?v='.$yt,$thumb]); $msg='Vídeo de YouTube añadido.';
  } elseif($action==='delete'){
    $id=(int)($_POST['id']??0); $s=$pdo->prepare('SELECT * FROM media WHERE id=?'); $s->execute([$id]); $m=$s->fetch();
    if($m){ if($m['type']==='image' && str_starts_with($m['url'],$uploadUrl)){ $path=__DIR__.'/../'.ltrim($m['url'],'/'); if(is_file($path)) @unlink($path); } $pdo->prepare('DELETE FROM media WHERE id=?')->execute([$id]); $msg='Elemento eliminado.'; }
  }
 }catch(Throwable $e){$error=$e->getMessage();}
}
$rows=$pdo->query('SELECT * FROM media ORDER BY created_at DESC,id DESC')->fetchAll();
admin_header('Multimedia','media');
?>
<div class="eyebrow">NEXUS · CONTENIDO</div><h1>Multimedia</h1>
<p>Desde aquí puedes subir imágenes a la galería y añadir vídeos de YouTube sin tocar el código de la web.</p>
<?php if($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-grid">
 <div class="admin-card"><h2>📷 Subir imagen</h2><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="image"><label>Título<input name="title" required></label><label>Categoría<input name="category" value="General"></label><label>Descripción<textarea name="description"></textarea></label><label>Imagen<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label><small>Máximo 10 MB · JPG, PNG, WEBP o GIF.</small><button class="btn primary" type="submit">Subir imagen</button></form></div>
 <div class="admin-card"><h2>▶️ Añadir YouTube</h2><form class="admin-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="youtube"><label>Título<input name="title" required></label><label>Categoría<input name="category" value="General"></label><label>Descripción<textarea name="description"></textarea></label><label>URL de YouTube<input name="url" placeholder="https://www.youtube.com/watch?v=..." required></label><button class="btn primary" type="submit">Añadir vídeo</button></form></div>
</div><br>
<div class="admin-card"><h2>Contenido publicado</h2><div class="admin-media-grid">
<?php foreach($rows as $m): ?><article class="media-admin-item"><div class="media-admin-thumb"><?php if($m['type']==='image'): ?><img src="<?=e($m['url'])?>" alt=""><?php else: ?><img src="<?=e($m['thumbnail_url'])?>" alt=""><span class="play-badge">▶</span><?php endif; ?></div><div><strong><?=e($m['title'])?></strong><div class="small"><?=e($m['category'])?> · <?=e($m['type'])?></div><form method="post" onsubmit="return confirm('¿Eliminar este contenido?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button class="btn danger" type="submit">Eliminar</button></form></div></article><?php endforeach; if(!$rows): ?><p>No hay contenido añadido todavía.</p><?php endif; ?></div></div>
<?php admin_footer(); ?>
