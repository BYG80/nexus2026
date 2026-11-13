<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/armory.php';
start_secure_session();
$members=nexus_armory_members();
$characters=nexus_armory_characters();
$selected=trim((string)($_GET['personaje']??(array_key_first($characters)??'')));
$profile=null; $error='';
if ($selected!=='' && !isset($characters[$selected])) { http_response_code(404); $error='Este personaje no está vinculado a la armería de la hermandad.'; }
elseif ($selected!=='') { try { $profile=nexus_armory_profile($selected); } catch(Throwable $exception) { $error=$exception->getMessage(); } }
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php nexus_render_seo('/armeria'); ?>
<link rel="icon" href="/favicon.ico"><link rel="stylesheet" href="/css/global.css"><link rel="stylesheet" href="/css/armeria.css?v=<?=filemtime(__DIR__.'/css/armeria.css')?>">
</head><body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container"><div class="eyebrow">NEXUS · HORDA · EUROPA</div><h1>Armería de la hermandad</h1><p>Solo personajes de miembros registrados y verificados en nuestra web. Su perfil público de Blizzard debe confirmar que pertenecen a NexusForever y a la Horda.</p></div></header>
<main><section><div class="container">
<?php if(!$members): ?><div class="card"><h2>No hay personajes disponibles todavía</h2><p>Los miembros deben tener su personaje creado en Mi panel. Solo se muestran perfiles de miembros verificados de NEXUS.</p><a href="/panel">Ir a Mi panel</a></div><?php endif; ?>
<?php if($characters): ?><form class="armory-selector" method="get"><label for="armory-character">Personaje</label><select id="armory-character" name="personaje"><?php foreach($characters as $slug=>$name): ?><option value="<?=e($slug)?>" <?=$slug===$selected?'selected':''?>><?=e($name)?></option><?php endforeach; ?></select><button class="btn primary" type="submit">Ver equipo</button></form><?php endif; ?>
<?php if($error): ?><div class="card"><h2>No se ha podido mostrar el equipo</h2><p><?=e($error)?></p><?php if(isset($characters[$selected])): ?><a href="<?=e(nexus_armory_official_url($selected))?>" target="_blank" rel="noopener noreferrer">Consultar el perfil oficial de Blizzard ↗</a><?php endif; ?></div><?php endif; ?>
<?php if($profile): ?>
<div class="armory-layout"><article class="card armory-summary">
<?php if($profile['portrait']): ?><div class="armory-portrait-frame"><img class="armory-portrait" src="<?=e($profile['portrait'])?>" alt="Aspecto de <?=e($profile['name'])?>" loading="lazy" referrerpolicy="no-referrer"></div><?php endif; ?>
<h2><?=e($profile['name'])?></h2><p><?=e($profile['race'].' · '.$profile['class'])?> · Nivel <?=(int)$profile['level']?></p><p><?=e($profile['guild'])?> · Horda · EU</p><p class="armory-ilvl"><strong><?=(int)$profile['item_level']?></strong> nivel de objeto medio</p>
<?php if($profile['updated']): ?><p class="small muted">Actualización de Blizzard: <?=e((new DateTimeImmutable($profile['updated']))->setTimezone(new DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i'))?> (Madrid)</p><?php endif; ?>
<p class="small muted">Consultado: <?=e(date('d/m/Y H:i',(int)$profile['cached_at']))?>. Caché de 10 minutos.<?php if($profile['stale']): ?> Se muestra la última copia disponible porque no se ha podido actualizar.<?php endif; ?></p>
<a class="btn ghost" href="<?=e(nexus_armory_official_url($selected))?>" target="_blank" rel="noopener noreferrer">Ver en Blizzard ↗</a>
</article><div class="armory-equipment" aria-label="Objetos equipados">
<?php foreach($profile['equipment'] as $item): ?><article class="card armory-item"><div class="armory-item-title"><?php if($item['icon']): ?><img src="<?=e($item['icon'])?>" width="48" height="48" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?><div><span class="muted"><?=e($item['slot'])?></span><h3 class="quality-<?=e($item['rarity'])?>"><?=e($item['name'])?></h3></div></div>
<p class="muted">Nivel de objeto <?=(int)$item['level']?><?php if($item['armor']): ?> · <?=(int)$item['armor']?> armadura<?php endif; ?></p>
<?php if($item['stats']): ?><ul><?php foreach($item['stats'] as $stat): ?><li><?=e($stat)?></li><?php endforeach; ?></ul><?php endif; ?>
<?php foreach($item['enchants'] as $enchant): ?><p class="armory-enchant"><?=e($enchant)?></p><?php endforeach; ?>
</article><?php endforeach; ?>
<?php if(!$profile['equipment']): ?><div class="card"><p>Blizzard aún no muestra objetos equipados para este personaje.</p></div><?php endif; ?>
</div></div>
<?php endif; ?>
<p class="armory-source muted">Fuente: armería pública de Blizzard. NEXUS no está afiliada a Blizzard. Si el formato de la página oficial cambia o el perfil no es público, el equipo puede no estar disponible.</p>
</div></section></main>
<?php include __DIR__.'/includes/legal-footer.php'; ?>
</body></html>
