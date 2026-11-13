<?php
declare(strict_types=1);

function nexus_base_url(): string
{
    return rtrim((string)app_config('app_url', 'https://www.nexus-forever.es'), '/');
}

function nexus_canonical(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return nexus_base_url() . ($path === '/' ? '/' : $path);
}

function nexus_seo_title(string $section = ''): string
{
    $faction = nexus_faction_label();
    $base = 'NEXUS | Hermandad ' . $faction . ' WoW Forever | Guild World of Warcraft';
    return $section !== '' ? $section . ' | NEXUS — ' . $faction . ' WoW Forever' : $base;
}

function nexus_seo_description(string $description): string
{
    return trim(preg_replace('/\s+/', ' ', $description));
}

function nexus_jsonld(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
