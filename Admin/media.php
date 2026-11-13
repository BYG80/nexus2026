<?php
require_once __DIR__.'/_common.php'; require_admin();
$pdo=db(); $msg=''; $error='';
$uploadDir=__DIR__.'/../img/uploads'; $uploadUrl='/img/uploads/';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);

function youtube_id(string $url): ?string {
    $url=trim($url);
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    if (!in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtu.be'],true) || parse_url($url,PHP_URL_SCHEME)!=='https') return null;
    if(preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~i',$url,$m)) return $m[1];
    if(preg_match('~[?&]v=([A-Za-z0-9_-]{11})~i',$url,$m)) return $m[1];
    return null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'image') {
            $title = trim((string)($_POST['title'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'General'));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($title === '' || mb_strlen($title)>180 || mb_strlen($category)>80 || mb_strlen($description)>5000) throw new RuntimeException('Revisa el título, la categoría y la descripción.');
            $file = $_FILES['image'] ?? null;
            if (!$file || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Selecciona una imagen válida.');
            if ((int)$file['size'] > 10*1024*1024) throw new RuntimeException('La imagen supera los 10 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $dimensions = @getimagesize($file['tmp_name']);
            if (!isset($allowed[$mime]) || !$dimensions || $dimensions[0]*$dimensions[1]>40000000) throw new RuntimeException('Usa una imagen JPG, PNG, WEBP o GIF válida de hasta 40 megapíxeles.');
            $name = bin2hex(random_bytes(12)).'.'.$allowed[$mime];
            $target = $uploadDir.'/'.$name;
            if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('No se pudo guardar la imagen.');
            try {
                $pdo->prepare("INSERT INTO media (type,title,description,category,url) VALUES ('image',?,?,?,?)")->execute([$title,$description,$category,$uploadUrl.$name]);
                $id = (int)$pdo->lastInsertId();
            } catch (Throwable $exception) { @unlink($target); throw $exception; }
            $msg = 'Imagen guardada en la galería.';
            if (!empty($_POST['publish_discord'])) {
                try { nexus_publish_media($pdo,$id); $msg .= ' Publicada también en Discord.'; }
                catch (Throwable $exception) { $error = 'La imagen está guardada. Discord queda pendiente: '.$exception->getMessage(); }
            }
        } elseif ($action === 'retry') {
            $id = (int)($_POST['id'] ?? 0);
            nexus_publish_media($pdo,$id);
            $msg = 'Publicación de Discord actualizada sin duplicar el mensaje vinculado.';
        } elseif ($action === 'retry_delete') {
            nexus_remove_media_discord($pdo,(int)($_POST['id'] ?? 0));
            $msg = 'Mensaje retirado de Discord.';
        } elseif ($action === 'youtube') {
            $title=trim((string)($_POST['title']??'')); $category=trim((string)($_POST['category']??'General')); $description=trim((string)($_POST['description']??''));
            $url=trim((string)($_POST['url']??'')); $yt=youtube_id($url);
            if ($title==='' || mb_strlen($title)>180 || mb_strlen($category)>80 || mb_strlen($description)>5000 || !$yt) throw new RuntimeException('Indica un título y una URL válida de YouTube.');
            $pdo->prepare("INSERT INTO media(type,title,description,category,url,thumbnail_url) VALUES ('youtube',?,?,?,?,?)")->execute([$title,$description,$category,'https://www.youtube.com/watch?v='.$yt,'https://i.ytimg.com/vi/'.$yt.'/hqdefault.jpg']);
            $msg='Vídeo de YouTube añadido.';
        } elseif ($action === 'delete') {
            $id=(int)($_POST['id']??0);
            $stmt=$pdo->prepare('SELECT * FROM media WHERE id=?'); $stmt->execute([$id]); $media=$stmt->fetch();
            if (!$media) throw new RuntimeException('El contenido ya no existe.');
            if ($media['type']==='image') {
                try { nexus_remove_media_discord($pdo,$id); }
                catch (Throwable $exception) { $error='Contenido retirado de la web. Queda pendiente retirar el mensaje de Discord: '.$exception->getMessage(); }
            }
            $pdo->prepare('DELETE FROM media WHERE id=?')->execute([$id]);
            if ($media['type']==='image') {
                $path=realpath(__DIR__.'/../'.ltrim((string)$media['url'],'/'));
                $directory=realpath($uploadDir);
                if ($path && $directory && str_starts_with($path,$directory.DIRECTORY_SEPARATOR)) @unlink($path);
            }
            $msg='Contenido eliminado de la galería.';
        }
    } catch (Throwable $exception) { $error=$exception->getMessage(); }
    $_SESSION['media_flash']=['message'=>$msg,'error'=>$error];
    header('Location: /Admin/media.php');
    exit;
}
$flash=$_SESSION['media_flash']??[];
unset($_SESSION['media_flash']);
$msg=$flash['message']??''; $error=$flash['error']??'';
$discordStates=[]; $pendingDeletes=[];
try {
    nexus_media_discord_schema($pdo);
    foreach ($pdo->query('SELECT * FROM discord_media_publications')->fetchAll(PDO::FETCH_ASSOC) as $state) {
        $discordStates[(int)$state['media_id']]=$state;
        if ($state['status']==='delete_pending') $pendingDeletes[]=$state;
    }
} catch (Throwable $exception) { if (!$error) $error='La tabla de publicaciones de Discord no está disponible. Consulta las instrucciones de instalación.'; }
$rows=$pdo->query('SELECT * FROM media ORDER BY created_at DESC,id DESC')->fetchAll();
admin_header('Multimedia','media');
?>
<div class="eyebrow">NEXUS · CONTENIDO</div><h1>Multimedia</h1>
<p>Desde aquí puedes subir imágenes a la galería y añadir vídeos de YouTube sin tocar el código de la web.</p>
<?php if($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-grid">
 <div class="admin-card"><h2>📷 Subir imagen</h2><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="image"><label>Título<input name="title" required></label><label>Categoría<input name="category" value="General"></label><label>Descripción<textarea name="description"></textarea></label><label>Imagen<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label><small>Máximo 10 MB · JPG, PNG, WEBP o GIF.</small><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="publish_discord" value="1" checked style="width:auto">Publicar automáticamente en Discord</label><button class="btn primary" type="submit">Subir imagen</button></form></div>
 <div class="admin-card"><h2>▶️ Añadir YouTube</h2><form class="admin-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="youtube"><label>Título<input name="title" required></label><label>Categoría<input name="category" value="General"></label><label>Descripción<textarea name="description"></textarea></label><label>URL de YouTube<input name="url" placeholder="https://www.youtube.com/watch?v=..." required></label><button class="btn primary" type="submit">Añadir vídeo</button></form></div>
</div><br>
<div class="admin-card"><h2>Contenido publicado</h2><div class="admin-media-grid">
<?php foreach($rows as $m): ?><article class="media-admin-item"><div class="media-admin-thumb"><?php if($m['type']==='image'): ?><img src="<?=e($m['url'])?>" alt=""><?php else: ?><img src="<?=e($m['thumbnail_url'])?>" alt=""><span class="play-badge">▶</span><?php endif; ?></div><div><strong><?=e($m['title'])?></strong><div class="small"><?=e($m['category'])?> · <?=e($m['type'])?></div>
<?php if ($m['type']==='image'): $publication=$discordStates[(int)$m['id']]??null; ?>
<p class="small">Discord: <?=($publication['status']??'')==='published'?'publicada':'sin publicar o pendiente'?></p>
<?php if (!empty($publication['last_error'])): ?><p class="small"><?=e($publication['last_error'])?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?=(int)$m['id']?>"><button class="btn ghost" type="submit"><?=($publication['status']??'')==='published'?'Actualizar en Discord':'Publicar / reintentar en Discord'?></button></form>
<?php endif; ?><form method="post" onsubmit="return confirm('¿Eliminar este contenido?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button class="btn danger" type="submit">Eliminar</button></form></div></article><?php endforeach; if(!$rows): ?><p>No hay contenido añadido todavía.</p><?php endif; ?></div></div>
<?php if ($pendingDeletes): ?><div class="admin-card" style="margin-top:20px"><h2>Retiradas pendientes en Discord</h2><?php foreach ($pendingDeletes as $pending): ?><p>Imagen #<?=(int)$pending['media_id']?> · <?=e($pending['last_error']??'')?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="retry_delete"><input type="hidden" name="id" value="<?=(int)$pending['media_id']?>"><button class="btn ghost" type="submit">Reintentar retirada</button></form><?php endforeach; ?></div><?php endif; ?>
<?php admin_footer(); ?>
