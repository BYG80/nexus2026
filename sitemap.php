<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
$base=rtrim((string)app_config('app_url','https://www.nexus-forever.es'),'/');
$urls=['/','/hermandad-wow-forever','/world-of-warcraft-forever'];
$urls[] = nexus_faction()==='alianza' ? '/alianza-wow-forever' : '/horda-wow-forever';
$urls=array_merge($urls,['/roster','/members','/calendario','/rsp','/reclutamiento','/historia','/logros','/galeria','/noticias','/normas','/mazmorras','/aviso-legal','/privacidad','/politica-cookies']);
$xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
foreach($urls as $path){$xml.="\n  <url><loc>".htmlspecialchars($base.$path,ENT_XML1,'UTF-8')."</loc><lastmod>".date('Y-m-d')."</lastmod></url>";}
$xml.="\n</urlset>\n";
echo $xml;
