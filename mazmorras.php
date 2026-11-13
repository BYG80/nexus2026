<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/mazmorras'); ?>
<link rel="icon" type="image/x-icon" href="./favicon.ico">
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/mazmorras.css">
</head>

<body>

<?php include __DIR__ . '/includes/public-nav.php'; ?>

<header class="page-hero">
  <div class="container">

    <div class="eyebrow">NEXUS · WORLD OF WARCRAFT FOREVER</div>

    <h1>Mazmorras de World of Warcraft Forever</h1>

    <p>
      Contenido de grupo para progresar, conseguir equipo y disfrutar
      del camino junto a la comunidad NEXUS.
    </p>

  </div>
</header>

<main>

  <section>
    <div class="container grid two">

      <article class="card">
        <h2>Grupos organizados</h2>

        <ul class="list">
          <li>Leveo y progreso de personajes</li>
          <li>Farmeo de equipo y objetos</li>
          <li>Logros y objetivos especiales</li>
          <li>Ayuda a nuevos miembros</li>
        </ul>
      </article>

      <article class="card">
        <h2>Siempre hay sitio</h2>

        <p class="muted">
          Queremos que encontrar grupo dentro de NEXUS sea sencillo.
          Discord será el punto principal para anunciar actividades,
          organizar mazmorras y formar grupos.
        </p>
      </article>

    </div>
  </section>

</main>

<footer class="site-footer">
  <div class="container footer-grid">

    <div>
      <strong>NEXUS — VETERANOS DE AZEROTH</strong>
      <p>World of Warcraft Forever · Comunidad española</p>
    </div>

    <div>
      <a href="reclutamiento.php">Reclutamiento</a>
      ·
      <a href="normas.php">Normas</a>
    </div>

  </div>

  <div class="copyright">
    © 2026 NEXUS — Veteranos de Azeroth
  </div>
</footer>

<button
  class="back-top"
  type="button"
  aria-label="Volver arriba">
  ↑
</button>



</body>
</html>

