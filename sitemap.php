<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo.php';
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (nexus_seo_pages() as $path => $page) {
    if (!($page['index'] ?? true)) continue;
    $file = __DIR__ . ($path === '/' ? '/index.php' : $path . '.php');
    if (!is_file($file)) continue;
    echo '  <url><loc>' . htmlspecialchars(nexus_canonical($path), ENT_XML1, 'UTF-8') . '</loc>';
    echo '<lastmod>' . gmdate('Y-m-d', filemtime($file)) . "</lastmod></url>\n";
}
echo "</urlset>\n";
