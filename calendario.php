<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Calendario de eventos de WoW Forever | NEXUS</title>

<meta name="description" content="Consulta el calendario de eventos de NEXUS en WoW Forever: eventos, mazmorras y actividades de la hermandad.">

<meta name="robots" content="index, follow">
<meta name="author" content="NEXUS — Veteranos de Azeroth">

<!-- URL canónica -->
<link rel="canonical" href="https://www.nexus-forever.es/calendario.php">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:locale" content="es_ES">
<meta property="og:title" content="Calendario de eventos de WoW Forever | NEXUS">
<meta property="og:description" content="Consulta el calendario de eventos de NEXUS en WoW Forever: eventos, mazmorras y actividades de la hermandad.">
<meta property="og:url" content="https://www.nexus-forever.es/calendario.php">
<meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">
<meta property="og:image" content="https://www.nexus-forever.es/img/galeria/Azeroth01.jpg">
<meta property="og:image:alt" content="NEXUS — Veteranos de Azeroth en World of Warcraft Forever">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Calendario de eventos de WoW Forever | NEXUS">
<meta name="twitter:description" content="Consulta el calendario de eventos de NEXUS en WoW Forever: eventos, mazmorras y actividades de la hermandad.">
<meta name="twitter:image" content="https://www.nexus-forever.es/img/galeria/Azeroth01.jpg">
<meta name="twitter:image:alt" content="NEXUS — Veteranos de Azeroth en World of Warcraft Forever">

<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/calendario.css">
<link rel="icon" type="image/x-icon" href="./favicon.ico">

<!-- =====================================================
     DATOS ESTRUCTURADOS
     ===================================================== -->

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "@id": "https://www.nexus-forever.es/calendario.php#webpage",
  "url": "https://www.nexus-forever.es/calendario.php",
  "name": "Calendario de eventos de WoW Forever | NEXUS",
  "description": "Calendario de eventos de NEXUS en World of Warcraft Forever.",
  "inLanguage": "es-ES",
  "isPartOf": {
    "@type": "WebSite",
    "@id": "https://www.nexus-forever.es/#website",
    "url": "https://www.nexus-forever.es/",
    "name": "NEXUS — Veteranos de Azeroth"
  },
  "about": {
    "@type": "VideoGame",
    "name": "World of Warcraft"
  },
  "publisher": {
    "@type": "Organization",
    "name": "NEXUS — Veteranos de Azeroth",
    "url": "https://www.nexus-forever.es/"
  }
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Inicio",
      "item": "https://www.nexus-forever.es/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Calendario",
      "item": "https://www.nexus-forever.es/calendario.php"
    }
  ]
}
</script>

</head>

<body>

<?php include __DIR__ . '/includes/public-nav.php'; ?>

<header class="page-hero">
    <div class="container">

        <div class="eyebrow">
            NEXUS · WORLD OF WARCRAFT FOREVER
        </div>

        <h1>Calendario de eventos de WoW Forever</h1>

        <p>
            Consulta y organiza los próximos eventos de NEXUS:
            raids, mazmorras, eventos y actividades de hermandad
            y actividades de la hermandad.
        </p>

    </div>
</header>

<main>

<section>
<div class="container">

    <!-- INTRO SEO -->

    <div class="card" style="margin-bottom:18px;">

        <h2>Eventos de NEXUS en WoW Forever</h2>

        <p>
            Aquí encontrarás el calendario de actividades de
            <strong>NEXUS — Veteranos de Azeroth</strong>, nuestra comunidad
            española de <strong>World of Warcraft Forever</strong>.
        </p>

        <p>
            Consulta las próximas <strong>mazmorras, eventos y actividades de hermandad</strong>.
            Los miembros de NEXUS pueden iniciar sesión con Discord para
            consultar los eventos y apuntarse directamente desde la web.
        </p>

    </div>

    <!-- CUENTA / DISCORD -->

    <div
        id="calendarAccount"
        class="card"
        style="margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    </div>

    <!-- CALENDARIO -->

    <div class="calendar-wrap">

        <div class="card calendar-card">

            <div class="calendar-head">

                <button
                    class="icon-btn"
                    id="prevMonth"
                    type="button"
                    aria-label="Mes anterior">
                    ←
                </button>

                <h2 id="monthTitle"></h2>

                <button
                    class="icon-btn"
                    id="nextMonth"
                    type="button"
                    aria-label="Mes siguiente">
                    →
                </button>

            </div>

            <div class="weekdays">
                <div>Lun</div>
                <div>Mar</div>
                <div>Mié</div>
                <div>Jue</div>
                <div>Vie</div>
                <div>Sáb</div>
                <div>Dom</div>
            </div>

            <div
                class="calendar-grid"
                id="calendarGrid"
                aria-label="Calendario de eventos">
            </div>

            <p class="calendar-note">
                Consulta el calendario para conocer las próximas actividades
                de la hermandad.
            </p>

        </div>

        <!-- PRÓXIMOS EVENTOS -->

        <div class="card">

            <h2>Próximos eventos de NEXUS</h2>

            <div
                id="eventList"
                class="event-list">
            </div>

            <p class="calendar-note">
                Los eventos y sus plazas disponibles se actualizan
                automáticamente.
            </p>

        </div>

    </div>

    <!-- INFORMACIÓN SEO ADICIONAL -->

    <div class="card" style="margin-top:18px;">

        <h2>Participa en los eventos de la hermandad</h2>

        <p>
            Si formas parte de NEXUS, puedes utilizar nuestro sistema de
            inscripción para apuntarte a los eventos de
            <strong>WoW Forever</strong>.
        </p>

        <p>
            Las actividades pueden incluir contenido PvE como
            <strong>mazmorras y raids</strong>, además de actividades
            <strong>eventos y actividades de hermandad</strong>.
            Consulta regularmente el calendario para conocer las próximas
            actividades organizadas por la hermandad.
        </p>

    </div>

</div>
</section>

</main>

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

            <a href="reclutamiento">
                Reclutamiento
            </a>

            ·

            <a href="normas">
                Normas
            </a>

        </div>

    </div>

    <div class="copyright">
        © 2026 NEXUS — Veteranos de Azeroth
    </div>

</footer>

<button
    class="back-top"
    aria-label="Volver arriba">
    ↑
</button>


<script src="./js/calendario.js?v=20261013-2"></script>

</body>
</html>