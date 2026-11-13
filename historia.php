<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- SEO principal -->
  <title>Historia de NEXUS | Veteranos de Azeroth — World of Warcraft Forever</title>
  <meta name="description" content="Conoce la historia, filosofía y objetivos de NEXUS — Veteranos de Azeroth, una comunidad española de World of Warcraft Forever.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://www.nexus-forever.es/historia.php">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_ES">
  <meta property="og:title" content="Historia de NEXUS — Veteranos de Azeroth">
  <meta property="og:description" content="De los primeros días de Azeroth a una nueva aventura en World of Warcraft Forever. Descubre cómo nace NEXUS.">
  <meta property="og:url" content="https://www.nexus-forever.es/historia.php">
  <meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Historia de NEXUS — Veteranos de Azeroth">
  <meta name="twitter:description" content="Conoce la historia de NEXUS y nuestra nueva aventura en World of Warcraft Forever.">

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

  <!-- CSS -->
  <link rel="stylesheet" href="./css/global.css">
  <link rel="stylesheet" href="./css/historia.css">

  <!-- Datos estructurados -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "NEXUS — Veteranos de Azeroth",
    "url": "https://www.nexus-forever.es/",
    "description": "Comunidad española de jugadores de World of Warcraft Forever."
  }
  </script>
</head>

<body>

  <!-- Navegación principal -->
  <?php include __DIR__ . '/includes/public-nav.php'; ?>


  <!-- Cabecera -->
  <header class="page-hero">
    <div class="container">

      <div class="eyebrow">
        NEXUS · WORLD OF WARCRAFT FOREVER
      </div>

      <h1>
        Historia de NEXUS — Veteranos de Azeroth
      </h1>

      <p>
        De los primeros días de Azeroth a una nueva aventura
        en World of Warcraft Forever.
      </p>

    </div>
  </header>


  <main>

    <!-- Historia de la comunidad -->
    <section aria-labelledby="veteranos-title">
      <div class="container grid two">

        <div class="card">

          <h2 id="veteranos-title">
            Veteranos de Azeroth
          </h2>

          <p>
            Una parte de NEXUS está formada por jugadores que llevan
            muchos años recorriendo Azeroth. Conocemos la sensación
            de descubrir una zona por primera vez, preparar una raid
            con amigos y celebrar cada avance del grupo.
          </p>

          <p>
            Forever representa una oportunidad para recuperar esa esencia
            y volver a construir una comunidad desde el principio.
          </p>

        </div>


        <!-- Nueva etapa -->
        <div class="card">

          <h2>
            Nueva etapa de NEXUS
          </h2>

          <p>
            La idea de NEXUS es sencilla: disfrutar del juego sin perder
            el componente social. Queremos una hermandad activa,
            organizada y abierta a jugadores veteranos y nuevos.
          </p>

          <div class="quote">
            “No importa desde cuándo juegas. Importa que quieras
            formar parte de la aventura.”
          </div>

        </div>

      </div>
    </section>

  </main>


  <!-- Pie de página -->
  <footer class="site-footer">

    <div class="container footer-grid">

      <div>

        <strong>
          NEXUS — VETERANOS DE AZEROTH
        </strong>

        <p>
          World of Warcraft Forever · Comunidad española
        </p>

      </div>


      <div>

        <a href="reclutamiento.php">
          Reclutamiento
        </a>

        ·

        <a href="normas.php">
          Normas
        </a>

      </div>

    </div>


    <div class="copyright">
      © 2026 NEXUS — Veteranos de Azeroth
    </div>

  </footer>


  <!-- Botón volver arriba -->
  <button
    class="back-top"
    type="button"
    aria-label="Volver arriba">
    ↑
  </button>


  <!-- JavaScript principal -->
  
</body>
</html>

