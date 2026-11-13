<?php
require_once __DIR__ . '/../config/bootstrap.php';
start_secure_session();
$isMemberLogged = !empty($_SESSION['member_discord_id']);
$returnTo = (string)($_SERVER['REQUEST_URI'] ?? '/');
if ($returnTo === '' || $returnTo[0] !== '/' || str_starts_with($returnTo, '//')) $returnTo = '/';
$loginHref = '/auth/discord.php?return_to=' . rawurlencode($returnTo);
?>
<nav class="site-nav" aria-label="Navegación principal"><div class="nav-inner"><a class="brand" href="/">NEXUS</a><span class="nav-faction"><?=e(nexus_faction_emoji().' '.nexus_faction_label())?></span><button class="menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="main-navigation">☰</button><div class="nav-links" id="main-navigation"><a href="/">Inicio</a><a href="/historia">Historia</a><a href="/logros">Logros</a><a href="/calendario">Calendario</a><a href="/roster">Roster</a><a href="/armeria">Armería</a><a href="/rsp">RSP</a><a href="/members">Miembros</a><?php if($isMemberLogged): ?><a href="/panel">Mi panel</a><?php else: ?><a href="<?=e($loginHref)?>" class="nav-member-login">🔐 Entrar</a><?php endif; ?><a href="/galeria">Galería</a><a href="/noticias">Noticias</a><a href="/reclutamiento" class="nav-cta">Unirse</a></div></div></nav>

<!-- Consentimiento de privacidad/cookies NEXUS -->
<link rel="stylesheet" href="/css/privacy.css">
<div id="nexus-cookie-banner" role="dialog" aria-live="polite" aria-labelledby="nexus-cookie-title" hidden>
  <h2 id="nexus-cookie-title">Privacidad y cookies</h2>
  <p>Utilizamos almacenamiento técnico necesario para el funcionamiento de la web y guardamos tu elección. Los vídeos de YouTube solo se cargarán si permites contenido externo; al reproducirlos, YouTube puede recibir datos como tu dirección IP. Puedes aceptar, rechazar o configurar esta opción. <a href="/politica-cookies.php">Política de cookies</a> · <a href="/privacidad.php">Privacidad</a>.</p>
  <div class="nexus-cookie-actions">
    <button type="button" class="primary" id="nexus-cookie-accept">Aceptar contenido externo</button>
    <button type="button" id="nexus-cookie-reject">Rechazar</button>
    <button type="button" id="nexus-cookie-configure">Configurar</button>
  </div>
  <div id="nexus-cookie-settings" hidden>
    <label><input type="checkbox" checked disabled> Almacenamiento técnico y de preferencias (necesario)</label>
    <label><input type="checkbox" id="nexus-cookie-external"> Contenido externo de YouTube</label>
    <div class="nexus-cookie-actions"><button type="button" class="primary" id="nexus-cookie-save">Guardar selección</button></div>
  </div>
</div>
<script src="/js/privacy.js" defer></script>
<script src="/js/script.js" defer></script>
<script src="/js/music.js" defer></script>

<?php include __DIR__ . '/chat-widget.php'; ?>
