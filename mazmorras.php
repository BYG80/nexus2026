<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Mazmorras de World of Warcraft Forever | NEXUS</title>

  <meta name="description" content="Encuentra grupos para mazmorras de World of Warcraft Forever en NEXUS: leveo, equipo, logros, eventos y contenido de grupo.">

  <meta name="robots" content="index, follow">

  <link rel="canonical" href="https://www.nexus-forever.es/mazmorras.php">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_ES">
  <meta property="og:title" content="Mazmorras de World of Warcraft Forever | NEXUS">
  <meta property="og:description" content="Forma grupos para disfrutar de las mazmorras de World of Warcraft Forever, conseguir equipo, completar objetivos y avanzar junto a NEXUS.">
  <meta property="og:url" content="https://www.nexus-forever.es/mazmorras.php">
  <meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="Mazmorras de World of Warcraft Forever | NEXUS">
  <meta name="twitter:description" content="Mazmorras, grupos y contenido cooperativo de World of Warcraft Forever con NEXUS — Veteranos de Azeroth.">

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

  <!-- CSS -->
  <link rel="stylesheet" href="./css/global.css">
  <link rel="stylesheet" href="./css/mazmorras.css">

  <!-- Schema.org -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Mazmorras de World of Warcraft Forever | NEXUS",
    "description": "Descubre las mazmorras de World of Warcraft Forever con NEXUS: grupos organizados para levear, conseguir equipo, completar logros y ayudar a otros miembros de la comunidad.",
    "url": "https://www.nexus-forever.es/mazmorras.php",
    "isPartOf": {
      "@type": "WebSite",
      "name": "NEXUS — Veteranos de Azeroth",
      "url": "https://www.nexus-forever.es/"
    },
    "inLanguage": "es"
  }
  </script>
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

