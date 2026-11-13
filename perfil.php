<?php
declare(strict_types=1);
require_once __DIR__.'/includes/member-awards.php';
require_once __DIR__.'/includes/seo.php';
start_secure_session();header('X-Robots-Tag: noindex, follow');
$profile=null;$memberAwards=[];
try{
 $pdo=db();$id=(int)($_GET['personaje']??0);
 $stmt=$pdo->prepare('SELECT mp.id,mp.name,mp.race,mp.class_name,mp.level,mp.discord_id FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1 WHERE mp.id=? AND mp.faction=? LIMIT 1');$stmt->execute([$id,nexus_faction()]);$profile=$stmt->fetch(PDO::FETCH_ASSOC);
 if($profile)$memberAwards=nexus_member_awards($pdo,(string)$profile['discord_id'],nexus_faction());else http_response_code(404);
}catch(Throwable $error){http_response_code(503);}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><?php nexus_render_seo('/perfil'); ?><link rel="icon" href="/favicon.ico"><link rel="stylesheet" href="/css/global.css"><link rel="stylesheet" href="/css/member-awards.css?v=<?=filemtime(__DIR__.'/css/member-awards.css')?>"></head><body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container"><div class="eyebrow">NEXUS · PERFIL DE MIEMBRO</div><h1><?=e($profile?$profile['name']:'Perfil no disponible')?></h1><?php if($profile): ?><p><?=e($profile['race'].' · '.$profile['class_name'])?><?php if($profile['level']): ?> · Nivel <?=(int)$profile['level']?><?php endif; ?></p><?php endif; ?></div></header>
<main><section><div class="container">
<?php if($profile): ?><h2 id="reconocimientos">Logros y hazañas de la hermandad</h2><p class="section-lead">Reconocimientos otorgados por NEXUS a este miembro a lo largo de sus personajes. Se conservan aunque cambie el jugador destacado de la semana.</p><?php include __DIR__.'/includes/member-awards-table.php'; ?>
<?php else: ?><div class="card"><p>No se encuentra un perfil de miembro activo con ese identificador.</p></div><?php endif; ?>
<p style="margin-top:24px"><a href="/members">← Volver a los miembros</a></p>
</div></section></main><?php include __DIR__.'/includes/legal-footer.php'; ?></body></html>
