<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/news-html.php';

$rows = [];

try {
    if (nexus_is_configured()) {
        $rows = db()->query("
            SELECT id, title, body, published_at
            FROM news
            WHERE published = 1
            ORDER BY published_at DESC, id DESC
        ")->fetchAll();
    }
} catch (Throwable $e) {
    $rows = [];
}


/*
 * Limpia el HTML utilizado en las noticias.
 *
 * Permitimos solamente:
 * a, strong, em, b, i, br, p, ul, ol y li.
 */
function sanitize_news_html(string $html): string
{
    return nexus_news_html($html);
}
?>
<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>
    Noticias | NEXUS — Veteranos de Azeroth
</title>

<meta
    name="description"
    content="Noticias y comunicados de la hermandad NEXUS."
>

<link
    rel="canonical"
    href="https://www.nexus-forever.es/noticias.php"
>

<link
    rel="stylesheet"
    href="./css/global.css"
>

<link
    rel="icon"
    type="image/x-icon"
    href="./favicon.ico"
>


<style>

/* ================================
   CONTENIDO DE LAS NOTICIAS
   ================================ */

.news-content {
    white-space: normal;
    line-height: 1.7;
}


/* ================================
   TÍTULOS DE LAS NOTICIAS
   ================================ */

.news-title {
    font-size: 1.35rem;
    line-height: 1.3;
    margin-top: .75rem;
    margin-bottom: 1rem;
}


/* En móviles */

@media (max-width: 700px) {

    .news-title {
        font-size: 1.15rem;
        line-height: 1.3;
    }

}


/* ================================
   PÁRRAFOS
   ================================ */

.news-content p {
    margin: 0 0 1rem;
}

.news-content p:last-child {
    margin-bottom: 0;
}


/* ================================
   HIPERVÍNCULOS
   ================================ */

.news-content a {
    color: #d4af37;
    text-decoration: underline;
    font-weight: 600;

    transition:
        color .2s ease,
        text-shadow .2s ease,
        opacity .2s ease;
}


/* Efecto al pasar el ratón */

.news-content a:hover {
    color: #f0cf5c;
    opacity: 1;

    text-shadow:
        0 0 8px rgba(212, 175, 55, .45);
}


/* ================================
   NEGRITA
   ================================ */

.news-content strong {
    font-weight: 700;
}


/* ================================
   CURSIVA
   ================================ */

.news-content em {
    font-style: italic;
}


/* ================================
   LISTAS
   ================================ */

.news-content ul,
.news-content ol {
    margin: .75rem 0 1rem 1.5rem;
}

.news-content li {
    margin-bottom: .35rem;
}

</style>

</head>


<body>


<?php include __DIR__ . '/includes/public-nav.php'; ?>


<!-- ================================
     CABECERA
     ================================ -->

<header class="page-hero">

    <div class="container">

        <div class="eyebrow">
            NEXUS · COMUNIDAD
        </div>

        <h1>
            Noticias
        </h1>

        <p>
            Comunicados, novedades y noticias de la hermandad.
        </p>

    </div>

</header>


<main>


<!-- ================================
     LISTADO DE NOTICIAS
     ================================ -->

<section>

    <div
        class="container"
        style="max-width:900px"
    >

        <div class="grid">


        <?php if (!$rows): ?>


            <div class="card">

                <h2>
                    Sin noticias todavía
                </h2>

                <p class="muted">
                    El panel de administración permitirá publicar
                    las novedades de NEXUS.
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($rows as $n): ?>


                <article class="card">


                    <!-- Fecha -->

                    <div class="eyebrow">

                        <?=e(
                            date(
                                'd/m/Y',
                                strtotime($n['published_at'])
                            )
                        )?>

                    </div>


                    <!-- Título -->

                    <h2 class="news-title">

                        <?=e($n['title'])?>

                    </h2>


                    <!-- Contenido -->

                    <div class="muted news-content">

                        <?=sanitize_news_html(
                            (string)$n['body']
                        )?>

                    </div>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


        </div>

    </div>

</section>


</main>


<!-- ================================
     PIE
     ================================ -->

<footer class="site-footer">

    <div class="container">

        <strong>
            NEXUS — VETERANOS DE AZEROTH
        </strong>

    </div>

</footer>





</body>

</html>