<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();
header('X-Robots-Tag: noindex, follow');

require_once __DIR__.'/config/bootstrap.php';


try {
    $member = require_discord_member();
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(503);
    exit('El panel no está disponible porque la base de datos de NEXUS no responde.');
}

$faction = nexus_faction();
$races = nexus_wow_races();
$discordId = $member['discord_id'];
$msg = '';
$error = '';
$character = null;
$characters = [];
$professions = [];
$orders = [];
$receivedOrders = [];
$guildMembers = [];

$primaryOptions = ['Alquimia','Herrería','Encantamiento','Ingeniería','Herboristería','Inscripción','Joyería','Peletería','Minería','Desuello','Sastrería'];
$secondaryOptions = ['Cocina','Pesca','Primeros Auxilios'];

try {
    $st = $pdo->prepare('SELECT * FROM member_profiles WHERE discord_id=? AND faction=? ORDER BY id ASC');
    $st->execute([$discordId,$faction]);
    $characters = $st->fetchAll(PDO::FETCH_ASSOC);
    $characterId = (int)($_GET['character'] ?? 0);
    if ($characterId > 0) {
        foreach ($characters as $candidate) {
            if ((int)$candidate['id'] === $characterId) { $character = $candidate; break; }
        }
    }
    if (!$character && !isset($_GET['new'])) $character = null;

    $st = $pdo->prepare("SELECT * FROM professions WHERE member_discord_id=? AND faction=? AND status='active' ORDER BY profession_type,profession_name");
    $st->execute([$discordId,$faction]);
    $professions = $st->fetchAll(PDO::FETCH_ASSOC);

    /* Solo miembros verificados pueden ser destinatarios de pedidos.
     * El Roster no interviene en este sistema. */
    $st = $pdo->prepare("SELECT m.discord_id,m.discord_username,COALESCE(mp.name,'') AS character_name FROM members m LEFT JOIN member_profiles mp ON mp.discord_id=m.discord_id AND mp.faction=? WHERE m.discord_id<>? AND m.is_member=1 ORDER BY m.discord_username ASC");
    $st->execute([$faction,$discordId]);
    $guildMembers = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT * FROM member_orders WHERE member_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
    $st->execute([$discordId,$faction]);
    $orders = $st->fetchAll(PDO::FETCH_ASSOC);

    // Pedidos que otros miembros han dirigido a este usuario.
    $st = $pdo->prepare("SELECT * FROM member_orders WHERE recipient_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
    $st->execute([$discordId,$faction]);
    $receivedOrders = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf($_POST['csrf'] ?? null);

        /* Crear pedido y publicarlo automáticamente en Discord. */
        if (($_POST['action'] ?? '') === 'create_order') {
            $title=trim((string)($_POST['order_title']??''));
            $recipientId=trim((string)($_POST['order_recipient']??''));
            $itemName=trim((string)($_POST['order_item']??''));
            $category=trim((string)($_POST['order_category']??'General'));
            $quantity=trim((string)($_POST['order_quantity']??''));
            $details=trim((string)($_POST['order_details']??''));
            $priority=trim((string)($_POST['order_priority']??'normal'));
            if($title==='' || $recipientId==='' || $itemName==='' || $details==='') throw new RuntimeException('Debes indicar a quién se lo solicitas, el item y los detalles del pedido.');
            if(!in_array($priority,['normal','alta','urgente'],true)) $priority='normal';
            $recipientStmt=$pdo->prepare("SELECT m.discord_id,m.discord_username,COALESCE(mp.name,'') AS character_name FROM members m LEFT JOIN member_profiles mp ON mp.discord_id=m.discord_id AND mp.faction=? WHERE m.discord_id=? AND m.is_member=1 LIMIT 1");
            $recipientStmt->execute([$faction,$recipientId]);
            $recipient=$recipientStmt->fetch(PDO::FETCH_ASSOC);
            if(!$recipient) throw new RuntimeException('El miembro destinatario no es válido o no pertenece a la hermandad.');
            $characterName=trim((string)($character['name']??''));
            $ins=$pdo->prepare("INSERT INTO member_orders (member_discord_id,recipient_discord_id,member_username,recipient_username,faction,character_name,recipient_character_name,title,item_name,category,quantity,details,priority) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$discordId,$recipientId,$member['username'],$recipient['discord_username'],$faction,$characterName,$recipient['character_name'],$title,$itemName,$category,$quantity,$details,$priority]);
            $orderId=(int)$pdo->lastInsertId();
            try {
                publish_member_order_to_discord($pdo,$orderId);
                $msg='Pedido publicado correctamente en el canal de pedidos de Discord.';
            } catch(Throwable $discordError) {
                $error='El pedido se guardó en la web, pero Discord NO pudo publicarlo: '.$discordError->getMessage();
                $msg='Pedido guardado en la web.';
            }
        } elseif (($_POST['action'] ?? '') === 'delete_order') {
            $orderId=(int)($_POST['order_id']??0);
            if($orderId<1) throw new RuntimeException('Pedido no válido.');
            try {
                close_member_order($pdo,$orderId,$discordId);
                $msg='Pedido cancelado y retirado de Discord.';
            } catch(Throwable $orderError) {
                $error=$orderError->getMessage();
            }
        } elseif (($_POST['action'] ?? '') === 'complete_received_order') {
            $orderId=(int)($_POST['order_id']??0);
            if($orderId<1) throw new RuntimeException('Pedido no válido.');
            try {
                complete_received_order($pdo,$orderId,$discordId);
                $msg='Pedido marcado como entregado y retirado de Discord.';
            } catch(Throwable $orderError) {
                $error=$orderError->getMessage();
            }
        /* El miembro puede eliminar su personaje y sus profesiones desde Mi panel. */
        } elseif (($_POST['action'] ?? '') === 'sync_professions') {
            try {
                sync_professions_to_discord($pdo);
                $msg = 'Profesiones sincronizadas con Discord correctamente.';
            } catch (Throwable $discordError) {
                $error = 'Discord NO pudo sincronizar las profesiones: ' . $discordError->getMessage();
            }
        } elseif (($_POST['action'] ?? '') === 'delete_character') {
            $profileId = (int)($_POST['profile_id'] ?? 0);
            if ($profileId <= 0) throw new RuntimeException('Personaje no válido.');
            $st = $pdo->prepare('SELECT id,name FROM member_profiles WHERE id=? AND discord_id=? AND faction=? LIMIT 1');
            $st->execute([$profileId,$discordId,$faction]);
            $toDelete = $st->fetch(PDO::FETCH_ASSOC);
            if (!$toDelete) throw new RuntimeException('El personaje no existe o no pertenece a tu cuenta.');
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM professions WHERE member_discord_id=? AND faction=? AND character_name=?')->execute([$discordId,$faction,$toDelete['name']]);
            $pdo->prepare('DELETE FROM member_profiles WHERE id=? AND discord_id=? AND faction=?')->execute([$profileId,$discordId,$faction]);
            $pdo->commit();

            try {
                sync_professions_to_discord($pdo);
                $msg = 'Personaje eliminado correctamente y directorio de profesiones actualizado en Discord.';
            } catch (Throwable $discordError) {
                $error = 'Personaje eliminado correctamente, pero Discord no pudo actualizarse: '.$discordError->getMessage();
                $msg = 'El personaje se ha eliminado.';
            }

            $character = null;
            $professions = [];
            $st=$pdo->prepare('SELECT * FROM member_profiles WHERE discord_id=? AND faction=? ORDER BY id ASC');
            $st->execute([$discordId,$faction]);
            $characters=$st->fetchAll(PDO::FETCH_ASSOC);
            $character=null;
        } else {

        $name = trim((string)($_POST['name'] ?? ''));
        $race = trim((string)($_POST['race'] ?? ''));
        $class = trim((string)($_POST['class_name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        $allowedRolesByClass = [
            'Guerrero' => ['Tanque','DPS'],
            'Cazador' => ['DPS'],
            'Pícaro' => ['DPS'],
            'Sacerdote' => ['DPS','Healer'],
            'Chamán' => ['DPS','Healer'],
            'Mago' => ['DPS'],
            'Brujo' => ['DPS'],
            'Druida' => ['Tanque','DPS','Healer'],
            'Paladín' => ['Tanque','DPS','Healer'],
        ];
        $level = (int)($_POST['level'] ?? 0);
        $level = ($level >= 1 && $level <= 100) ? $level : null;

        if ($name === '' || $race === '' || $class === '') {
            throw new RuntimeException('Nombre, raza y clase son obligatorios.');
        }
        if (!isset($races[$race]) || !in_array($class,$races[$race],true)) {
            throw new RuntimeException('La combinación raza/clase no es válida para '.nexus_faction_label().'.');
        }
        if (!isset($allowedRolesByClass[$class]) || !in_array($role,$allowedRolesByClass[$class],true)) {
            throw new RuntimeException('El rol seleccionado no está disponible para la clase '.$class.'.');
        }

        $colors = [
            'Guerrero'=>'#C79C6E','Cazador'=>'#ABD473','Pícaro'=>'#FFF569','Sacerdote'=>'#FFFFFF',
            'Chamán'=>'#0070DE','Mago'=>'#69CCF0','Brujo'=>'#9482C9','Druida'=>'#FF7D0A','Paladín'=>'#F58CBA'
        ];
        $color = $colors[$class] ?? '#d8ad55';

        $profileId = (int)($_POST['profile_id'] ?? 0);
        $oldName = '';
        if ($profileId > 0) {
            $check = $pdo->prepare('SELECT id,name FROM member_profiles WHERE id=? AND discord_id=? AND faction=? LIMIT 1');
            $check->execute([$profileId,$discordId,$faction]);
            $owned = $check->fetch(PDO::FETCH_ASSOC);
            if (!$owned) throw new RuntimeException('El personaje seleccionado no pertenece a tu cuenta.');
            $oldName = (string)$owned['name'];
        }

        $dup = $pdo->prepare('SELECT id FROM member_profiles WHERE discord_id=? AND faction=? AND name=? AND id<>? LIMIT 1');
        $dup->execute([$discordId,$faction,$name,$profileId]);
        if ($dup->fetchColumn()) throw new RuntimeException('Ya tienes un personaje registrado con ese nombre.');

        $pdo->beginTransaction();
        if ($profileId > 0) {
            $u = $pdo->prepare('UPDATE member_profiles SET name=?,race=?,class_name=?,role=?,level=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND discord_id=? AND faction=?');
            $u->execute([$name,$race,$class,$role,$level,$profileId,$discordId,$faction]);
            if ($oldName !== $name) {
                $pdo->prepare('UPDATE professions SET character_name=? WHERE member_discord_id=? AND faction=? AND character_name=?')->execute([$name,$discordId,$faction,$oldName]);
            }
        } else {
            $u = $pdo->prepare('INSERT INTO member_profiles (discord_id,faction,name,race,class_name,role,level) VALUES (?,?,?,?,?,?,?)');
            $u->execute([$discordId,$faction,$name,$race,$class,$role,$level]);
            $profileId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM professions WHERE member_discord_id=? AND faction=? AND character_name=?')->execute([$discordId,$faction,$name]);
        $ins = $pdo->prepare('INSERT INTO professions (member_discord_id,member_username,character_name,race,faction,class_name,level,profession_name,profession_type,skill_level,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');

        $selectedPrimary = [];
        for ($i=1; $i<=2; $i++) {
            $pn = trim((string)($_POST['profession'.$i] ?? ''));
            if ($pn !== '' && in_array($pn,$primaryOptions,true) && !in_array($pn,$selectedPrimary,true)) {
                $selectedPrimary[] = $pn;
                $sl = (int)($_POST['profession'.$i.'_skill'] ?? 0);
                $ins->execute([$discordId,$member['username'],$name,$race,$faction,$class,$level,$pn,'primary',$sl>0?min($sl,1000):null,'active']);
            }
        }

        foreach ([
            'secondary_cooking'=>'Cocina',
            'secondary_fishing'=>'Pesca',
            'secondary_first_aid'=>'Primeros Auxilios'
        ] as $field=>$professionName) {
            $sl = (int)($_POST[$field.'_skill'] ?? 0);
            $enabled = isset($_POST[$field]) && $_POST[$field] === '1';
            if ($enabled) {
                $ins->execute([$discordId,$member['username'],$name,$race,$faction,$class,$level,$professionName,'secondary',$sl>0?min($sl,1000):null,'active']);
            }
        }

        $pdo->commit();

        /* Siempre intentamos sincronizar. Así un canal/token mal configurado no queda oculto. */
        try {
            $discordSync = sync_professions_to_discord($pdo);
            if ($error === '') {
                $msg = 'Perfil actualizado y directorio de profesiones sincronizado con Discord.';
            }
        } catch (Throwable $discordError) {
            $error = 'El perfil se guardó, pero Discord NO pudo actualizar el canal de profesiones: ' . $discordError->getMessage();
            $msg = 'El perfil se ha guardado en la web.';
        }

        $st=$pdo->prepare('SELECT * FROM member_profiles WHERE discord_id=? AND faction=? ORDER BY id ASC');
        $st->execute([$discordId,$faction]);
        $characters=$st->fetchAll(PDO::FETCH_ASSOC);
        $character=null;
        foreach($characters as $candidate){ if((int)$candidate['id']===$profileId){$character=$candidate;break;} }
        if(!$character && $characters) $character=$characters[0];
        $st=$pdo->prepare("SELECT * FROM professions WHERE member_discord_id=? AND faction=? AND status='active' ORDER BY profession_type,profession_name");
        $st->execute([$discordId,$faction]);
        $professions=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$pdo->prepare("SELECT * FROM member_orders WHERE member_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
        $st->execute([$discordId,$faction]);
        $orders=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$pdo->prepare("SELECT * FROM member_orders WHERE recipient_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
        $st->execute([$discordId,$faction]);
        $receivedOrders=$st->fetchAll(PDO::FETCH_ASSOC);
        }

        /* Refresca los pedidos después de cualquier acción del panel. */
        $st=$pdo->prepare("SELECT * FROM member_orders WHERE member_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
        $st->execute([$discordId,$faction]);
        $orders=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$pdo->prepare("SELECT * FROM member_orders WHERE recipient_discord_id=? AND faction=? AND status='active' ORDER BY created_at DESC");
        $st->execute([$discordId,$faction]);
        $receivedOrders=$st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
}

$selectedName=(string)($character['name']??'');
$professions=array_values(array_filter($professions,fn($p)=>(string)($p['character_name']??'')===$selectedName));
$primary = array_values(array_filter($professions,fn($p)=>$p['profession_type']==='primary'));
$secondary = [];
foreach ($professions as $p) if ($p['profession_type']==='secondary') $secondary[$p['profession_name']]=$p;
function prof_value(array $arr,int $i,string $key): string { return e((string)($arr[$i][$key]??'')); }
function sec_skill(array $arr,string $name): string { return e((string)($arr[$name]['skill_level']??'')); }
function sec_checked(array $arr,string $name): string { return isset($arr[$name]) ? 'checked' : ''; }
function member_class_icon(string $class): string { $c=mb_strtolower($class,'UTF-8'); return ['guerrero'=>'⚔','cazador'=>'➹','pícaro'=>'☠','picaro'=>'☠','sacerdote'=>'✚','chamán'=>'⚡','chaman'=>'⚡','mago'=>'❄','brujo'=>'☄','druida'=>'☘','paladín'=>'✦','paladin'=>'✦'][$c]??'✦'; }
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/includes/seo.php'; nexus_render_seo('/panel'); ?>
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/panel.css?v=20261011c">
<link rel="icon" href="./favicon.ico">
</head><body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<main><section><div class="container">
<div class="page-hero"><div class="eyebrow">NEXUS · PANEL DE MIEMBRO</div><h1>Mi panel</h1><p>Gestiona tus personajes, profesiones y pedidos. Puedes registrar varios personajes de tu cuenta de Discord.</p></div>
<?php if($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?><?php if($error): ?><div class="flash" style="border-color:#a33"><?=e($error)?></div><?php endif; ?>
<div class="card panel-card">
<div class="panel-discord"><img src="<?=e($_SESSION['member_avatar']??'')?>" alt="" width="56" height="56" style="border-radius:50%"><div><strong><?=e($member['username'])?></strong><div class="muted">Rol Discord: <?=e($member['role'])?> · Facción: <?=e(nexus_faction_label())?></div></div></div>
<section class="my-characters"><div class="section-head"><div><div class="eyebrow">MI CUENTA</div><h2>Mis personajes registrados</h2><p class="muted">Solo aparecen aquí los personajes que tú has creado desde Mi panel.</p></div><a class="btn primary" href="/panel?new=1">＋ Nuevo personaje</a></div>
<?php if($characters): ?><div class="my-character-grid">
<?php foreach($characters as $c): $isCurrent=$character && (int)$character['id']===(int)$c['id']; ?>
<article class="my-character-card <?= $isCurrent?'selected':'' ?>" style="--class-color:<?=e(['Guerrero'=>'#C79C6E','Cazador'=>'#ABD473','Pícaro'=>'#FFF569','Sacerdote'=>'#FFFFFF','Chamán'=>'#0070DE','Mago'=>'#69CCF0','Brujo'=>'#9482C9','Druida'=>'#FF7D0A','Paladín'=>'#F58CBA'][$c['class_name']]??'#d8ad55')?>">
<div class="my-character-portrait" style="width:42px!important;height:42px!important;min-width:42px!important;min-height:42px!important;flex:0 0 42px!important;overflow:hidden!important;"><img style="display:block!important;width:42px!important;height:42px!important;max-width:42px!important;max-height:42px!important;min-width:42px!important;min-height:42px!important;object-fit:cover!important;" src="<?=e((function($race){$r=mb_strtolower(trim($race),'UTF-8');$m=['orco'=>'orco','troll'=>'troll','tauren'=>'tauren','no-muerto'=>'no-muerto','no muerto'=>'no-muerto','undead'=>'no-muerto','cielonato'=>'cielonato'];return './img/races/'.($m[$r]??'unknown').'.svg';})($c['race']))?>" alt="<?=e($c['race'])?>"></div>
<div class="my-character-info"><span class="my-character-race"><?=e($c['race'])?></span><h3><?=e($c['name'])?></h3><div class="my-character-class"><?=e(member_class_icon((string)$c['class_name']))?> <?=e($c['class_name'])?></div><div class="my-character-meta"><?= $c['level'] ? 'Nivel '.e((string)$c['level']) : 'Nivel —' ?></div></div>
<div class="my-character-actions"><a class="btn ghost" href="/panel?character=<?=e((string)$c['id'])?>">✏️ Editar</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_character"><input type="hidden" name="profile_id" value="<?=e((string)$c['id'])?>"><button class="btn danger" type="submit" onclick="return confirm('¿Seguro que quieres eliminar este personaje y sus profesiones?');">🗑️</button></form></div>
</article><?php endforeach; ?></div><?php else: ?><div class="empty-character"><strong>Aún no tienes personajes registrados.</strong><p class="muted">Pulsa «Nuevo personaje» para añadir el primero.</p></div><?php endif; ?></section>
<form method="post" class="panel-card sync-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="sync_professions"><button type="submit" class="btn">🔄 Sincronizar mis profesiones con Discord</button></form>
</div>
<?php if($character || isset($_GET['new'])): ?>
<div class="card panel-card character-editor"><div class="section-head"><div><div class="eyebrow"><?= $character ? 'EDITAR PERSONAJE' : 'NUEVO PERSONAJE' ?></div><h2><?= $character ? 'Editar '.e($character['name']) : 'Añadir personaje' ?></h2></div><?php if($character): ?><a class="btn ghost" href="/panel">Cancelar</a><?php endif; ?></div>
<form method="post" class="panel-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="profile_id" value="<?=e((string)($character['id']??0))?>">
<h2>Personaje</h2><label>Nombre del personaje<input name="name" maxlength="80" required value="<?=e($character['name']??'')?>"></label>
<label>Raza<select name="race" id="race" required><option value="">Selecciona una raza</option><?php foreach($races as $race=>$cls): ?><option value="<?=e($race)?>" <?=($character['race']??'')===$race?'selected':''?>><?=e($race)?></option><?php endforeach; ?></select></label>
<label>Clase<select name="class_name" id="class_name" required><option value="">Selecciona una clase</option><?php foreach(nexus_wow_classes() as $classOption): ?><option value="<?=e($classOption)?>" <?=($character['class_name']??'')===$classOption?'selected':''?>><?=e($classOption)?></option><?php endforeach; ?></select></label>
<label>Rol de combate<select name="role" id="character_role" required><option value="">Selecciona un rol</option><?php foreach(['DPS','Tanque','Healer'] as $roleOption): ?><option value="<?=e($roleOption)?>" <?=($character['role']??'')===$roleOption?'selected':''?>><?=e($roleOption)?></option><?php endforeach; ?></select></label>
<label>Nivel<input type="number" name="level" min="1" max="100" value="<?=e((string)($character['level']??''))?>"></label>
<h2>Profesiones principales</h2><p class="muted">Selecciona hasta dos profesiones principales.</p>
<div class="profession-row"><label>Profesión principal 1<select name="profession1"><option value="">Sin profesión</option><?php foreach($primaryOptions as $p): ?><option value="<?=e($p)?>" <?=($primary[0]['profession_name']??'')===$p?'selected':''?>><?=e($p)?></option><?php endforeach; ?></select></label><label>Nivel<input type="number" name="profession1_skill" min="1" max="1000" value="<?=prof_value($primary,0,'skill_level')?>"></label></div>
<div class="profession-row"><label>Profesión principal 2<select name="profession2"><option value="">Sin profesión</option><?php foreach($primaryOptions as $p): ?><option value="<?=e($p)?>" <?=($primary[1]['profession_name']??'')===$p?'selected':''?>><?=e($p)?></option><?php endforeach; ?></select></label><label>Nivel<input type="number" name="profession2_skill" min="1" max="1000" value="<?=prof_value($primary,1,'skill_level')?>"></label></div>
<h2>Profesiones secundarias</h2><p class="muted">Puedes registrar Cocina, Pesca y Primeros Auxilios.</p><div class="secondary-grid">
<div class="secondary-box"><label><span><input type="checkbox" name="secondary_cooking" value="1" <?=sec_checked($secondary,'Cocina')?>> 🍳 Cocina</span><input type="number" name="secondary_cooking_skill" min="1" max="1000" placeholder="Nivel" value="<?=sec_skill($secondary,'Cocina')?>"></label></div>
<div class="secondary-box"><label><span><input type="checkbox" name="secondary_fishing" value="1" <?=sec_checked($secondary,'Pesca')?>> 🎣 Pesca</span><input type="number" name="secondary_fishing_skill" min="1" max="1000" placeholder="Nivel" value="<?=sec_skill($secondary,'Pesca')?>"></label></div>
<div class="secondary-box"><label><span><input type="checkbox" name="secondary_first_aid" value="1" <?=sec_checked($secondary,'Primeros Auxilios')?>> 🩹 Primeros Auxilios</span><input type="number" name="secondary_first_aid_skill" min="1" max="1000" placeholder="Nivel" value="<?=sec_skill($secondary,'Primeros Auxilios')?>"></label></div></div>
<label>Nombre en Discord<input value="<?=e($member['username'])?>" readonly></label><div class="panel-actions"><button class="btn primary" type="submit">💾 Guardar personaje</button><?php if($character): ?><button class="btn danger" type="submit" name="action" value="delete_character" formnovalidate onclick="return confirm('¿Seguro que quieres eliminar este personaje y sus profesiones?');">🗑️ Eliminar personaje</button><?php endif; ?></div></form></div>
<?php endif; ?>
<div class="card panel-card orders-card"><h2>📦 Pedidos a la hermandad</h2><p class="muted">Publica una petición para que otros miembros puedan ayudarte. Al crearla se publica automáticamente en Discord.</p>
<form method="post" class="panel-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_order">
<div class="profession-row"><label>¿A quién se lo solicitas?<select name="order_recipient" required><option value="">Selecciona un miembro</option><?php foreach($guildMembers as $gm): ?><option value="<?=e($gm['discord_id'])?>"><?=e($gm['discord_username'])?><?=trim((string)$gm['character_name'])!==''?' · '.e($gm['character_name']):''?></option><?php endforeach; ?></select></label><label>Item solicitado<input name="order_item" maxlength="180" required placeholder="Ej.: 20 x Mineral de hierro"></label></div>
<label>Título del pedido<input name="order_title" maxlength="180" required placeholder="Ej.: Necesito ayuda con materiales"></label>
<div class="profession-row"><label>Categoría<select name="order_category"><option>Profesiones</option><option>Objetos</option><option>Materiales</option><option>Equipamiento</option><option>Misiones</option><option>Mazmorras</option><option>Raids</option><option>General</option></select></label><label>Cantidad<input name="order_quantity" maxlength="80" placeholder="Ej.: 20 unidades"></label></div>
<label>¿Qué necesitas?<textarea name="order_details" maxlength="1500" required rows="5" placeholder="Explica lo que necesitas y cualquier detalle importante..."></textarea></label>
<label>Prioridad<select name="order_priority"><option value="normal">🟢 Normal</option><option value="alta">🟠 Alta</option><option value="urgente">🔴 Urgente</option></select></label>
<button class="btn primary" type="submit">📦 Publicar pedido en Discord</button></form>
<?php if($receivedOrders): ?><div class="orders-list received-orders"><h3>🔔 Pedidos para ti <span class="order-count"><?=count($receivedOrders)?></span></h3><p class="muted">Estos miembros te han solicitado un item o ayuda. Cuando hagas la entrega, márcalo como entregado.</p><?php foreach($receivedOrders as $order): ?><article class="order-item order-received"><div><strong><?=e($order['title'])?></strong><div class="muted">🙋 <?=e($order['member_username'])?><?=trim((string)$order['character_name'])!==''?' · '.e($order['character_name']):''?> · <?=e($order['category'])?> · <?=e($order['priority'])?></div><p><strong>📦 Item:</strong> <?=e($order['item_name']??'')?></p><p><?=nl2br(e($order['details']))?></p><?=trim((string)$order['quantity'])!==''?'<div class="muted"><strong>Cantidad:</strong> '.e($order['quantity']).'</div>':''?></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="complete_received_order"><input type="hidden" name="order_id" value="<?=e((string)$order['id'])?>"><button class="btn primary" type="submit" onclick="return confirm('¿Has realizado la entrega? El pedido se marcará como completado y se retirará de Discord.')">✅ Marcar como entregado</button></form></article><?php endforeach; ?></div><?php endif; ?>
<?php if($orders): ?><div class="orders-list"><h3>📤 Mis pedidos activos</h3><?php foreach($orders as $order): ?><article class="order-item"><div><strong><?=e($order['title'])?></strong><div class="muted">🎯 Para: <?=e($order['recipient_username'])?><?=trim((string)$order['recipient_character_name'])!==''?' · '.e($order['recipient_character_name']):''?> · <?=e($order['category'])?> · <?=e($order['priority'])?></div><p><strong>📦 Item:</strong> <?=e($order['item_name']??'')?></p><p><?=nl2br(e($order['details']))?></p></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_order"><input type="hidden" name="order_id" value="<?=e((string)$order['id'])?>"><button class="btn danger" type="submit" onclick="return confirm('¿Cancelar este pedido? También se retirará de Discord.')">🗑️ Ya no lo necesito</button></form></article><?php endforeach; ?></div><?php endif; ?></div>
</div></div></section></main>
<script>
(()=>{const init=()=>{const matrix=<?=json_encode($races,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;const roleMatrix={Guerrero:['Tanque','DPS'],Cazador:['DPS'],Pícaro:['DPS'],Sacerdote:['DPS','Healer'],Chamán:['DPS','Healer'],Mago:['DPS'],Brujo:['DPS'],Druida:['Tanque','DPS','Healer'],Paladín:['Tanque','DPS','Healer']};let currentClass=<?=json_encode($character['class_name']??'',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;let currentRole=<?=json_encode($character['role']??'',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;const r=document.getElementById('race'),c=document.getElementById('class_name'),role=document.getElementById('character_role');if(!r||!c||!role)return;const fillRoles=()=>{const selected=currentRole;const list=roleMatrix[c.value]||[];role.replaceChildren(new Option('Selecciona un rol',''));for(const x of list){const o=new Option(x,x);if(x===selected)o.selected=true;role.add(o)}if(!list.includes(selected)){role.value='';currentRole='';}};const fillClasses=()=>{const selected=currentClass;const list=matrix[r.value]||[];c.replaceChildren(new Option('Selecciona una clase',''));for(const x of list){const o=new Option(x,x);if(x===selected)o.selected=true;c.add(o)}if(!list.includes(selected)){c.value='';currentClass='';}fillRoles();};r.addEventListener('change',()=>{currentClass='';currentRole='';fillClasses()});c.addEventListener('change',()=>{currentRole='';fillRoles()});fillClasses();};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();})();
</script></body></html>
