<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/guild-info.php';
$guildInfo = nexus_guild_info();

$heading = 'Hermandad ' . nexus_faction_label() . ' de World of Warcraft Forever';
$intro = 'Una comunidad española para disfrutar de Azeroth con compañeros, organización y ganas de jugar.';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/hermandad-wow-forever'); ?>
<link rel="icon" href="./favicon.ico">
<link rel="stylesheet" href="./css/global.css">
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
<section><div class="container"><h2>Cómo entrar en NEXUS</h2><p>Somos una hermandad española de la <?=e(nexus_faction_label())?>. Para conocernos, entra en Discord y consulta las normas de convivencia. Si quieres participar, envía tu solicitud con tu personaje y disponibilidad.</p><div class="actions"><a class="btn primary" href="<?=e($guildInfo['discord_invite'])?>" target="_blank" rel="noopener noreferrer">Entrar en Discord</a><a class="btn ghost" href="/normas">Leer las normas</a></div><h2 style="margin-top:32px">Horarios de raids y actividades</h2><p><?=e($guildInfo['raid_schedule'] ?: 'Los horarios regulares se confirman en Discord. Consulta el calendario para ver las fechas de las actividades publicadas.')?><?php if ($guildInfo['raid_schedule']): ?> · Zona horaria: <?=e($guildInfo['raid_timezone'])?>.<?php endif; ?></p><p><a href="/calendario">Ver las próximas actividades de NEXUS →</a></p></div></section>
</main>
<footer class="site-footer"><div class="container"><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · <?=e(nexus_faction_label())?> · Comunidad española</p></div></footer>

</body></html>
