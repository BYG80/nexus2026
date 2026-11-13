<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
start_secure_session();


$isMemberLogged = !empty($_SESSION['member_discord_id']);
if ($isMemberLogged) { try { require_discord_member(); } catch (Throwable $e) { $isMemberLogged = false; } }
require_once __DIR__ . '/includes/seo.php';

$pdo = null;
$leaders=[];
$totalDkp=0;
try {
    $pdo=db();
    $stmt=$pdo->prepare("SELECT r.name,r.race,r.class_name,r.role,r.discord_id,COALESCE(SUM(dl.amount),0) balance
        FROM roster r LEFT JOIN dkp_ledger dl ON dl.member_discord_id=r.discord_id
        WHERE r.status='active' AND r.faction=?
        GROUP BY r.id,r.name,r.race,r.class_name,r.role,r.discord_id
        ORDER BY balance DESC,r.name ASC");
    $stmt->execute([nexus_faction()]);
    $leaders=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($leaders as $l) $totalDkp += (int)$l['balance'];
} catch(Throwable $e) {}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RSP | NEXUS — Cómo funciona</title>
<meta name="robots" content="index,follow">
<link rel="stylesheet" href="./css/global.css">
<link rel="stylesheet" href="./css/roster.css">
<link rel="stylesheet" href="./css/rsp.css">
<link rel="icon" href="./favicon.ico">
</head>
<body>
<?php include __DIR__.'/includes/public-nav.php'; ?>
<header class="page-hero"><div class="container">
<div class="eyebrow">NEXUS · <?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?> · WOW FOREVER</div>
<h1>Sistema RSP</h1>
<p>Los RSP recompensan la asistencia, la preparación, la colaboración, la mentoría y la participación PvP. El máximo ganado es de <strong>50 RSP por semana</strong> y el saldo máximo acumulado es de <strong>100 RSP</strong>.</p>
</div></header>
<main><section><div class="container">
<?php if($isMemberLogged): ?><div class="roster-stat-panel" style="margin-bottom:22px">
<div class="roster-stat"><strong><?=count($leaders)?></strong><span>Jugadores</span></div>
<div class="roster-stat"><strong><?=$totalDkp?></strong><span>RSP acumulados</span></div>
<div class="roster-stat"><strong>⚔</strong><span><?=e(nexus_faction_label())?></span></div>
</div>
<div class="card">
<h2>Clasificación de la hermandad</h2>
<p class="muted">El saldo es la suma de todos los movimientos registrados. Las correcciones y penalizaciones también quedan reflejadas en el historial de administración.</p>
<div style="overflow:auto">
<table class="admin-table">
<thead><tr><th>#</th><th>Personaje</th><th>Raza</th><th>Clase</th><th>Rol</th><th>RSP</th></tr></thead>
<tbody>
<?php foreach($leaders as $i=>$l): ?>
<tr>
<td><strong><?=($i+1)?></strong></td>
<td><strong><?=htmlspecialchars((string)$l['name'],ENT_QUOTES,'UTF-8')?></strong></td>
<td><?=htmlspecialchars((string)$l['race'],ENT_QUOTES,'UTF-8')?></td>
<td><?=htmlspecialchars((string)$l['class_name'],ENT_QUOTES,'UTF-8')?></td>
<td><?=htmlspecialchars((string)($l['role']?:'—'),ENT_QUOTES,'UTF-8')?></td>
<td><strong><?=((int)$l['balance'])?></strong></td>
</tr>
<?php endforeach; ?>
<?php if(!$leaders): ?><tr><td colspan="6">Todavía no hay personajes enlazados al roster.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:18px"><h2>📖 Cómo funciona el RSP</h2><p class="muted">Aquí puedes consultar las reglas y la tabla de recompensas del sistema RSP. El ranking y el saldo individual están disponibles al iniciar sesión como miembro de NEXUS.</p><a class="btn primary" href="/auth/discord.php?return_to=/rsp">🔐 Entrar con Discord</a></div>
<?php endif; ?>

<div class="card" style="margin-top:18px">
<h2 class="rsp-section-title">🏆 Tabla oficial de recompensas RSP</h2>
<p class="muted">Estas son las cantidades configuradas actualmente. Las actividades marcadas como mentoría, guía y PvP se asignan desde administración.</p>
<div class="rsp-table-wrap">
<table class="rsp-table">
<thead><tr><th>Actividad</th><th class="rsp-points">RSP</th><th>Condición</th></tr></thead>
<tbody>
<?php
$publicRules=[];
try {
    if ($pdo instanceof PDO) {
        $ruleQ=$pdo->query("SELECT label,amount,code FROM dkp_rules WHERE active=1 ORDER BY sort_order,id");
        $publicRules=$ruleQ->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Throwable $e) {}
foreach($publicRules as $rule):
    $condition='Actividad registrada por administración.';
    if($rule['code']==='pvp_bg_group') $condition='Grupo de 3 a 5 miembros.';
    elseif($rule['code']==='pvp_premade') $condition='Premade PvP registrada.';
    elseif($rule['code']==='mentor') $condition='Hasta 20 RSP por semana según responsabilidades.';
    elseif($rule['code']==='guide') $condition='Hasta 10 RSP por semana según responsabilidades.';
?>
<tr><td class="rsp-activity"><?=e($rule['label'])?></td><td class="rsp-points">+<?=((int)$rule['amount'])?> RSP</td><td class="rsp-condition"><?=e($condition)?></td></tr>
<?php endforeach; ?>
<tr class="rsp-limit"><td class="rsp-activity">Límite semanal</td><td class="rsp-points">50 RSP</td><td class="rsp-condition">Máximo ganado por jugador y semana.</td></tr>
<tr class="rsp-limit"><td class="rsp-activity">Límite acumulado</td><td class="rsp-points">100 RSP</td><td class="rsp-condition">El excedente sobre 100 no se conserva.</td></tr>
</tbody>
</table>
</div>
</div>

<div class="card" style="margin-top:18px">
<h2>Reglas del sistema RSP</h2>
<div class="grid two">
<div><h3>⚔ Límites</h3><p class="muted">Se pueden ganar como máximo <strong>50 RSP por semana</strong> y acumular hasta <strong>100 RSP</strong>. Los puntos no caducan. El sistema nunca permite saldo negativo por las recompensas normales.</p></div>
<div><h3>🎲 Loot</h3><p class="muted">Al caer un objeto, los jugadores elegibles declaran antes de las tiradas cuánto gastan: <strong>0, 10, 25 o 50 RSP</strong>. El gasto amplía el rango del dado: 1–100, 1–110, 1–125 o 1–150.</p></div>
<div><h3>💰 Gasto</h3><p class="muted">Los RSP se descuentan al confirmar la participación, ganes o pierdas. No se puede cambiar el gasto después de conocer otras tiradas. En empate se repite sin gastar RSP.</p></div>
<div><h3>🏆 Contribución</h3><p class="muted">La asistencia a raid, preparación, materiales, ayuda, mentoría, guías y PvP generan RSP según la tabla oficial. Las actividades no pueden superar conjuntamente los 50 RSP semanales.</p></div>
<div><h3>⚔ PvP Premade</h3><p class="muted"><strong>+20 RSP</strong> por una premade PvP registrada. Se puede registrar directamente desde el panel de administración sin necesidad de crear un evento de calendario.</p></div>
<div><h3>🏹 BG en grupo</h3><p class="muted"><strong>+5 RSP</strong> cuando la BG se registra con un grupo de <strong>3 a 5 miembros</strong>. El panel de administración valida automáticamente este requisito.</p></div>
</div>
</div>

<div class="card" style="margin-top:18px">
<h2>Mentores y guías</h2>
<div class="grid two">
<div><h3>🎓 Mentores de clase · hasta 20 RSP/semana</h3><p class="muted">Personajes principales con experiencia en su clase. Ayudan con talentos, rotaciones, estadísticas, equipo y preparación para raid. Cobran por cumplir sus responsabilidades aunque no asistan a raid.</p></div>
<div><h3>📚 Guías · hasta 10 RSP/semana</h3><p class="muted">Aportan conocimiento general sobre profesiones, mazmorras, raids, preguntas frecuentes y preparación de grupos. También cobran por cumplir sus responsabilidades aunque no asistan a raid.</p></div>
</div>
</div>

<div class="card" style="margin-top:18px">
<h2>Transparencia y revisión</h2>
<ul class="list">
<li>Se registra cada RSP ganado, gastado, corregido o penalizado.</li>
<li>Los miembros pueden consultar su saldo y el ranking de la hermandad.</li>
<li>Las reglas están pensadas inicialmente para una prueba de cuatro semanas.</li>
<li>Los cambios de valores se anunciarán antes de aplicarse.</li>
</ul>
<p class="muted" style="margin-top:16px"><strong>Objetivo:</strong> premiar a quien juega, a quien contribuye y a quien enseña, evitando que el sistema se convierta en una ventaja permanente para los veteranos.</p>
</div>
</div></section></main>
<footer class="site-footer"><div class="container"><strong>NEXUS — VETERANOS DE AZEROTH</strong><p>World of Warcraft Forever · <?=e(nexus_faction_label())?> · Comunidad española</p></div></footer>
</body></html>
