<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/historia'); ?>
<link rel="icon" type="image/x-icon" href="./favicon.ico">
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/historia.css">
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

