<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

$__nexusConfigured = is_file(__DIR__.'/config/config.php');
if ($__nexusConfigured) require_once __DIR__.'/config/bootstrap.php';
$mediaImages = [];
$videos = [];
if ($__nexusConfigured && function_exists('db')) {
    try {
        $mediaImages = db()->query("SELECT title,description,category,url FROM media WHERE type='image' ORDER BY created_at DESC,id DESC")->fetchAll();
        $videos = db()->query("SELECT * FROM media WHERE type='youtube' ORDER BY created_at DESC,id DESC")->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="es">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/galeria'); ?>
<link
        rel="icon"
        href="./favicon.ico"
    >
<link
        rel="stylesheet"
        href="./css/global.css"
    >
<link
        rel="stylesheet"
        href="./css/galeria.css?v=10"
    >
</head>


<body>


<!-- =========================================================
     HEADER
     ========================================================= -->

<?php include __DIR__ . '/includes/public-nav.php'; ?>



<!-- =========================================================
     HERO
     ========================================================= -->

<section class="page-hero">

    <div class="container">

        <div class="eyebrow">
            NEXUS · WORLD OF WARCRAFT FOREVER
        </div>

        <h1>
            Galería de NEXUS — Veteranos de Azeroth
        </h1>

        <p>
            Capturas, raids, aventuras y momentos
            compartidos por la comunidad NEXUS.
        </p>

    </div>

</section>



<!-- =========================================================
     CONTENIDO
     ========================================================= -->

<main>

    <section class="section">

        <div class="container">


            <!-- FILTROS -->

            <div
                id="galleryFilters"
                class="gallery-filter"
            >
            </div>


            <!-- SLIDER -->

            <div
                id="gallerySlider"
                class="gallery-slider"
            >

                <button
                    id="prevSlide"
                    class="slider-arrow prev"
                    type="button"
                    aria-label="Imagen anterior"
                >
                    ‹
                </button>


                <button
                    id="nextSlide"
                    class="slider-arrow next"
                    type="button"
                    aria-label="Imagen siguiente"
                >
                    ›
                </button>

            </div>


            <!-- PUNTOS -->

            <div
                id="galleryDots"
                class="gallery-dots"
            >
            </div>


            <!-- MINIATURAS -->

            <div
                id="galleryThumbs"
                class="gallery-grid"
            >
            </div>


            <p class="gallery-help">
                🖱️ Haz clic en cualquier imagen para ampliarla.
                También puedes utilizar las flechas ← → para cambiar
                de imagen. Pulsa ESC o ✕ para cerrar.
            </p>

        </div>

    </section>

</main>



<!-- =========================================================
     LIGHTBOX
     ========================================================= -->

<div
    id="galleryLightbox"
    class="gallery-lightbox"
    aria-hidden="true"
>

    <button
        id="lightboxClose"
        class="gallery-lightbox-close"
        type="button"
        aria-label="Cerrar imagen"
    >
        ✕
    </button>


    <button
        id="lightboxPrev"
        class="gallery-lightbox-prev"
        type="button"
        aria-label="Imagen anterior"
    >
        ‹
    </button>


    <div class="gallery-lightbox-inner">

        <img
            id="lightboxImage"
            src=""
            alt=""
        >

        <div
            id="lightboxCaption"
            class="gallery-lightbox-caption"
        >
        </div>

    </div>


    <button
        id="lightboxNext"
        class="gallery-lightbox-next"
        type="button"
        aria-label="Imagen siguiente"
    >
        ›
    </button>

</div>



<!-- =========================================================
     VÍDEOS DE YOUTUBE
     ========================================================= -->
<?php if ($videos): ?>
<section class="section">
  <div class="container">
    <div class="eyebrow">NEXUS · VÍDEOS</div>
    <h2>Vídeos de la hermandad</h2>
    <div class="nexus-video-grid">
      <?php foreach ($videos as $video): ?>
        <?php $vid = ''; if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $video['url'], $mm)) $vid=$mm[1]; ?>
        <?php if ($vid): ?><article class="nexus-video-card"><div class="nexus-video-frame"><iframe src="https://www.youtube-nocookie.com/embed/<?=e($vid)?>" title="<?=e($video['title'])?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div><h3><?=e($video['title'])?></h3><?php if($video['description']): ?><p><?=nl2br(e($video['description']))?></p><?php endif; ?></article><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer class="site-footer">

    <div class="container">

        <strong>
            NEXUS — VETERANOS DE AZEROTH
        </strong>

        <span>
            World of Warcraft Forever · Comunidad española
        </span>

        <div>

            <a href="reclutamiento.php">
                Reclutamiento
            </a>

            ·

            <a href="normas.php">
                Normas
            </a>

        </div>

        <small>
            © 2026 NEXUS — Veteranos de Azeroth
        </small>

    </div>

</footer>



<!-- =========================================================
     BOTÓN ARRIBA
     ========================================================= -->

<button
    id="backToTop"
    class="back-to-top"
    type="button"
    aria-label="Volver arriba"
>
    ↑
</button>



<!-- =========================================================
     JAVASCRIPT GLOBAL
     ========================================================= -->




<!-- =========================================================
     JAVASCRIPT GALERÍA
     ========================================================= -->

<script src="./js/galeria.js?v=13"></script>


</body>

</html>
