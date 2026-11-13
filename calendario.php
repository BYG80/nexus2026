<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="nexus-csrf" content="<?=e(csrf_token())?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/calendario'); ?>
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/calendario.css">
<link rel="icon" type="image/x-icon" href="./favicon.ico">
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
            raids, mazmorras, eventos y actividades de la hermandad.
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


<script src="./js/calendario.js?v=<?=filemtime(__DIR__ . '/js/calendario.js')?>"></script>

</body>
</html>