<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

function nexus_base_url(): string
{
    $base = rtrim(trim((string)app_config('app_url', 'https://www.nexus-forever.es')), '/');
    return preg_match('~^https?://[^/]+$~i', $base) ? $base : 'https://www.nexus-forever.es';
}

function nexus_canonical(string $path = ''): string
{
    $path = '/' . ltrim(explode('?', explode('#', $path)[0])[0], '/');
    $path = rtrim($path, '/') ?: '/';
    if ($path === '/index.php') $path = '/';
    if (str_ends_with($path, '.php')) $path = substr($path, 0, -4);
    if (in_array($path, ['/world-of-warcraft-forever', '/horda-wow-forever', '/alianza-wow-forever'], true)) $path = '/hermandad-wow-forever';
    return nexus_base_url() . $path;
}

function nexus_seo_title(string $section = ''): string
{
    return $section === '' ? 'NEXUS | Hermandad ' . nexus_faction_label() . ' de WoW Forever' : $section . ' | NEXUS WoW Forever';
}

function nexus_seo_description(string $description): string
{
    return trim((string)preg_replace('/\s+/u', ' ', $description));
}

function nexus_jsonld(array $data): string
{
    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

/** Una sola fuente para las URLs, los metadatos y el sitemap. */
function nexus_seo_pages(): array
{
    $faction = nexus_faction_label();
    return [
        '/' => ['title' => nexus_seo_title(), 'label' => 'Inicio', 'description' => "Únete a NEXUS, hermandad española de la $faction en World of Warcraft Forever. Conoce nuestra comunidad, eventos, roster y cómo entrar en Discord."],
        '/hermandad-wow-forever' => ['title' => "Hermandad $faction de WoW Forever | NEXUS", 'label' => 'Nuestra hermandad', 'description' => "Conoce NEXUS, una hermandad española de la $faction en WoW Forever: comunidad, raids, Discord y los pasos para jugar con nosotros."],
        '/historia' => ['title' => 'Historia y comunidad de NEXUS | WoW Forever', 'label' => 'Historia', 'description' => 'Descubre la historia y la filosofía de NEXUS: veteranos de Azeroth que se reúnen para disfrutar de World of Warcraft Forever en comunidad.'],
        '/roster' => ['title' => "Roster de la $faction | NEXUS WoW Forever", 'label' => 'Roster', 'description' => "Consulta los personajes de NEXUS en la $faction: clases, razas, roles y grupos de la hermandad para organizar nuestras aventuras en WoW Forever."],
        '/calendario' => ['title' => 'Calendario de raids y eventos | NEXUS WoW Forever', 'label' => 'Calendario', 'description' => 'Consulta las fechas y horarios publicados de raids, mazmorras y actividades de NEXUS en WoW Forever. Organiza tu participación con la hermandad.'],
        '/rsp' => ['title' => 'Sistema RSP y recompensas | NEXUS WoW Forever', 'label' => 'Sistema RSP', 'description' => 'Conoce cómo funciona el sistema RSP de NEXUS: asistencia, participación, recompensas y normas para las actividades de la hermandad en WoW Forever.'],
        '/reclutamiento' => ['title' => "Reclutamiento $faction | Únete a NEXUS en WoW Forever", 'label' => 'Reclutamiento', 'description' => "¿Buscas hermandad $faction en WoW Forever? Conoce NEXUS, entra en nuestro Discord y envía tu solicitud con tu personaje, clase y disponibilidad."],
        '/logros' => ['title' => 'Logros de la hermandad | NEXUS WoW Forever', 'label' => 'Logros', 'description' => 'Descubre los logros, objetivos y momentos de progresión compartidos por los miembros de NEXUS en World of Warcraft Forever.'],
        '/galeria' => ['title' => 'Galería de Azeroth y raids | NEXUS WoW Forever', 'label' => 'Galería', 'description' => 'Explora las capturas, vídeos y recuerdos de NEXUS: aventuras por Azeroth, raids y momentos compartidos en World of Warcraft Forever.'],
        '/noticias' => ['title' => 'Noticias y avisos de la hermandad | NEXUS WoW Forever', 'label' => 'Noticias', 'description' => 'Lee las noticias, novedades y comunicaciones oficiales de NEXUS. Sigue los avisos y las actividades de nuestra hermandad de WoW Forever.'],
        '/normas' => ['title' => 'Normas de convivencia y raids | NEXUS WoW Forever', 'label' => 'Normas', 'description' => 'Consulta las normas de NEXUS: respeto, ayuda entre miembros, preparación para raids, puntualidad y uso de Discord en nuestra comunidad de WoW Forever.'],
        '/mazmorras' => ['title' => 'Mazmorras y juego en grupo | NEXUS WoW Forever', 'label' => 'Mazmorras', 'description' => 'Descubre el contenido de mazmorras de NEXUS y encuentra los recursos de la hermandad para organizar grupos y aventuras en World of Warcraft Forever.'],
        '/aviso-legal' => ['title' => 'Aviso legal | NEXUS', 'label' => 'Aviso legal', 'description' => 'Información sobre la titularidad, el uso del sitio y los derechos de los contenidos de NEXUS, comunidad independiente de World of Warcraft Forever.'],
        '/privacidad' => ['title' => 'Política de privacidad | NEXUS', 'label' => 'Privacidad', 'description' => 'Información sobre los datos de cuenta, formularios y servicios externos utilizados en la web de NEXUS y cómo ejercer tus derechos de privacidad.'],
        '/politica-cookies' => ['title' => 'Cookies y preferencias de privacidad | NEXUS', 'label' => 'Cookies', 'description' => 'Conoce el almacenamiento de preferencias de NEXUS, el consentimiento para vídeos externos y cómo modificar tu elección de privacidad.'],
        '/armeria' => ['title' => 'Armería Horda y equipo de miembros | NEXUS WoW Forever', 'label' => 'Armería', 'description' => 'Consulta el equipo, los objetos y los encantamientos de los miembros de NEXUS en WoW Forever Europa, a partir de sus perfiles públicos de Blizzard.'],
        '/perfil' => ['title' => 'Perfil y reconocimientos de miembro | NEXUS', 'label' => 'Perfil de miembro', 'description' => 'Historial de logros y hazañas otorgados por la hermandad NEXUS a sus miembros.', 'index' => false],
        '/members' => ['title' => 'Miembros y profesiones | NEXUS', 'label' => 'Miembros', 'description' => 'Información de miembros y profesiones de la comunidad NEXUS.', 'index' => false],
        '/panel' => ['title' => 'Mi panel de miembro | NEXUS', 'label' => 'Mi panel', 'description' => 'Gestiona tu cuenta y tus personajes en NEXUS.', 'index' => false],
    ];
}

function nexus_render_seo(string $path): void
{
    $pages = nexus_seo_pages();
    $page = $pages[$path] ?? ['title' => 'NEXUS', 'description' => 'Comunidad NEXUS de WoW Forever.', 'index' => false];
    $canonical = nexus_canonical($path);
    $image = nexus_base_url() . '/img/galeria/Azeroth01.jpg';
    $robots = ($page['index'] ?? true) ? 'index, follow, max-image-preview:large' : 'noindex, follow';
    echo '<title>' . e($page['title']) . "</title>\n";
    $meta = ['description' => $page['description'], 'robots' => $robots, 'twitter:card' => 'summary_large_image', 'twitter:title' => $page['title'], 'twitter:description' => $page['description'], 'twitter:image' => $image, 'twitter:image:alt' => 'Aventuras de NEXUS en Azeroth'];
    foreach ($meta as $name => $content) echo '<meta name="' . e($name) . '" content="' . e($content) . "\">\n";
    $og = ['type' => 'website', 'locale' => 'es_ES', 'site_name' => 'NEXUS — Veteranos de Azeroth', 'title' => $page['title'], 'description' => $page['description'], 'url' => $canonical, 'image' => $image, 'image:width' => '1920', 'image:height' => '1080', 'image:alt' => 'Aventuras de NEXUS en Azeroth'];
    foreach ($og as $name => $content) echo '<meta property="og:' . e($name) . '" content="' . e($content) . "\">\n";
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";
    $verification = trim((string)app_config('google_site_verification', ''));
    if (preg_match('/^[A-Za-z0-9_-]+$/', $verification)) echo '<meta name="google-site-verification" content="' . e($verification) . "\">\n";
    $site = nexus_canonical('/');
    $graph = [
        ['@type' => 'Organization', '@id' => $site . '#organization', 'name' => 'NEXUS — Veteranos de Azeroth', 'url' => $site, 'description' => 'Hermandad española de la ' . nexus_faction_label() . ' en World of Warcraft Forever.'],
        ['@type' => 'WebSite', '@id' => $site . '#website', 'url' => $site, 'name' => 'NEXUS — Veteranos de Azeroth', 'inLanguage' => 'es', 'publisher' => ['@id' => $site . '#organization']],
        ['@type' => 'WebPage', '@id' => $canonical . '#webpage', 'url' => $canonical, 'name' => $page['title'], 'description' => $page['description'], 'inLanguage' => 'es', 'isPartOf' => ['@id' => $site . '#website']],
    ];
    if ($path !== '/') $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $site],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $page['label'], 'item' => $canonical],
    ]];
    echo '<script type="application/ld+json">' . nexus_jsonld(['@context' => 'https://schema.org', '@graph' => $graph]) . "</script>\n";
}
