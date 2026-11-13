<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');

$pdo = db();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);
        nexus_faction_save($pdo, (string)($_POST['faction'] ?? ''));
        // Evita que la configuración estática quede cacheada en esta petición.
        $msg = 'Facción actualizada. La web ya está configurada para ' . nexus_faction_label() . '.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$config = nexus_faction_config();
admin_header('Facción', 'faction');
?>
<div class="eyebrow">NEXUS · CONFIGURACIÓN GLOBAL</div>
<h1>Facción de la web</h1>
<p class="muted">Elige una sola facción. La selección se aplica al roster, inscripciones, profesiones, validaciones y contenidos que dependen de raza/clase.</p>
<?php if ($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?>
<?php if ($error): ?><div class="flash" style="border-color:#a33"><?=e($error)?></div><?php endif; ?>
<div class="admin-grid">
  <div class="admin-card">
    <h2>Facción activa: <?=e($config['label'])?></h2>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <label>Facción
        <select name="faction" required>
          <option value="horda" <?=$config['faction']==='horda'?'selected':''?>>⚔️ Horda</option>
          <option value="alianza" <?=$config['faction']==='alianza'?'selected':''?>>🛡️ Alianza</option>
        </select>
      </label>
      <button class="btn primary" type="submit" onclick="return confirm('¿Cambiar la facción global de la web?')">Guardar facción</button>
    </form>
  </div>
  <div class="admin-card">
    <h2>Razas disponibles</h2>
    <ul><?php foreach ($config['races'] as $race=>$classes): ?><li><strong><?=e($race)?></strong> — <?=e(implode(', ', $classes))?></li><?php endforeach; ?></ul>
  </div>
</div>
<?php admin_footer();
