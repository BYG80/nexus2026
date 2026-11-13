<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
header('X-Robots-Tag: noindex, follow');



try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(503);
    exit('La sección de miembros no está disponible en este momento.');
}

$faction = nexus_faction();
$rows = [];
$professionMap = [];
$dkpBalances = [];

try {
    $st = $pdo->prepare("SELECT mp.id,mp.name,mp.race,mp.class_name,'#d8ad55' AS class_color,mp.level,'DPS' AS role,'active' AS status,mp.discord_id,m.discord_username,m.discord_avatar
        FROM member_profiles mp
        LEFT JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1
        WHERE mp.faction=? AND m.is_member=1
        ORDER BY mp.class_name,mp.name");
    $st->execute([$faction]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $ps = $pdo->prepare("SELECT member_discord_id,character_name,profession_name,profession_type,skill_level
        FROM professions
        WHERE status='active' AND faction=? AND member_discord_id IS NOT NULL
        ORDER BY member_discord_id,profession_type,profession_name");
    $ps->execute([$faction]);
    foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $professionMap[(string)$pr['member_discord_id']][] = $pr;
    }

    $ids = array_values(array_filter(array_column($rows, 'discord_id')));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT member_discord_id,COALESCE(SUM(amount),0) balance
            FROM dkp_ledger WHERE member_discord_id IN ($ph) GROUP BY member_discord_id");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $dkpBalances[(string)$d['member_discord_id']] = (int)$d['balance'];
        }
    }
} catch (Throwable $e) {
    $rows = [];
}

function member_race_file(string $race): string {
    $r = mb_strtolower(trim($race), 'UTF-8');
    $map = [
        'orco'=>'orco','orc'=>'orco','troll'=>'troll','tauren'=>'tauren',
        'no-muerto'=>'no-muerto','no muerto'=>'no-muerto','undead'=>'no-muerto',
        'humano'=>'humano','enano'=>'enano','gnomo'=>'gnomo',
        'elfo de la noche'=>'elfo-de-la-noche','cielonato'=>'cielonato'
    ];
    return './img/races/' . ($map[$r] ?? 'unknown') . '.svg';
}

function member_class_icon(string $class): string {
    $c = mb_strtolower($class, 'UTF-8');
    return [
        'guerrero'=>'⚔','cazador'=>'➹','pícaro'=>'☠','picaro'=>'☠',
        'sacerdote'=>'✚','chamán'=>'⚡','chaman'=>'⚡','mago'=>'❄',
        'brujo'=>'☄','druida'=>'☘','paladín'=>'✦','paladin'=>'✦'
    ][$c] ?? '✦';
}

$classes = [];
foreach ($rows as $r) {
    $classes[$r['class_name'] ?: 'Sin clase'] = ($classes[$r['class_name'] ?: 'Sin clase'] ?? 0) + 1;
}
ksort($classes);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/members'); ?>
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/roster.css">
<link rel="stylesheet" href="./css/members.css">
<link rel="icon" href="./favicon.ico">
</head>
<body>
<?php include __DIR__ . '/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container">
<div class="eyebrow">NEXUS · HERMANDAD · <?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?></div>
<h1>Miembros de la hermandad</h1>
<p>Consulta pública de los personajes registrados en NEXUS: clases, razas, profesiones y RSP de la hermandad.</p>
</div></header>
<main><section><div class="container">

<div class="roster-controls member-controls">
    <input class="roster-search" id="memberSearch" type="search" placeholder="Buscar personaje, clase, raza o profesión...">
    <button class="roster-filter active" data-filter="all">Todos <small>(<?=count($rows)?>)</small></button>
    <?php foreach(array_keys($classes) as $c): ?><button class="roster-filter" data-filter="<?=e($c)?>"><?=e($c)?> <small>(<?=$classes[$c]?>)</small></button><?php endforeach; ?>
</div>
<div class="member-grid" id="memberGrid">
<?php foreach($rows as $r):
    $did=(string)($r['discord_id']??'');
    $profs=$professionMap[$did]??[];
    $search=mb_strtolower(($r['name']??'').' '.($r['race']??'').' '.($r['class_name']??''), 'UTF-8');
    foreach($profs as $pr) $search.=' '.mb_strtolower((string)$pr['profession_name'],'UTF-8');
?>
<article class="member-card" data-class="<?=e($r['class_name']?:'Sin clase')?>" data-search="<?=e($search)?>">
    <div class="member-card-top">
        <div class="member-avatar-wrap">
            <?php if(!empty($r['discord_avatar'])): ?><img class="member-avatar" src="<?=e($r['discord_avatar'])?>" alt=""><?php else: ?><div class="member-avatar-fallback">✦</div><?php endif; ?>
        </div>
        <div class="member-title">
            <div class="member-race"><img src="<?=e(member_race_file((string)$r['race']))?>" alt="<?=e($r['race'])?>"> <?=e($r['race'])?></div>
            <h2><?=e($r['name'])?></h2>
            <div class="member-class"><?=e(member_class_icon((string)$r['class_name']))?> <?=e($r['class_name']?:'Clase no indicada')?><?php if($r['role']): ?> · <?=e($r['role'])?><?php endif; ?></div>
        </div>
        <div class="member-rsp"><strong><?= (int)($dkpBalances[$did]??0) ?></strong><span>RSP</span></div>
    </div>
    <div class="member-details">
        <div class="member-line"><span>Nivel</span><strong><?=e((string)($r['level']?:'—'))?></strong></div>
        <div class="member-line"><span>Discord</span><strong><?=e((string)($r['discord_username']?:'Miembro NEXUS'))?></strong></div>
    </div>
    <div class="member-professions">
        <h3>🛠 Profesiones</h3>
        <?php if($profs): ?><div class="profession-pills">
            <?php foreach($profs as $pr): ?><span class="profession-pill <?=e((string)$pr['profession_type'])?>"><?=e($pr['profession_name'])?><?php if($pr['skill_level']!==null && $pr['skill_level']!==''): ?> <b><?=e((string)$pr['skill_level'])?></b><?php endif; ?></span><?php endforeach; ?>
        </div><?php else: ?><p class="muted">Sin profesiones registradas.</p><?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php if(!$rows): ?><div class="card"><h2>No hay personajes publicados</h2><p class="muted">Los miembros pueden completar su personaje desde <a href="/panel">Mi panel</a>.</p></div><?php endif; ?>
</div></section></main>
<footer class="site-footer"><div class="container"><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>Directorio público de personajes de la hermandad.</p></div></footer>

<script>(function(){const q=document.getElementById('memberSearch'),bs=[...document.querySelectorAll('.roster-filter')],cs=[...document.querySelectorAll('.member-card')];function go(){const term=(q.value||'').toLowerCase().trim(),f=document.querySelector('.roster-filter.active')?.dataset.filter||'all';cs.forEach(c=>{const okF=f==='all'||c.dataset.class===f,okQ=!term||c.dataset.search.includes(term);c.style.display=okF&&okQ?'':'none'});}q?.addEventListener('input',go);bs.forEach(b=>b.addEventListener('click',()=>{bs.forEach(x=>x.classList.remove('active'));b.classList.add('active');go()}));})();</script>
</body></html>
