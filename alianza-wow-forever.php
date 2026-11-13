<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

require_once __DIR__ . '/includes/seo.php';

$slug = basename($_SERVER['SCRIPT_NAME'], '.php');
$current = nexus_faction();
$isFactionPage = in_array($slug, ['horda-wow-forever','alianza-wow-forever'], true);
$pageFaction = $slug === 'alianza-wow-forever' ? 'alianza' : ($slug === 'horda-wow-forever' ? 'horda' : $current);

if ($isFactionPage && $pageFaction !== $current) {
    http_response_code(404);
    $robots = 'noindex, follow';
} else {
    $robots = 'index, follow, max-image-preview:large';
}

$label = $pageFaction === 'alianza' ? 'Alianza' : 'Horda';
if ($slug === 'hermandad-wow-forever') {
    $title = 'Hermandad WoW Forever | NEXUS — Veteranos de Azeroth';
    $description = 'NEXUS es una hermandad española de World of Warcraft Forever. Descubre nuestra comunidad, roster, eventos, mazmorras, profesiones, RSP y reclutamiento.';
    $heading = 'Hermandad de World of Warcraft Forever';
    $intro = 'Una comunidad española para disfrutar World of Warcraft Forever con organización, compañeros y ganas de jugar.';
} elseif ($slug === 'world-of-warcraft-forever') {
    $title = 'World of Warcraft Forever | Comunidad y hermandad NEXUS';
    $description = 'NEXUS reúne jugadores españoles de World of Warcraft Forever con roster, eventos, mazmorras, profesiones, RSP y reclutamiento.';
    $heading = 'World of Warcraft Forever';
    $intro = 'Todo lo que necesitas para conocer NEXUS y nuestra forma de vivir World of Warcraft Forever.';
} else {
    $title = 'Hermandad '.$label.' WoW Forever | NEXUS';
    $description = 'NEXUS es una hermandad '.$label.' de World of Warcraft Forever. Consulta el roster, clases, razas, eventos, profesiones, RSP y cómo unirte.';
    $heading = 'Hermandad '.$label.' WoW Forever';
    $intro = 'Conoce la facción '.$label.' de NEXUS en World of Warcraft Forever, nuestro roster y cómo formar parte de la hermandad.';
}
$canonical = nexus_canonical('/'.$slug);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?></title>
<meta name="description" content="<?=e($description)?>">
<meta name="robots" content="<?=e($robots)?>">
<link rel="canonical" href="<?=e($canonical)?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_ES">
<meta property="og:title" content="<?=e($title)?>">
<meta property="og:description" content="<?=e($description)?>">
<meta property="og:url" content="<?=e($canonical)?>">
<meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">
<meta property="og:image" content="<?=e(nexus_canonical('/img/galeria/Azeroth01.jpg'))?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=e($title)?>">
<meta name="twitter:description" content="<?=e($description)?>">
<meta name="twitter:image" content="<?=e(nexus_canonical('/img/galeria/Azeroth01.jpg'))?>">
<link rel="icon" href="./favicon.ico">
<link rel="stylesheet" href="./css/global.css">
<script type="application/ld+json"><?=nexus_jsonld(['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$title,'description'=>$description,'url'=>$canonical,'isPartOf'=>['@type'=>'WebSite','name'=>'NEXUS — Veteranos de Azeroth','url'=>nexus_canonical('/')]])?></script>
</head>
<body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container"><div class="eyebrow">NEXUS · WORLD OF WARCRAFT FOREVER</div><h1><?=e($heading)?></h1><p><?=e($intro)?></p></div></header>
<main>
<section><div class="container"><div class="grid two">
<article class="card"><h2>Una hermandad para jugar juntos</h2><p>NEXUS está pensada para jugadores que buscan una comunidad estable, organizada y activa en World of Warcraft Forever. Aquí puedes conocer a nuestros personajes, consultar eventos y descubrir cómo participar.</p><p>La configuración actual de la hermandad es <strong><?=e(nexus_faction_label())?></strong>. Las razas y clases disponibles se adaptan automáticamente a la facción seleccionada por la administración.</p></article>
<article class="card"><h2>Qué encontrarás en NEXUS</h2><ul class="list"><li>Roster público de personajes y clases.</li><li>Calendario de eventos y actividades.</li><li>Profesiones integradas en el roster.</li><li>Sistema RSP (Raid System Points).</li><li>Reclutamiento para nuevos miembros.</li><li>Acceso de miembros mediante Discord.</li></ul></article>
</div></div></section>
<section><div class="container"><div class="card"><h2>World of Warcraft Forever en NEXUS</h2><p>Si estás buscando una <strong>hermandad WoW</strong>, una <strong>guild WoW Forever</strong> o una comunidad española con la que jugar, puedes empezar con nuestro roster y nuestro sistema de reclutamiento.</p><div class="actions"><a class="btn primary" href="/reclutamiento">Quiero unirme</a><a class="btn ghost" href="/roster">Ver roster</a><a class="btn ghost" href="/calendario">Ver eventos</a></div></div></div></section>
</main>
<footer class="site-footer"><div class="container"><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · <?=e(nexus_faction_label())?> · Comunidad española</p></div></footer>

</body></html>
