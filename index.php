<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/news-html.php';

$latestNews = [];

/*
 * Limpieza segura del contenido de las noticias.
 * Permitimos únicamente etiquetas básicas y enlaces.
 */
function sanitize_home_news_html(string $html): string
{
    return nexus_news_html($html);
}


/*
 * Crea un resumen de la noticia sin cortar etiquetas HTML.
 */
function news_excerpt(string $html, int $length = 190): string
{
    $safe = sanitize_home_news_html($html);

    // Si la noticia es corta, mostramos todo.
    $plainText = trim(
        preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        )
    );

    if (mb_strlen($plainText, 'UTF-8') <= $length) {
        return $safe;
    }

    /*
     * Para evitar cortar un enlace a la mitad,
     * construimos el resumen a partir del texto visible.
     */
    $excerpt = mb_substr(
        $plainText,
        0,
        $length,
        'UTF-8'
    );

    // Intentamos terminar en una palabra completa.
    $lastSpace = mb_strrpos(
        $excerpt,
        ' ',
        0,
        'UTF-8'
    );

    if ($lastSpace !== false && $lastSpace > 100) {
        $excerpt = mb_substr(
            $excerpt,
            0,
            $lastSpace,
            'UTF-8'
        );
    }

    return e($excerpt) . '…';
}


try {

    if (nexus_is_configured()) {

        $latestNews = db()->query("
            SELECT id, title, body, published_at
            FROM news
            WHERE published = 1
            ORDER BY published_at DESC, id DESC
            LIMIT 3
        ")->fetchAll();
    }

} catch (Throwable $e) {

    $latestNews = [];
}

?>
<!doctype html>
<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <!-- SEO principal -->

    <?php $seoFaction = nexus_faction_label(); ?>
    <title><?=e(nexus_seo_title('Hermandad de World of Warcraft Forever'))?></title>
    <meta name="description" content="<?=e(nexus_seo_description('NEXUS es una hermandad española de '.$seoFaction.' en World of Warcraft Forever. Conoce nuestro roster, eventos, mazmorras, profesiones, RSP y reclutamiento.'))?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="<?=e(nexus_canonical('/'))?>">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_ES">
    <meta property="og:title" content="<?=e(nexus_seo_title('Hermandad de World of Warcraft Forever'))?>">
    <meta property="og:description" content="<?=e(nexus_seo_description('Hermandad española '.$seoFaction.' de World of Warcraft Forever. Roster, eventos, mazmorras, profesiones, RSP y reclutamiento.'))?>">
    <meta property="og:url" content="<?=e(nexus_canonical('/'))?>">
    <meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">
    <meta property="og:image" content="<?=e(nexus_canonical('/img/galeria/Azeroth01.jpg'))?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?=e(nexus_seo_title('Hermandad de World of Warcraft Forever'))?>">
    <meta name="twitter:description" content="<?=e(nexus_seo_description('NEXUS — hermandad '.$seoFaction.' de World of Warcraft Forever.'))?>">
    <meta name="twitter:image" content="<?=e(nexus_canonical('/img/galeria/Azeroth01.jpg'))?>">

    <!-- Favicon -->

    <link
        rel="icon"
        type="image/x-icon"
        href="./favicon.ico"
    >


    <!-- CSS -->

    <link
        rel="stylesheet"
        href="./css/global.css"
    >

    <link
        rel="stylesheet"
        href="./css/index.css?v=<?=filemtime(__DIR__ . '/css/index.css')?>"
    >


    <!-- Estilos de las noticias -->




    <!-- Datos estructurados -->

    <script type="application/ld+json">
<?=nexus_jsonld([
    '@context'=>'https://schema.org',
    '@graph'=>[
        ['@type'=>'WebSite','@id'=>nexus_canonical('/').'#website','url'=>nexus_canonical('/'),'name'=>'NEXUS — Veteranos de Azeroth','description'=>'Hermandad española de World of Warcraft Forever.'],
        ['@type'=>'Organization','@id'=>nexus_canonical('/').'#organization','name'=>'NEXUS — Veteranos de Azeroth','url'=>nexus_canonical('/'),'description'=>'Hermandad española de '.nexus_faction_label().' en World of Warcraft Forever.'],
        ['@type'=>'WebPage','@id'=>nexus_canonical('/').'#webpage','url'=>nexus_canonical('/'),'name'=>nexus_seo_title('Hermandad de World of Warcraft Forever'),'isPartOf'=>['@id'=>nexus_canonical('/').'#website']]
    ]
])?>
    </script>

</head>


<body class="home-page">


    <!-- Navegación -->

    <?php include __DIR__ . '/includes/public-nav.php'; ?>


    <!-- Cabecera principal -->

    <header class="page-hero">

        <div class="container">

            <div class="eyebrow">
                NEXUS · WORLD OF WARCRAFT FOREVER
            </div>

            <h1>
                NEXUS — Veteranos de Azeroth
            </h1>

            <p>
                El hogar de los veteranos. Una hermandad creada para vivir
                World of Warcraft Forever con comunidad, organización y ganas de jugar.
            </p>

        </div>

    </header>


    <main>



        <!-- Carrusel de imágenes de NEXUS -->
        <section class="nexus-slider-section" aria-label="Imágenes de Azeroth">
            <div class="container">
                <div class="nexus-slider" data-nexus-slider role="region" aria-label="Aventuras de NEXUS" aria-roledescription="carrusel" tabindex="0">
                    <button class="nexus-slider-pause" type="button" aria-pressed="false">Pausar carrusel</button>
                    <div class="nexus-slides" aria-live="off">
                        <div class="nexus-slide is-active" aria-hidden="false">
                            <img src="./img/galeria/Azeroth01.webp" width="1920" height="1080" decoding="async" alt="Paisajes de Azeroth" fetchpriority="high">
                            <div class="nexus-slide-caption"><strong>Explora Azeroth con NEXUS</strong><span>Una comunidad de veteranos, nuevas aventuras.</span></div>
                        </div>
                        <div class="nexus-slide" aria-hidden="true">
                            <img src="./img/galeria/Azeroth02.webp" width="1920" height="1080" decoding="async" alt="Aventuras en el mundo de Azeroth" loading="lazy">
                            <div class="nexus-slide-caption"><strong>La aventura continúa</strong><span>Mazmorras, eventos y grandes momentos juntos.</span></div>
                        </div>
                    </div>
                    <p class="sr-only" data-slider-status aria-live="polite" aria-atomic="true"></p>
                    <button class="nexus-slider-arrow nexus-slider-prev" type="button" aria-label="Imagen anterior">&#10094;</button>
                    <button class="nexus-slider-arrow nexus-slider-next" type="button" aria-label="Imagen siguiente">&#10095;</button>
                    <div class="nexus-slider-dots" role="group" aria-label="Seleccionar imagen">
                        <button class="is-active" type="button" aria-label="Ver imagen 1" aria-current="true"></button>
                        <button type="button" aria-label="Ver imagen 2" aria-current="false"></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Presentación -->

        <section
            class="hero"
            aria-labelledby="hero-title"
        >

            <div>

                <div class="eyebrow">
                    World of Warcraft Forever
                </div>

                <h2 id="hero-title">
                    <span>NEXUS</span><br>
                    Veteranos de Azeroth
                </h2>

                <p>
                    Algunos llevamos jugando desde los primeros días de Azeroth.
                    Ahora volvemos a unirnos para disfrutar de Forever, descubrir
                    su contenido y construir una comunidad duradera.
                </p>

                <div class="actions">

                    <a
                        class="btn primary"
                        href="reclutamiento.php"
                    >
                        Quiero unirme
                    </a>

                    <a
                        class="btn ghost"
                        href="historia.php"
                    >
                        Conócenos
                    </a>

                </div>

            </div>

        </section>


        <!-- Últimas noticias -->

        <section aria-labelledby="news-title">

            <div class="container">

                <h2 class="section-title" id="news-title">Últimas noticias</h2>

                <p class="section-lead">
                    Novedades, avisos y comunicaciones de NEXUS.
                </p>


                <?php if ($latestNews): ?>


                    <div class="grid">


                        <?php foreach ($latestNews as $news): ?>


                            <article class="card news-card">


                                <div class="eyebrow">

                                    <?=e(
                                        date(
                                            'd/m/Y',
                                            strtotime($news['published_at'])
                                        )
                                    )?>

                                </div>


                                <h3>

                                    <?=e($news['title'])?>

                                </h3>


                                <div class="muted news-excerpt">

                                    <?=news_excerpt(
                                        (string)$news['body'],
                                        190
                                    )?>

                                </div>


                                <a href="noticias.php">
                                    Leer noticias →
                                </a>


                            </article>


                        <?php endforeach; ?>


                    </div>


                    <div
                        class="actions"
                        style="margin-top:1.25rem"
                    >

                        <a
                            class="btn ghost"
                            href="noticias.php"
                        >
                            Ver todas las noticias
                        </a>

                    </div>


                <?php else: ?>


                    <div class="card news-empty">

                        <h3>
                            Noticias de NEXUS
                        </h3>

                        <p class="muted">
                            Todavía no hay noticias publicadas.
                            Los administradores pueden crearlas desde el panel.
                        </p>

                        <a href="noticias.php">
                            Ir a noticias →
                        </a>

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <!-- Contenido de la hermandad -->
        <section class="guild-intro" aria-labelledby="guild-content-title">
            <div class="container">
                <h2 class="section-title" id="guild-content-title">
                    Una hermandad para jugar juntos
                </h2>

                <p class="section-lead">
                    No buscamos únicamente llenar un roster. Queremos reunir jugadores
                    que disfruten del mundo, las mazmorras y, sobre todo, de compartir
                    la experiencia.
                </p>

                <div class="guild-feature-grid">
                    <article class="card roster-feature">
                        <div class="stat" aria-hidden="true">⚔️</div>
                        <h3>Roster de la hermandad</h3>
                        <p class="muted">
                            Conoce a nuestros personajes, sus clases, razas y roles.
                            Descubre quién forma parte de NEXUS y cómo encaja cada personaje
                            en nuestras aventuras.
                        </p>
                        <a class="btn primary" href="roster.php">Ver el roster →</a>
                    </article>
                </div>
            </div>
        </section>


        <!-- Recursos de la comunidad -->

        <section class="home-resources" aria-label="Recursos de NEXUS">

            <div class="container grid two">


                <div class="card">

                    <div
                        class="stat"
                        aria-hidden="true"
                    >
                        📅
                    </div>

                    <h3>
                        Calendario de eventos
                    </h3>

                    <p class="muted">
                        Consulta y añade los próximos eventos de la hermandad.
                    </p>

                    <a href="calendario.php">
                        Ver calendario →
                    </a>

                </div>


                <div class="card">

                    <div
                        class="stat"
                        aria-hidden="true"
                    >
                        🖼️
                    </div>

                    <h3>
                        Galería de NEXUS
                    </h3>

                    <p class="muted">
                        Capturas y recuerdos de nuestras aventuras en Azeroth.
                    </p>

                    <a href="galeria.php">
                        Ver galería →
                    </a>

                </div>


            </div>

        </section>


        <!-- Llamada a la acción -->

        <section
            aria-labelledby="join-title"
        >

            <div class="container cta">

                <h2 id="join-title">
                    ¿Buscas una hermandad para World of Warcraft Forever?
                </h2>

                <p class="muted">
                    Contacta con nosotros y cuéntanos qué clase, raza y profesión
                    quieres llevar.
                </p>

                <a
                    class="btn primary"
                    href="reclutamiento.php"
                >
                    Entrar en NEXUS
                </a>

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

                · <a href="/aviso-legal.php">Aviso legal</a> · <a href="/privacidad.php">Privacidad</a> · <a href="/politica-cookies.php">Cookies</a>

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
        aria-label="Volver arriba"
    >
        ↑
    </button>


    <!-- JavaScript principal -->

    
    <script src="./js/home-slider.js" defer></script>

</body>

</html>