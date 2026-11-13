<?php
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();

 // Necesario para conservar el token entre la carga del formulario y su envío.
require_once __DIR__ . '/includes/seo.php';

/* La tabla se prepara de forma compatible con instalaciones ya existentes. */
function nexus_prepare_recruitment_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS recruitment_applications (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT, player_name VARCHAR(60) NOT NULL,
      character_name VARCHAR(24) NOT NULL, race VARCHAR(80) NOT NULL, class_name VARCHAR(80) NOT NULL,
      role_name VARCHAR(40) NOT NULL, level SMALLINT NULL, profession_1 VARCHAR(40) NULL,
      profession_2 VARCHAR(40) NULL, specialization VARCHAR(80) NULL, experience VARCHAR(100) NULL,
      preferred_content VARCHAR(100) NULL, schedule VARCHAR(120) NULL, discord_name VARCHAR(80) NOT NULL,
      presentation TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      viewed_at DATETIME NULL, updated_at DATETIME NULL, updated_by VARCHAR(120) NULL,
      PRIMARY KEY(id), KEY idx_recruitment_status(status), KEY idx_recruitment_created(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $cols = $pdo->query("SHOW COLUMNS FROM recruitment_applications")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('viewed_at', $cols, true)) $pdo->exec("ALTER TABLE recruitment_applications ADD viewed_at DATETIME NULL");
    if (!in_array('updated_at', $cols, true)) $pdo->exec("ALTER TABLE recruitment_applications ADD updated_at DATETIME NULL");
    if (!in_array('updated_by', $cols, true)) $pdo->exec("ALTER TABLE recruitment_applications ADD updated_by VARCHAR(120) NULL");
    /* VARCHAR evita que una tabla antigua rechace los estados nuevos. */
    $pdo->exec("ALTER TABLE recruitment_applications MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS recruitment_application_history (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_id INT UNSIGNED NOT NULL,
      old_status VARCHAR(20) NULL, new_status VARCHAR(20) NOT NULL, changed_by VARCHAR(120) NOT NULL,
      changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, note VARCHAR(255) NULL,
      PRIMARY KEY(id), KEY idx_recruitment_history_app(application_id,changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
try { nexus_prepare_recruitment_table(db()); } catch (Throwable $e) { error_log('NEXUS recruitment schema: '.$e->getMessage()); $recruitmentSetupError = 'No se pudo preparar el almacenamiento de solicitudes. Contacta con el administrador de la web.'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_jugador'])) {
    try {
        if (!empty($recruitmentSetupError)) throw new RuntimeException($recruitmentSetupError);
        if (!isset($_POST['recruitment_token']) || !hash_equals((string)($_SESSION['recruitment_token'] ?? ''), (string)$_POST['recruitment_token'])) throw new RuntimeException('El formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        if (empty($_POST['acepto_contacto'])) throw new RuntimeException('Debes aceptar el uso de los datos para tramitar la solicitud.');
        $required = ['nombre_jugador','personaje','raza','clase','rol_principal','discord'];
        foreach ($required as $field) if (trim((string)($_POST[$field] ?? '')) === '') throw new RuntimeException('Completa todos los campos obligatorios.');
        $stmt = db()->prepare("INSERT INTO recruitment_applications (player_name,character_name,race,class_name,role_name,level,profession_1,profession_2,specialization,experience,preferred_content,schedule,discord_name,presentation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $level = (int)($_POST['nivel'] ?? 0); if ($level < 1 || $level > 100) $level = null;
        $values = [trim($_POST['nombre_jugador']),trim($_POST['personaje']),trim($_POST['raza']),trim($_POST['clase']),trim($_POST['rol_principal']),$level,trim($_POST['profesion_1']??''),trim($_POST['profesion_2']??''),trim($_POST['especializacion']??''),trim($_POST['experiencia']??''),trim($_POST['contenido_preferido']??''),trim($_POST['horarios']??''),trim($_POST['discord']),trim($_POST['presentacion']??'')];
        $stmt->execute(array_map(static fn($v) => $v === '' ? null : $v, $values));
        $mailBody = "Nueva solicitud de reclutamiento NEXUS\n\n" . implode("\n", ['Jugador: '.$values[0], 'Personaje: '.$values[1], 'Raza/clase: '.$values[2].' / '.$values[3], 'Rol: '.$values[4], 'Discord: '.$values[12], 'Presentación: '.$values[13]]);
        @mail('roster@nexus-forever.es', 'Nueva solicitud de reclutamiento — NEXUS', $mailBody, "Content-Type: text/plain; charset=UTF-8\r\nFrom: web@nexus-forever.es");
        header('Location: /reclutamiento?enviado=1'); exit;
    } catch (Throwable $e) { $recruitmentError = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reclutamiento WoW Forever | Únete a NEXUS</title>
<meta name="description" content="Solicita entrar en NEXUS — Veteranos de Azeroth. Indica tu personaje, raza, clase, rol, profesiones, experiencia y preferencias de juego.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://www.nexus-forever.es/reclutamiento.php">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_ES">
<meta property="og:title" content="Reclutamiento WoW Forever | Únete a NEXUS">
<meta property="og:description" content="Formulario de reclutamiento de NEXUS para jugadores de World of Warcraft Forever.">
<meta property="og:url" content="https://www.nexus-forever.es/reclutamiento.php">
<meta property="og:site_name" content="NEXUS — Veteranos de Azeroth">
<meta property="og:image" content="https://www.nexus-forever.es/img/galeria/Azeroth01.jpg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Reclutamiento WoW Forever | Únete a NEXUS">
<meta name="twitter:description" content="Formulario para unirte a NEXUS — Veteranos de Azeroth.">
<meta name="twitter:image" content="https://www.nexus-forever.es/img/galeria/Azeroth01.jpg">
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/reclutamiento.css">
<link rel="icon" type="image/x-icon" href="./favicon.ico">
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebPage",
  "name":"Reclutamiento NEXUS",
  "url":"https://www.nexus-forever.es/reclutamiento.php",
  "description":"Formulario de reclutamiento para NEXUS — Veteranos de Azeroth."
}
</script>
</head>
<body>
<?php include __DIR__ . '/includes/public-nav.php'; ?>

<header class="page-hero">
  <div class="container">
    <div class="eyebrow">NEXUS · WORLD OF WARCRAFT FOREVER</div>
    <h1>Reclutamiento</h1>
    <p>Cuéntanos quién eres en Azeroth y qué buscas en una hermandad. Queremos conocerte antes de que entres en NEXUS.</p>
  </div>
</header>

<main>
<section>
<div class="container">
  <div class="grid two">
    <div class="card">
      <h2>¿Qué buscamos?</h2>
      <ul class="list">
        <li>Jugadores activos y respetuosos.</li>
        <li>Ganas de participar en la comunidad.</li>
        <li>Interés por PvE, PvP, eventos y contenido social.</li>
        <li>Compromiso razonable con los eventos a los que te apuntes.</li>
        <li>Veteranos y jugadores que quieran aprender.</li>
      </ul>
      <p class="muted">
        No necesitas tener un personaje perfecto ni jugar todos los días.
        Buscamos personas que quieran formar parte de una comunidad y disfrutar de Azeroth.
      </p>
    </div>

    <div class="card">
      <h2>💬 También puedes contactarnos</h2>
      <p>Si prefieres hablar directamente con nosotros, puedes entrar en Discord y presentarte a la comunidad.</p>
      <p class="stat">BattleNet zEoX80#2420</p>
      <a class="btn primary" href="https://discord.gg/Yq9cQ7E9z" target="_blank" rel="noopener noreferrer">Entrar en Discord</a>
    </div>
  </div>

  <div class="card recruitment-form-card">
    <h2>📝 Solicitud para entrar en NEXUS</h2>
    <p class="section-lead">Rellena los datos de tu personaje. La solicitud quedará guardada en la web para que administradores y editores puedan revisarla.</p>
    <?php if (!empty($recruitmentSetupError)): ?><div class="flash" style="border-color:#a33"><?=e($recruitmentSetupError)?></div><?php endif; ?>
    <?php if (!empty($recruitmentError)): ?><div class="flash" style="border-color:#a33"><?=e($recruitmentError)?></div><?php endif; ?>
    <?php if (isset($_GET['enviado'])): ?><div class="flash">¡Solicitud recibida! El equipo de NEXUS la revisará.</div><?php endif; ?>

    <!--
      CONFIGURACIÓN:
      Sustituye TU_EMAIL_AQUI por el correo que quieras recibir las solicitudes.
      FormSubmit funciona con una web estática y no requiere servidor propio.
    -->
    <form action="/reclutamiento" method="POST">
      <?php if (empty($_SESSION['recruitment_token'])) $_SESSION['recruitment_token'] = bin2hex(random_bytes(32)); ?>
      <input type="hidden" name="recruitment_token" value="<?=e($_SESSION['recruitment_token'])?>">

      <div class="form-grid">
        <div class="form-field">
          <label for="nombre">Nombre del jugador *</label>
          <input id="nombre" name="nombre_jugador" type="text" required maxlength="60" autocomplete="name" placeholder="Tu nombre o nick">
        </div>

        <div class="form-field">
          <label for="personaje">Nombre del personaje *</label>
          <input id="personaje" name="personaje" type="text" required maxlength="24" placeholder="Ej.: Arthas">
        </div>

        <div class="form-field">
          <label for="raza">Raza *</label>
          <select id="raza" name="raza" required>
            <option value="">Selecciona una raza</option>
            <?php foreach(nexus_wow_races() as $race=>$raceClasses): ?><option><?=e($race)?></option><?php endforeach; ?>
          </select>
        </div>

        <div class="form-field">
          <label for="clase">Clase *</label>
          <select id="clase" name="clase" required>
            <option value="">Selecciona una clase</option>
            <?php foreach(nexus_wow_classes() as $class): ?><option><?=e($class)?></option><?php endforeach; ?>
          </select>
        </div>

        <div class="form-field">
          <label for="rol">Rol principal *</label>
          <select id="rol" name="rol_principal" required>
            <option value="">Selecciona un rol</option>
            <option>Tanque</option><option>Sanador</option><option>DPS</option><option>Flexible</option>
          </select>
        </div>

        <div class="form-field">
          <label for="nivel">Nivel actual</label>
          <input id="nivel" name="nivel" type="number" min="1" max="100" placeholder="Ej.: 30">
        </div>

        <div class="form-field">
          <label for="prof1">Profesión 1</label>
          <input id="prof1" name="profesion_1" type="text" maxlength="40" placeholder="Ej.: Alquimia">
        </div>

        <div class="form-field">
          <label for="prof2">Profesión 2</label>
          <input id="prof2" name="profesion_2" type="text" maxlength="40" placeholder="Ej.: Herboristería">
        </div>

        <div class="form-field">
          <label for="especializacion">Especialización / build</label>
          <input id="especializacion" name="especializacion" type="text" maxlength="80" placeholder="Ej.: Protección, Fuego, Restauración...">
        </div>

        <div class="form-field">
          <label for="experiencia">Experiencia</label>
          <select id="experiencia" name="experiencia">
            <option value="">Selecciona una opción</option>
            <option>Nuevo en WoW</option>
            <option>Jugador ocasional</option>
            <option>Veterano de WoW</option>
            <option>Veterano de contenido PvE</option>
            <option>Veterano de contenido PvE</option>
            <option>Experiencia en PvE y contenido de grupo</option>
          </select>
        </div>

        <div class="form-field">
          <label for="interes">Contenido que más te interesa</label>
          <select id="interes" name="contenido_preferido">
            <option value="">Selecciona una opción</option>
            <option>PvE — Raids</option>
            <option>Contenido PVP</option>
            <option>PvE — Mazmorras</option>
            <option>Contenido de grupo</option>
            <option>Mazmorras y eventos</option>
            <option>PvE y contenido social</option>
            <option>Social / Exploración / Logros</option>
          </select>
        </div>

        <div class="form-field">
          <label for="horarios">Horarios habituales</label>
          <input id="horarios" name="horarios" type="text" maxlength="120" placeholder="Ej.: tardes entre semana y fines de semana">
        </div>

        <div class="form-field">
          <label for="discord">Usuario de Discord *</label>
          <input id="discord" name="discord" type="text" required maxlength="80" placeholder="Ej.: usuario">
        </div>

        <div class="form-field full">
          <label for="presentacion">Cuéntanos algo sobre ti</label>
          <textarea id="presentacion" name="presentacion" maxlength="1000" placeholder="¿Qué buscas en NEXUS? ¿Qué contenido te gustaría hacer?"></textarea>
        </div>

        <div class="form-field full">
          <label class="check-row">
            <input type="checkbox" name="acepto_contacto" value="Sí" required>
            <span>Acepto que NEXUS utilice estos datos únicamente para responder a esta solicitud de reclutamiento. *</span>
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn primary" type="submit">Enviar solicitud</button>
        <button class="btn ghost" type="reset">Limpiar formulario</button>
      </div>
    </form>
  </div>
</div>
</section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · Comunidad española</p></div>
    <div><a href="/reclutamiento">Reclutamiento</a> · <a href="normas.php">Normas</a></div>
  </div>
  <div class="copyright">© 2026 NEXUS — Veteranos de Azeroth</div>
</footer>
<button class="back-top" type="button" aria-label="Volver arriba">↑</button>

<script>
  const params = new URLSearchParams(location.search);
  if (params.get('enviado') === '1') {
    const box = document.querySelector('.recruitment-form-card');
    if (box) {
      const msg = document.createElement('p');
      msg.className = 'form-success';
      msg.setAttribute('role','status');
      msg.textContent = '✓ Solicitud enviada. Gracias por querer formar parte de NEXUS.';
      box.insertBefore(msg, box.querySelector('form'));
    }
  }
</script>
</body>
</html>
