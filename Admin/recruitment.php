<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin(); // Los administradores y editores autenticados pueden gestionar solicitudes.
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS recruitment_applications (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, player_name VARCHAR(60) NOT NULL, character_name VARCHAR(24) NOT NULL,
 race VARCHAR(80) NOT NULL, class_name VARCHAR(80) NOT NULL, role_name VARCHAR(40) NOT NULL, level SMALLINT NULL,
 profession_1 VARCHAR(40) NULL, profession_2 VARCHAR(40) NULL, specialization VARCHAR(80) NULL, experience VARCHAR(100) NULL,
 preferred_content VARCHAR(100) NULL, schedule VARCHAR(120) NULL, discord_name VARCHAR(80) NOT NULL, presentation TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'new', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 viewed_at DATETIME NULL, updated_at DATETIME NULL, updated_by VARCHAR(120) NULL,
 PRIMARY KEY(id), KEY idx_recruitment_status(status), KEY idx_recruitment_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$cols = $pdo->query("SHOW COLUMNS FROM recruitment_applications")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('viewed_at',$cols,true)) $pdo->exec("ALTER TABLE recruitment_applications ADD viewed_at DATETIME NULL");
if (!in_array('updated_at',$cols,true)) $pdo->exec("ALTER TABLE recruitment_applications ADD updated_at DATETIME NULL");
if (!in_array('updated_by',$cols,true)) $pdo->exec("ALTER TABLE recruitment_applications ADD updated_by VARCHAR(120) NULL");
$pdo->exec("ALTER TABLE recruitment_applications MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");
$pdo->exec("CREATE TABLE IF NOT EXISTS recruitment_application_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_id INT UNSIGNED NOT NULL, old_status VARCHAR(20) NULL,
 new_status VARCHAR(20) NOT NULL, changed_by VARCHAR(120) NOT NULL, changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 note VARCHAR(255) NULL, PRIMARY KEY(id), KEY idx_recruitment_history_app(application_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$msg=''; $error='';
$labels=['new'=>'Nueva','viewed'=>'Vista','accepted'=>'Aceptada','cancelled'=>'Cancelada'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  check_csrf($_POST['csrf']??null);
  $id=(int)($_POST['id']??0);
  $action=(string)($_POST['action']??'status');
  if ($id<1) throw new RuntimeException('Solicitud no válida.');
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT id,status FROM recruitment_applications WHERE id=? FOR UPDATE');
  $q->execute([$id]);
  $old=$q->fetch(PDO::FETCH_ASSOC);
  if (!$old) throw new RuntimeException('La solicitud ya no existe.');
  $actor=(string)($_SESSION['admin_user']??'usuario admin');

  if ($action==='delete') {
   if (!in_array($old['status'], ['accepted','cancelled'], true)) throw new RuntimeException('Solo se pueden eliminar solicitudes aceptadas o canceladas.');
   // Elimina primero el historial asociado para no dejar registros huérfanos.
   $dh=$pdo->prepare('DELETE FROM recruitment_application_history WHERE application_id=?');
   $dh->execute([$id]);
   $da=$pdo->prepare('DELETE FROM recruitment_applications WHERE id=? AND status IN (\'accepted\',\'cancelled\')');
   $da->execute([$id]);
   if ($da->rowCount()!==1) throw new RuntimeException('No se pudo eliminar la solicitud. Actualiza la página e inténtalo de nuevo.');
   $pdo->commit();
   $msg='Solicitud #'.$id.' eliminada definitivamente del registro.';
  } else {
   $status=(string)($_POST['status']??'');
   if (!isset($labels[$status])) throw new RuntimeException('Estado no válido.');
   $u=$pdo->prepare('UPDATE recruitment_applications SET status=?, viewed_at=CASE WHEN ?=\'viewed\' AND viewed_at IS NULL THEN NOW() ELSE viewed_at END, updated_at=NOW(), updated_by=? WHERE id=?');
   $u->execute([$status,$status,$actor,$id]);
   if ($old['status']!==$status) {
    $h=$pdo->prepare('INSERT INTO recruitment_application_history (application_id,old_status,new_status,changed_by) VALUES (?,?,?,?)');
    $h->execute([$id,$old['status'],$status,$actor]);
   }
   $pdo->commit();
   $msg='Solicitud #'.$id.' actualizada a «'.$labels[$status].'». El cambio ha quedado registrado.';
  }
 } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el cambio. Comprueba la base de datos y los permisos.'; error_log('NEXUS recruitment admin: '.$e->getMessage()); }
}
$filter=(string)($_GET['estado']??'');
if ($filter!=='' && isset($labels[$filter])) { $st=$pdo->prepare('SELECT * FROM recruitment_applications WHERE status=? ORDER BY created_at DESC,id DESC'); $st->execute([$filter]); $apps=$st->fetchAll(PDO::FETCH_ASSOC); }
else { $apps=$pdo->query('SELECT * FROM recruitment_applications ORDER BY created_at DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC); }
$counts=array_fill_keys(array_keys($labels),0); foreach($pdo->query('SELECT status,COUNT(*) n FROM recruitment_applications GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $c) if(isset($counts[$c['status']])) $counts[$c['status']]=(int)$c['n'];
admin_header('Solicitudes de reclutamiento','recruitment');
?>
<style>
/* NEXUS · fichas de reclutamiento */
.recruitment-toolbar{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin:20px 0 26px}
.recruitment-stat{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 17px;background:linear-gradient(145deg,#171a22,#101217);border:1px solid rgba(255,255,255,.09);border-radius:13px;text-decoration:none;color:#eee;transition:transform .18s,border-color .18s,background .18s;min-width:0}
.recruitment-stat:hover{transform:translateY(-2px);border-color:rgba(216,173,85,.55);background:linear-gradient(145deg,#1b1d25,#111319)}
.recruitment-stat strong{font-size:.88rem;color:#e6e0d4}.recruitment-stat .recruitment-count{display:grid;place-items:center;min-width:38px;height:38px;padding:0 8px;box-sizing:border-box;border-radius:11px;background:rgba(216,173,85,.12);border:1px solid rgba(216,173,85,.23);font-size:1.15rem;font-weight:800;color:#e7c56c}
.recruitment-list{display:grid;gap:18px}.recruitment-card{position:relative;overflow:hidden;margin:0!important;padding:0!important;border:1px solid rgba(255,255,255,.1)!important;border-radius:15px!important;background:linear-gradient(145deg,#151820 0%,#101217 100%)!important;box-shadow:0 8px 25px rgba(0,0,0,.14);transition:border-color .18s,transform .18s}
.recruitment-card:hover{border-color:rgba(216,173,85,.32)!important;transform:translateY(-1px)}
.recruitment-card:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:#777;opacity:.9}
.recruitment-card.status-new:before{background:#d8ad55}.recruitment-card.status-viewed:before{background:#5d9ed8}.recruitment-card.status-accepted:before{background:#55b77b}.recruitment-card.status-cancelled:before{background:#d56a6a}
.recruitment-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:21px 23px 17px 26px;border-bottom:1px solid rgba(255,255,255,.075);background:rgba(255,255,255,.018)}
.recruitment-title{margin:0!important;color:#f3ead9;font-size:1.2rem;line-height:1.35;overflow-wrap:anywhere}.recruitment-title small{display:block;margin-top:5px;color:#aeb4bf;font-size:.78rem;font-weight:500;letter-spacing:.02em}
.recruitment-status{display:inline-flex;align-items:center;gap:7px;white-space:nowrap;padding:6px 11px;border:1px solid rgba(255,255,255,.13);border-radius:999px;font-size:.78rem;font-weight:750;letter-spacing:.02em}
.recruitment-status:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}.recruitment-status.status-new{color:#e8c36c;background:rgba(216,173,85,.09)}.recruitment-status.status-viewed{color:#82b9e9;background:rgba(93,158,216,.1)}.recruitment-status.status-accepted{color:#80d69d;background:rgba(85,183,123,.1)}.recruitment-status.status-cancelled{color:#ef9999;background:rgba(213,106,106,.1)}
.recruitment-card-body{padding:20px 23px 22px 26px}.recruitment-classline{margin:0 0 17px!important;color:#c8ccd4}.recruitment-classline strong{color:#f0d28b;font-size:1rem}.recruitment-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:10px;margin:0 0 17px}.recruitment-meta-item{min-width:0;padding:11px 13px;border:1px solid rgba(255,255,255,.075);border-radius:10px;background:rgba(0,0,0,.13)}.recruitment-meta-item span{display:block;margin-bottom:5px;color:#969eac;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em}.recruitment-meta-item strong,.recruitment-meta-item div{color:#e5e7ec;font-size:.88rem;line-height:1.45;overflow-wrap:anywhere}
.recruitment-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:10px;margin:0 0 16px}.recruitment-detail{padding:12px 14px;border-left:2px solid rgba(216,173,85,.45);background:rgba(255,255,255,.025);border-radius:0 8px 8px 0;min-width:0}.recruitment-detail strong{display:block;color:#d8ad55;font-size:.77rem;text-transform:uppercase;letter-spacing:.055em;margin-bottom:5px}.recruitment-detail div{color:#d5d8df;line-height:1.55;font-size:.9rem;overflow-wrap:anywhere}
.recruitment-presentation{padding:14px 16px;margin:0 0 17px;border:1px solid rgba(255,255,255,.07);border-radius:10px;background:rgba(0,0,0,.15);color:#d2d5dc;line-height:1.65;overflow-wrap:anywhere}.recruitment-presentation .label{display:block;color:#d8ad55;font-size:.75rem;font-weight:750;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}
.recruitment-audit{display:flex;flex-wrap:wrap;gap:7px 16px;color:#9ca4b1;font-size:.78rem;margin:0 0 18px}.recruitment-audit strong{color:#cbd0d8;font-weight:650}
.recruitment-controls{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;padding:15px;border:1px solid rgba(216,173,85,.16);border-radius:12px;background:rgba(216,173,85,.035)}.recruitment-controls .admin-form{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}.recruitment-controls .admin-form label{min-width:150px;color:#c8ccd4;font-size:.82rem}.recruitment-controls .admin-form select{min-width:155px}.recruitment-controls form{margin:0}.recruitment-delete{background:#762b30!important;border:1px solid #a94a4a!important;color:#fff!important}.recruitment-delete:hover{background:#92363b!important}
.recruitment-history{margin-top:14px;padding:12px 14px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(0,0,0,.12)}.recruitment-history summary{color:#d8ad55;cursor:pointer;font-size:.86rem;font-weight:700}.recruitment-history ul{margin:12px 0 0;padding-left:20px;color:#bfc4ce;font-size:.83rem;line-height:1.7}
@media(max-width:600px){.recruitment-card-head{padding:17px 16px 14px 20px}.recruitment-card-body{padding:16px 16px 18px 20px}.recruitment-title{font-size:1.05rem}.recruitment-controls{align-items:stretch}.recruitment-controls .admin-form{width:100%;align-items:stretch}.recruitment-controls .admin-form label{width:100%}.recruitment-controls .admin-form button,.recruitment-delete{width:100%}.recruitment-controls>form{width:100%}.recruitment-stat{padding:12px}}
</style>
<div class="eyebrow">NEXUS · RECLUTAMIENTO</div><h1>Solicitudes recibidas</h1>
<p class="muted">Registro central de solicitudes enviadas desde «Unirse». Administradores y editores pueden revisar cada ficha y actualizar su estado. Cada cambio de estado queda anotado con usuario y fecha. Las solicitudes aceptadas o canceladas pueden eliminarse definitivamente del registro.</p>
<?php if($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="flash" style="border-color:#a33"><?=e($error)?></div><?php endif; ?>
<div class="recruitment-toolbar">
<?php foreach($labels as $key=>$label): ?><a class="recruitment-stat" href="/Admin/recruitment.php?estado=<?=rawurlencode($key)?>"><strong><?=e($label)?></strong><span class="recruitment-count"><?=e($counts[$key])?></span></a><?php endforeach; ?>
<a class="recruitment-stat" href="/Admin/recruitment.php"><strong>Todas</strong><span class="recruitment-count"><?= (int)$pdo->query('SELECT COUNT(*) FROM recruitment_applications')->fetchColumn() ?></span></a>
</div>
<?php if(!$apps): ?><div class="admin-card"><p>No hay solicitudes en este filtro.</p></div><?php else: ?><div class="recruitment-list"><?php foreach($apps as $a): $appStatus=(string)($a['status']??'new'); ?>
<article class="admin-card recruitment-card status-<?=e($appStatus)?>">
 <header class="recruitment-card-head"><div><h2 class="recruitment-title"><?=e($a['player_name'])?> · <?=e($a['character_name'])?><small>Solicitud #<?=e($a['id'])?> · Recibida el <?=e($a['created_at'])?></small></h2></div><span class="recruitment-status status-<?=e($appStatus)?>"><?=e($labels[$appStatus]??$appStatus)?></span></header>
 <div class="recruitment-card-body">
  <p class="recruitment-classline"><strong><?=e($a['race'])?> <?=e($a['class_name'])?></strong> <span>· <?=e($a['role_name'])?><?= $a['level']?' · Nivel '.e($a['level']):'' ?></span></p>
  <div class="recruitment-meta">
   <div class="recruitment-meta-item"><span>Discord</span><strong><?=e($a['discord_name'])?></strong></div>
   <div class="recruitment-meta-item"><span>Profesiones</span><div><?=e(trim(($a['profession_1']??'').' / '.($a['profession_2']??''),' /') ?: '—')?></div></div>
   <div class="recruitment-meta-item"><span>Especialización</span><div><?=e($a['specialization']?:'—')?></div></div>
  </div>
  <div class="recruitment-details">
   <div class="recruitment-detail"><strong>Experiencia</strong><div><?=e($a['experience']?:'—')?></div></div>
   <div class="recruitment-detail"><strong>Contenido de interés</strong><div><?=e($a['preferred_content']?:'—')?></div></div>
   <div class="recruitment-detail"><strong>Horarios</strong><div><?=e($a['schedule']?:'—')?></div></div>
  </div>
  <div class="recruitment-presentation"><span class="label">Presentación</span><?=nl2br(e($a['presentation']?:'Sin presentación adicional.'))?></div>
  <div class="recruitment-audit"><span><strong>Estado:</strong> <?=e($labels[$appStatus]??$appStatus)?></span><?php if(!empty($a['updated_by'])): ?><span><strong>Última gestión:</strong> <?=e($a['updated_by'])?> · <?=e($a['updated_at']??'')?></span><?php endif; ?><?php if(!empty($a['viewed_at'])): ?><span><strong>Vista por primera vez:</strong> <?=e($a['viewed_at'])?></span><?php endif; ?></div>
  <div class="recruitment-controls">
   <form method="post" class="admin-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($a['id'])?>"><input type="hidden" name="action" value="status"><label>Gestionar estado<select name="status"><?php foreach($labels as $key=>$label): ?><option value="<?=e($key)?>" <?=$appStatus===$key?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></label><button class="btn primary" type="submit">Guardar y registrar</button></form>
   <?php if (in_array($appStatus, ['accepted','cancelled'], true)): ?>
    <?php $deleteLabel = $appStatus==='accepted' ? 'Eliminar solicitud aceptada' : 'Eliminar solicitud cancelada'; ?>
    <form method="post" onsubmit="return confirm('¿Eliminar definitivamente la solicitud #<?=e($a['id'])?> de <?=e($a['player_name'])?>? También se borrará su historial de gestión. Esta acción no se puede deshacer.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($a['id'])?>"><input type="hidden" name="action" value="delete"><button class="btn recruitment-delete" type="submit"><?=e($deleteLabel)?></button></form>
   <?php endif; ?>
  </div>
  <?php $hist=$pdo->prepare('SELECT old_status,new_status,changed_by,changed_at,note FROM recruitment_application_history WHERE application_id=? ORDER BY changed_at DESC,id DESC'); $hist->execute([$a['id']]); $history=$hist->fetchAll(PDO::FETCH_ASSOC); ?>
  <?php if($history): ?><details class="recruitment-history"><summary>Historial de gestión (<?=count($history)?>)</summary><ul><?php foreach($history as $h): ?><li><?=e($h['changed_at'])?> — <?=e($h['changed_by'])?>: <?=e($labels[$h['old_status']]??($h['old_status']?:'Creación'))?> → <?=e($labels[$h['new_status']]??$h['new_status'])?></li><?php endforeach; ?></ul></details><?php endif; ?>
 </div>
</article><?php endforeach; ?></div><?php endif; ?>
<?php admin_footer(); ?>
