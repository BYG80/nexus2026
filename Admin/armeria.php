<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin_role('admin');
require_once __DIR__ . '/../includes/armory.php';
$pdo=db(); $message=''; $error='';
try { $pdo->exec("CREATE TABLE IF NOT EXISTS armory_characters (profile_id INT UNSIGNED NOT NULL PRIMARY KEY,slug VARCHAR(120) NOT NULL,name VARCHAR(120) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
catch(Throwable $exception) { $error='No se puede preparar la tabla de personajes. Revisa los permisos de la base de datos.'; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        check_csrf($_POST['csrf']??null);
        $name=trim((string)($_POST['name']??''));
        if(mb_strlen($name)>120) throw new InvalidArgumentException('El nombre completo es demasiado largo.');
        $removing=($_POST['action']??'')==='remove';
        $slug=$removing?'oculto-'.(int)($_POST['profile_id']??0):nexus_armory_slug($name);
        $profileId=(int)($_POST['profile_id']??0);
        $check=$pdo->prepare('SELECT mp.id FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id AND m.is_member=1 WHERE mp.id=? AND mp.faction=?');
        $check->execute([$profileId,nexus_faction()]);
        if(!$check->fetchColumn())throw new InvalidArgumentException('Selecciona un personaje de un miembro verificado de la web.');
        $active=($_POST['action']??'')==='remove'?0:1;
        $pdo->prepare('INSERT INTO armory_characters (profile_id,slug,name,active) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE slug=VALUES(slug),name=VALUES(name),active=VALUES(active)')->execute([$profileId,$slug,$name,$active]);
        $message=$active?'Personaje vinculado. Su equipo se consultará en el perfil público de Blizzard.':'Personaje ocultado de la armería.';
    }catch(Throwable $exception){$error=$exception instanceof InvalidArgumentException?$exception->getMessage():'No se ha podido guardar el personaje.';}
}
$profiles=nexus_armory_members();
admin_header('Armería','armory');
?>
<h1>Armería de la hermandad</h1>
<p>Región Europa (EU), WoW Forever. Los nombres deben incluir apellido. El perfil oficial debe pertenecer a <strong><?=e(app_config('armory_guild_slug','nexusforever'))?></strong> y a la Horda.</p>
<?php if($message): ?><div class="flash"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="error-box"><?=e($error)?></div><?php endif; ?>
<div class="admin-card"><h2>Vincular personaje</h2><form class="admin-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><label>Personaje registrado en la web<select name="profile_id" required><?php foreach($profiles as $profile): ?><option value="<?=(int)$profile['profile_id']?>"><?=e($profile['name'])?></option><?php endforeach; ?></select></label><label>Nombre y apellido<input name="name" maxlength="120" placeholder="Ej.: Melkir Vossgrave" required></label><button class="btn primary" type="submit">Añadir a la armería</button></form><p class="small">También se incluyen los personajes de miembros verificados que tengan nombre completo en Mi panel. No se necesita una contraseña de Battle.net.</p></div>
<div class="admin-card" style="margin-top:20px"><h2>Personajes vinculados</h2><?php foreach($profiles as $profile): $slug=$profile['slug'];$name=$profile['display_name']; ?><div class="toolbar"><span><?=e($name)?><?php if($slug): ?> · <a href="/armeria?personaje=<?=rawurlencode($slug)?>" target="_blank" rel="noopener">Ver equipo ↗</a><?php endif; ?></span><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="name" value="<?=e($name)?>"><input type="hidden" name="profile_id" value="<?=(int)$profile['profile_id']?>"><button class="btn ghost" type="submit">Ocultar</button></form></div><?php endforeach; ?></div>
<?php admin_footer(); ?>
