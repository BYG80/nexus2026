<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/normas'); ?>
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/normas.css">
<link rel="icon" type="image/x-icon" href="./favicon.ico">
</head><body>
<?php include __DIR__ . '/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container"><div class="eyebrow">NEXUS · WORLD OF WARCRAFT FOREVER</div><h1>Normas de la hermandad</h1><p>Unas reglas sencillas para que NEXUS sea un lugar agradable para todos.</p></div></header>
<main><section><div class="container"><div class="grid two"><div class="card"><h3>Respeto</h3><p class="muted">Nada de insultos, acoso, discriminación o comportamientos que perjudiquen la convivencia.</p></div><div class="card"><h3>Comunidad</h3><p class="muted">Ayuda cuando puedas y utiliza los canales adecuados para organizar actividades.</p></div><div class="card"><h3>Raids</h3><p class="muted">La puntualidad, preparación y comunicación son importantes cuando se participa en contenido organizado.</p></div><div class="card"><h3>Discord</h3><p class="muted">Mantén el ambiente limpio, evita el spam y respeta las normas generales del servidor.</p></div></div></div></section></main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · Comunidad española</p></div>
    <div><a href="reclutamiento.php">Reclutamiento</a> · <a href="normas.php" aria-current="page">Normas</a></div>
  </div>
  <div class="copyright">© 2026 NEXUS — Veteranos de Azeroth</div>
</footer>
<button class="back-top" aria-label="Volver arriba">↑</button>


</body></html>