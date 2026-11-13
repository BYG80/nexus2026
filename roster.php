<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

require_once __DIR__ . '/includes/seo.php';
$rows=[];
$currentFaction=nexus_faction();
try {
    if (nexus_is_configured()) {
        $pdo = db();
        // Compatibilidad con instalaciones antiguas: si el panel de administración
        // todavía no ha visitado la sección de roster, aseguramos aquí las columnas
        // necesarias para que el roster público no quede vacío silenciosamente.
        try { $pdo->exec("ALTER TABLE roster ADD COLUMN source ENUM('admin','member') NOT NULL DEFAULT 'admin' AFTER faction"); } catch(Throwable $ignore) {}
        try { $pdo->exec("ALTER TABLE roster ADD COLUMN roster_group VARCHAR(2) NOT NULL DEFAULT 'A' AFTER notes"); } catch(Throwable $ignore) {}
        try { $pdo->exec("ALTER TABLE roster MODIFY roster_group VARCHAR(2) NOT NULL DEFAULT 'A'"); } catch(Throwable $ignore) {}
        try { $pdo->exec("UPDATE roster SET roster_group='A' WHERE roster_group IS NULL OR roster_group NOT IN ('A','B','20','40')"); } catch(Throwable $ignore) {}
        $st=$pdo->prepare("SELECT id,name,race,class_name,class_color,level,role,status,notes,COALESCE(NULLIF(roster_group,''),'A') AS roster_group FROM roster WHERE faction=? ORDER BY FIELD(COALESCE(NULLIF(roster_group,''),'A'),'A','B','20','40'),class_name,name");
        $st->execute([$currentFaction]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Throwable $e) {
    // No dejamos la página vacía por una incompatibilidad de esquema.
    // Como último recurso, mostramos los personajes administrativos usando A como grupo.
    try {
        if (nexus_is_configured()) {
            $st=db()->prepare("SELECT id,name,race,class_name,class_color,level,role,status,notes,COALESCE(NULLIF(roster_group,''),'A') AS roster_group FROM roster WHERE faction=? ORDER BY FIELD(COALESCE(NULLIF(roster_group,''),'A'),'A','B','20','40'),class_name,name");
            $st->execute([$currentFaction]);
            $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Throwable $ignore) { $rows=[]; }
}
function roster_race_file(string $race): string {
    $r=mb_strtolower(trim($race),'UTF-8');
    $map=[
        'orco'=>'orco','orc'=>'orco',
        'troll'=>'troll',
        'tauren'=>'tauren',
        'no-muerto'=>'no-muerto','no muerto'=>'no-muerto','undead'=>'no-muerto',
        'humano'=>'humano','enano'=>'enano','gnomo'=>'gnomo','elfo de la noche'=>'elfo-de-la-noche',
        'cielonato'=>'cielonato','cielonato formaviento'=>'cielonato','cielonato formaviento'=>'cielonato'
    ];
    $file=$map[$r]??'';
    return $file!=='' ? './img/races/'.$file.'.svg' : './img/races/unknown.svg';
}
function roster_class_icon(string $class): string {
    $c=mb_strtolower($class,'UTF-8');
    $map=['guerrero'=>'⚔','cazador'=>'➹','picaro'=>'☠','pícaro'=>'☠','sacerdote'=>'✚','chaman'=>'⚡','chamán'=>'⚡','mago'=>'❄','brujo'=>'☄','druida'=>'☘'];
    return $map[$c]??'✦';
}
$active=array_values(array_filter($rows,fn($r)=>($r['status']??'active')==='active'));
$inactive=array_values(array_filter($rows,fn($r)=>($r['status']??'active')!=='active'));
$classes=[]; foreach($active as $r){$classes[$r['class_name']?:'Sin clase']=($classes[$r['class_name']?:'Sin clase']??0)+1;} ksort($classes);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/roster'); ?>
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/roster.css">
<link rel="icon" href="./favicon.ico">
</head><body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container"><div class="eyebrow">NEXUS · HERMANDAD · <?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?></div><h1>Roster de NEXUS</h1><p>Los héroes que forman nuestra hermandad. Descubre quiénes somos, qué clase jugamos y qué papel desempeñamos en Azeroth.</p></div></header>
<main class="roster-page"><section><div class="container">
<div class="roster-intro"><div class="card"><div class="eyebrow">NEXUS · VETERANOS DE AZEROTH</div><h2>La hermandad en combate</h2><p class="muted">Nuestro roster está organizado por clases y roles para facilitar la creación de grupos, mazmorras, eventos y futuras actividades de hermandad.</p></div><div class="roster-stat-panel"><div class="roster-stat"><strong><?=count($active)?></strong><span>Activos</span></div><div class="roster-stat"><strong><?=count($classes)?></strong><span>Clases</span></div><div class="roster-stat"><strong><?=count($rows)?></strong><span>Total</span></div></div></div>
<div class="roster-controls"><input class="roster-search" id="rosterSearch" type="search" placeholder="Buscar personaje, clase o raza..."><div class="roster-size-filters"><button class="roster-size-filter active" data-roster-size="all">Todos los raids</button><button class="roster-size-filter" data-roster-size="A">Raid 10 · A</button><button class="roster-size-filter" data-roster-size="B">Raid 10 · B</button><button class="roster-size-filter" data-roster-size="20">Raid 20</button><button class="roster-size-filter" data-roster-size="40">Raid 40</button></div><div class="roster-class-filters"><button class="roster-filter active" data-filter="all">Todas las clases</button><?php foreach(array_keys($classes) as $c): ?><button class="roster-filter" data-filter="<?=e($c)?>"><?=e($c)?> <small>(<?=$classes[$c]?>)</small></button><?php endforeach; ?></div></div>
<?php if(!$rows): ?><div class="card roster-empty"><h2>Roster en preparación</h2><p class="muted">Todavía no hay personajes publicados. Añádelos desde el panel de administración.</p></div><?php else: ?>
<div id="rosterGrid">
<?php foreach(['A'=>'Raid 10 · Roster A','B'=>'Raid 10 · Roster B','20'=>'Raid 20','40'=>'Raid 40'] as $groupKey=>$groupTitle): $groupRows=array_values(array_filter($active,fn($item)=>(($item['roster_group']??'A')===$groupKey))); ?><div class="roster-group roster-group-<?=e($groupKey)?>"><div class="roster-group-head"><h2><?=e($groupTitle)?></h2><span class="group-count"><?=count($groupRows)?> personaje(s)</span><span class="group-line"></span></div><?php if (!$groupRows): ?><p class="roster-group-empty">Todavía no hay personajes asignados a <?=e($groupTitle)?>.</p><?php else: ?><div class="roster-grid"><?php foreach($groupRows as $r): $color=$r['class_color']?:'#d8ad55'; ?><article class="roster-card" data-roster-group="<?=e($groupKey)?>" data-class="<?=e($r['class_name']?:'Sin clase')?>" data-search="<?=e(mb_strtolower(($r['name']??'').' '.($r['race']??'').' '.($r['class_name']??''),'UTF-8'))?>" style="--class-color:<?=e($color)?>"><span class="roster-status">Activo</span><div class="roster-main"><div class="roster-portrait"><span class="roster-level"><?=($r['level']?'Nivel '.e((string)$r['level']):'NIVEL —')?></span><img src="<?=e(roster_race_file((string)$r['race']))?>" alt="<?=e($r['race'])?>"></div><div class="roster-info"><div class="roster-race"><?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?> · <?=e($r['race'])?></div><h3 class="roster-name"><?=e($r['name'])?></h3><div class="roster-class"><span class="class-gem"></span><?=e(roster_class_icon((string)$r['class_name']))?> <?=e($r['class_name']?:'Clase no indicada')?></div><div class="roster-meta"><?php if($r['role']): ?><span class="roster-badge roster-role">🛡 <?=e($r['role'])?></span><?php endif; ?><?php if($r['level']): ?><span class="roster-badge">★ Nivel <?=e((string)$r['level'])?></span><?php endif; ?></div><?php if($r['notes']): ?><p class="roster-notes"><?=e($r['notes'])?></p><?php endif; ?></div></div></article><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?></div>
<?php if($inactive): ?><div class="roster-group"><div class="roster-group-head"><h2>⚪ Personajes inactivos</h2><span class="group-line"></span></div><div class="roster-grid"><?php foreach($inactive as $r): $color=$r['class_color']?:'#777'; ?><article class="roster-card roster-inactive" data-roster-group="<?=e($r['roster_group']??'A')?>" data-class="<?=e($r['class_name']?:'Sin clase')?>" data-search="<?=e(mb_strtolower(($r['name']??'').' '.($r['race']??'').' '.($r['class_name']??''),'UTF-8'))?>" style="--class-color:<?=e($color)?>"><span class="roster-status off">Inactivo</span><div class="roster-main"><div class="roster-portrait"><img src="<?=e(roster_race_file((string)$r['race']))?>" alt="<?=e($r['race'])?>"></div><div class="roster-info"><div class="roster-race"><?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?> · <?=e($r['race'])?></div><h3 class="roster-name"><?=e($r['name'])?></h3><div class="roster-class"><span class="class-gem"></span><?=e(roster_class_icon((string)$r['class_name']))?> <?=e($r['class_name']?:'Clase no indicada')?></div><div class="roster-meta"><span class="roster-badge">Inactivo</span><?php if($r['level']): ?><span class="roster-badge">★ Nivel <?=e((string)$r['level'])?></span><?php endif; ?></div></div></div></article><?php endforeach; ?></div></div><?php endif; ?><?php endif; ?>
</div></section></main><footer class="site-footer"><div class="container"><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · Comunidad española</p></div></footer>
<script>(function(){const q=document.getElementById('rosterSearch'),classBtns=[...document.querySelectorAll('.roster-filter')],sizeBtns=[...document.querySelectorAll('.roster-size-filter')],groups=[...document.querySelectorAll('.roster-group')],cards=[...document.querySelectorAll('.roster-card')];function go(){const term=(q?.value||'').toLowerCase().trim(),cf=document.querySelector('.roster-filter.active')?.dataset.filter||'all',sf=document.querySelector('.roster-size-filter.active')?.dataset.rosterSize||'all';cards.forEach(c=>{const okF=cf==='all'||c.dataset.class===cf,okS=sf==='all'||c.dataset.rosterGroup===sf,okQ=!term||c.dataset.search.includes(term);c.style.display=okF&&okS&&okQ?'':'none'});groups.forEach(g=>{const visible=[...g.querySelectorAll('.roster-card')].some(c=>c.style.display!=='none');g.style.display=visible?'':'none';});}q?.addEventListener('input',go);classBtns.forEach(b=>b.addEventListener('click',()=>{classBtns.forEach(x=>x.classList.remove('active'));b.classList.add('active');go()}));sizeBtns.forEach(b=>b.addEventListener('click',()=>{sizeBtns.forEach(x=>x.classList.remove('active'));b.classList.add('active');go()}));})();</script></body></html>
