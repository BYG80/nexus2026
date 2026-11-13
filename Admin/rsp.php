<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_admin();

$pdo = db();

// Exportación JSON de saldos oficiales para el addon NEXUS RSP.
if (isset($_GET['download']) && $_GET['download'] === 'json') {
    $sqlExport = "SELECT r.name AS character_name, r.discord_id,
              COALESCE((SELECT SUM(dl.amount) FROM dkp_ledger dl WHERE dl.member_discord_id=r.discord_id),0) AS balance
            FROM roster r
            INNER JOIN members m ON m.discord_id=r.discord_id AND m.is_member=1
            WHERE r.status='active' AND r.faction=? AND r.source='admin' AND r.discord_id IS NOT NULL AND r.discord_id<>''
              AND r.id=(SELECT MIN(r2.id) FROM roster r2 WHERE r2.discord_id=r.discord_id AND r2.status='active' AND r2.faction=r.faction AND r2.source='admin')
            ORDER BY r.name";
    $exportStmt = $pdo->prepare($sqlExport);
    $exportStmt->execute([nexus_faction()]);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    $balances = [];
    foreach ($exportRows as $exportRow) {
        $balances[] = [
            'character' => (string)$exportRow['character_name'],
            'discordId' => (string)$exportRow['discord_id'],
            'balance' => (int)$exportRow['balance'],
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexus-rsp-saldos-web.json"');
    echo json_encode(['format' => 'nexus-rsp-balances', 'version' => 1, 'source' => 'NEXUS Web', 'faction' => nexus_faction(), 'balances' => $balances], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$msg = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf($_POST['csrf'] ?? null);
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'sync_ranking') {
            $result = sync_rsp_ranking_to_discord($pdo);
            $msg = $result['created'] ? 'Ranking RSP publicado en Discord.' : 'Ranking RSP actualizado en Discord.';
        }

        if ($action === 'reset_player' || $action === 'set_player_balance') {
            $discordId = trim((string)($_POST['discord_id'] ?? ''));
            if ($discordId === '') throw new RuntimeException('Jugador no válido.');
            // El selector se alimenta de Miembros: solo cuentas verificadas como miembros
            // y con al menos un personaje asociado a la facción actual.
            $activeMemberStmt = $pdo->prepare("SELECT m.discord_username,
                    GROUP_CONCAT(DISTINCT mp.name ORDER BY mp.name SEPARATOR ', ') character_names
                FROM members m
                INNER JOIN member_profiles mp ON mp.discord_id=m.discord_id
                WHERE m.discord_id=? AND m.is_member=1 AND mp.faction=?
                GROUP BY m.discord_id,m.discord_username LIMIT 1");
            $activeMemberStmt->execute([$discordId, nexus_faction()]);
            $activeMember = $activeMemberStmt->fetch(PDO::FETCH_ASSOC);
            if (!$activeMember) throw new RuntimeException('El jugador seleccionado ya no figura entre los miembros activos de esta facción.');
            $target = $action === 'reset_player' ? 0 : (int)($_POST['target_balance'] ?? 0);
            if ($target < 0) throw new RuntimeException('El saldo objetivo no puede ser negativo.');
            $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=?");
            $st->execute([$discordId]);
            $balance = (int)$st->fetchColumn();
            $delta = $target - $balance;
            if ($delta === 0) {
                $msg = 'Ese jugador ya tiene ' . $target . ' RSP.';
            } else {
                $rosterStmt = $pdo->prepare("SELECT id,name FROM roster WHERE discord_id=? AND status='active' AND faction=? AND source='admin' ORDER BY id LIMIT 1");
                $rosterStmt->execute([$discordId, nexus_faction()]);
                $roster = $rosterStmt->fetch(PDO::FETCH_ASSOC);
                $lastNameStmt = $pdo->prepare("SELECT character_name FROM dkp_ledger WHERE member_discord_id=? AND character_name IS NOT NULL ORDER BY id DESC LIMIT 1");
                $lastNameStmt->execute([$discordId]);
                $characterName = $roster['name'] ?? (($activeMember['character_names'] ?? '') ?: ($lastNameStmt->fetchColumn() ?: ($activeMember['discord_username'] ?? 'Miembro')));
                $ins = $pdo->prepare("INSERT INTO dkp_ledger (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $ins->execute([$discordId, $roster['id'] ?? null, $characterName, null, $delta, 'adjustment', 'Ajuste manual de saldo RSP a ' . $target, 'admin', 'balance-set:' . bin2hex(random_bytes(12)), (string)($_SESSION['admin_user'] ?? 'admin')]);
                try { sync_rsp_ranking_to_discord($pdo); $msg = 'Saldo de ' . $characterName . ' ajustado a ' . $target . ' RSP y ranking sincronizado.'; }
                catch (Throwable $discordError) { $msg = 'Saldo de ' . $characterName . ' ajustado a ' . $target . ' RSP, pero no se pudo actualizar Discord: ' . $discordError->getMessage(); }
            }
        }

        if ($action === 'reset_all' || $action === 'set_all_balances') {
            $target = $action === 'reset_all' ? 0 : (int)($_POST['target_balance'] ?? 0);
            if ($target < 0) throw new RuntimeException('El saldo objetivo no puede ser negativo.');
            $st = $pdo->prepare("SELECT MAX(r.id) id, SUBSTRING_INDEX(GROUP_CONCAT(r.name ORDER BY r.id DESC SEPARATOR ','), ',', 1) name, r.discord_id, COALESCE((SELECT SUM(dl.amount) FROM dkp_ledger dl WHERE dl.member_discord_id=r.discord_id),0) balance FROM roster r WHERE r.status='active' AND r.faction=? AND r.source='admin' AND r.discord_id IS NOT NULL AND r.discord_id<>'' GROUP BY r.discord_id");
            $st->execute([nexus_faction()]);
            $players = $st->fetchAll(PDO::FETCH_ASSOC);
            $count = 0;
            $ins = $pdo->prepare("INSERT INTO dkp_ledger (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $pdo->beginTransaction();
            foreach ($players as $pl) {
                $delta = $target - (int)$pl['balance'];
                if ($delta === 0) continue;
                $ins->execute([$pl['discord_id'], $pl['id'], $pl['name'], null, $delta, 'adjustment', 'Ajuste general de RSP al saldo ' . $target, 'admin', 'balance-all:' . bin2hex(random_bytes(12)), (string)($_SESSION['admin_user'] ?? 'admin')]);
                $count++;
            }
            $pdo->commit();
            try { sync_rsp_ranking_to_discord($pdo); $msg = 'Saldo RSP ajustado a ' . $target . ' para ' . $count . ' personaje(s). Ranking de Discord actualizado.'; }
            catch (Throwable $discordError) { $msg = 'Saldo RSP ajustado a ' . $target . ' para ' . $count . ' personaje(s), pero no se pudo actualizar Discord: ' . $discordError->getMessage(); }
        }

        if ($action === 'clear_history') {
            // Compacta el libro mayor: elimina los movimientos anteriores y conserva el saldo como opening_balance; esas filas base se excluyen de la tabla de historial.
            $pdo->beginTransaction();
            $balances = $pdo->query("SELECT dl.member_discord_id, COALESCE(SUM(dl.amount),0) balance, (SELECT dl2.character_name FROM dkp_ledger dl2 WHERE dl2.member_discord_id=dl.member_discord_id AND dl2.character_name IS NOT NULL ORDER BY dl2.id DESC LIMIT 1) character_name, (SELECT dl3.roster_id FROM dkp_ledger dl3 WHERE dl3.member_discord_id=dl.member_discord_id AND dl3.roster_id IS NOT NULL ORDER BY dl3.id DESC LIMIT 1) roster_id FROM dkp_ledger dl WHERE dl.member_discord_id IS NOT NULL AND dl.member_discord_id<>'' GROUP BY dl.member_discord_id")->fetchAll(PDO::FETCH_ASSOC);
            $pdo->exec('DELETE FROM dkp_ledger');
            $ins = $pdo->prepare("INSERT INTO dkp_ledger (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $kept = 0;
            foreach ($balances as $b) {
                $balance = (int)$b['balance'];
                if ($balance === 0) continue;
                $ins->execute([$b['member_discord_id'], $b['roster_id'] !== null ? (int)$b['roster_id'] : null, $b['character_name'] ?: 'Personaje', null, $balance, 'opening_balance', 'Saldo inicial tras limpiar el historial anterior', 'admin', 'opening:' . bin2hex(random_bytes(12)), (string)($_SESSION['admin_user'] ?? 'admin')]);
                $kept++;
            }
            $pdo->commit();
            try { sync_rsp_ranking_to_discord($pdo); $msg = 'Historial detallado limpiado. Los saldos se conservaron como saldo inicial para ' . $kept . ' personaje(s) y se sincronizó Discord.'; }
            catch (Throwable $discordError) { $msg = 'Historial detallado limpiado y saldos conservados, pero no se pudo actualizar Discord: ' . $discordError->getMessage(); }
        }

        if ($action === 'award_bulk') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $ruleCodes = array_values(array_unique(array_filter(array_map('strval', $_POST['rule_codes'] ?? []))));
            // Compatibilidad con formularios de versiones anteriores.
            if (!$ruleCodes && !empty($_POST['rule_code'])) $ruleCodes = [trim((string)$_POST['rule_code'])];
            $members = array_values(array_unique(array_filter(array_map('strval', $_POST['members'] ?? []))));
            if (!$ruleCodes || !$members) throw new RuntimeException('Selecciona al menos un concepto y al menos un personaje.');
            $hasNonPvp = false;
            foreach ($ruleCodes as $rc) { if (!in_array($rc, ['pvp_premade','pvp_bg_group'], true)) { $hasNonPvp = true; break; } }
            if ($hasNonPvp && $eventId < 1) throw new RuntimeException('Selecciona una raid/evento para los conceptos que no son PvP.');
            if (in_array('pvp_bg_group', $ruleCodes, true) && (count($members) < 3 || count($members) > 5)) {
                throw new RuntimeException('Una BG en grupo debe registrarse con un mínimo de 3 y un máximo de 5 miembros.');
            }
            $event = ['title' => 'Actividad PvP NEXUS'];
            if ($eventId > 0) {
                $eventStmt = $pdo->prepare('SELECT title FROM events WHERE id=? LIMIT 1');
                $eventStmt->execute([$eventId]);
                $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
                if (!$event) throw new RuntimeException('La raid/evento no existe.');
            }
            $ruleStmt = $pdo->prepare('SELECT label, amount FROM dkp_rules WHERE code=? AND active=1 LIMIT 1');
            $ins = $pdo->prepare("INSERT IGNORE INTO dkp_ledger (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $rosterStmt = $pdo->prepare("SELECT id,name FROM roster WHERE discord_id=? AND status='active' AND source='admin' ORDER BY id LIMIT 1");
            $count = 0; $summary = [];
            $pdo->beginTransaction();
            foreach ($ruleCodes as $ruleCode) {
                $ruleStmt->execute([$ruleCode]);
                $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC);
                if (!$rule) throw new RuntimeException('Uno de los conceptos seleccionados no existe o está desactivado.');
                $ruleCount = 0;
                foreach ($members as $discordId) {
                    $rosterStmt->execute([$discordId]);
                    $roster = $rosterStmt->fetch(PDO::FETCH_ASSOC);
                    $currentBalanceStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=?');
                    $currentBalanceStmt->execute([$discordId]);
                    $currentBalance = (int)$currentBalanceStmt->fetchColumn();
                    $weeklyRemaining = max(0, rsp_weekly_cap() - rsp_weekly_earned($pdo, $discordId));
                    $awardAmount = min((int)$rule['amount'], $weeklyRemaining, max(0, 100 - $currentBalance));
                    if ($awardAmount <= 0) continue;
                    $sourceKey = ($ruleCode === 'raid_attendance') ? 'attendance:' . $eventId . ':' . $discordId : ($eventId > 0 ? 'rule:' . $ruleCode . ':' . $eventId . ':' . $discordId : 'pvp:' . $ruleCode . ':' . $discordId . ':' . bin2hex(random_bytes(8)));
                    $ins->execute([$discordId, $roster['id'] ?? null, $roster['name'] ?? null, $eventId > 0 ? $eventId : null, $awardAmount, $ruleCode, $rule['label'] . ' · ' . $event['title'], 'rule', $sourceKey, (string)($_SESSION['admin_user'] ?? 'admin')]);
                    $ruleCount += $ins->rowCount();
                }
                $count += $ruleCount;
                $summary[] = ['label' => $rule['label'], 'amount' => (int)$rule['amount'], 'members' => $members, 'count' => $ruleCount];
            }
            $pdo->commit();
            $published = [];
            try {
                foreach ($summary as $item) {
                    if ($item['count'] > 0) rsp_publish_discord_award($pdo, $eventId, $event['title'], $item['label'], $item['amount'], $item['members'], $item['count']);
                }
                sync_rsp_ranking_to_discord($pdo);
                $msg = $count . ' movimiento(s) RSP aplicado(s) con ' . count($summary) . ' concepto(s). Se han evitado duplicados y se ha sincronizado Discord.';
            } catch (Throwable $discordError) {
                $msg = $count . ' movimiento(s) RSP aplicado(s), pero no se pudo publicar todo en Discord: ' . $discordError->getMessage();
            }
        }

        if ($action === 'manual') {
            // El selector usa el ID de member_profiles, que es donde se guardan
            // los personajes creados desde el panel del miembro.
            $characterId = (int)($_POST['character_id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            $eventId = (int)($_POST['event_id'] ?? 0);
            $characterStmt = $pdo->prepare("SELECT mp.id,mp.name,mp.discord_id,
                    (SELECT r.id FROM roster r WHERE r.discord_id=mp.discord_id AND r.name=mp.name AND r.faction=mp.faction AND r.status='active' ORDER BY r.id DESC LIMIT 1) roster_id
                FROM member_profiles mp
                LEFT JOIN members m ON m.discord_id=mp.discord_id
                WHERE mp.id=? AND mp.faction=? AND mp.discord_id IS NOT NULL AND mp.discord_id<>''
                LIMIT 1");
            $characterStmt->execute([$characterId, nexus_faction()]);
            $roster = $characterStmt->fetch(PDO::FETCH_ASSOC);
            $discordId = trim((string)($roster['discord_id'] ?? ''));
            if (!$roster || $discordId === '' || $amount === 0 || $reason === '') {
                throw new RuntimeException('Selecciona un personaje registrado de un miembro activo, una cantidad distinta de 0 y un motivo.');
            }

            $sourceKey = 'manual:' . bin2hex(random_bytes(12));
            $ins = $pdo->prepare("INSERT INTO dkp_ledger
                (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $discordId,
                !empty($roster['roster_id']) ? (int)$roster['roster_id'] : null,
                $roster['name'] ?? null,
                $eventId > 0 ? $eventId : null,
                $amount,
                'manual',
                $reason,
                'manual',
                $sourceKey,
                (string)($_SESSION['admin_user'] ?? 'admin')
            ]);

            try {
                if ($eventId > 0) {
                    $eventTitleStmt=$pdo->prepare('SELECT title FROM events WHERE id=? LIMIT 1');
                    $eventTitleStmt->execute([$eventId]);
                    $eventTitle=(string)($eventTitleStmt->fetchColumn() ?: 'Evento NEXUS');
                } else {
                    $eventTitle='Movimiento manual';
                }
                rsp_publish_discord_award($pdo, $eventId, $eventTitle, $reason, $amount, [$discordId], 1);
                sync_rsp_ranking_to_discord($pdo);
                $msg = 'Movimiento RSP registrado, total actualizado y ranking de Discord sincronizado.';
            } catch (Throwable $discordError) {
                $msg = 'Movimiento RSP registrado, pero no se pudo publicar en Discord: ' . $discordError->getMessage();
            }
        }

        if ($action === 'save_rule') {
            $id = (int)($_POST['id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            if ($id < 1) throw new RuntimeException('Regla no válida.');
            $st = $pdo->prepare('UPDATE dkp_rules SET amount=?, active=? WHERE id=?');
            $st->execute([$amount, $active, $id]);
            $msg = 'Regla RSP actualizada.';
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
}

$events = $pdo->query("SELECT id,event_date,event_time,title,type FROM events ORDER BY event_date DESC,event_time DESC,id DESC LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
$rules = $pdo->query("SELECT * FROM dkp_rules ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC);

$selectedEvent = (int)($_GET['event_id'] ?? ($events[0]['id'] ?? 0));
$attendees = [];
if ($selectedEvent > 0) {
    $st = $pdo->prepare("SELECT ea.member_discord_id,ea.member_username,ea.race,ea.class_name,ea.role,ea.attended,
        COALESCE((SELECT SUM(dl.amount) FROM dkp_ledger dl WHERE dl.member_discord_id=ea.member_discord_id),0) balance
        FROM event_attendees ea WHERE ea.event_id=? AND (ea.faction=? OR ea.faction IS NULL) ORDER BY ea.attended DESC,ea.member_username ASC");
    $st->execute([$selectedEvent, nexus_faction()]);
    $attendees = $st->fetchAll(PDO::FETCH_ASSOC);
}

$leaderStmt = $pdo->prepare("SELECT r.name,r.race,r.class_name,r.discord_id,COALESCE(SUM(dl.amount),0) balance
    FROM roster r LEFT JOIN dkp_ledger dl ON dl.member_discord_id=r.discord_id
    WHERE r.status='active' AND r.faction=? AND r.source='admin' GROUP BY r.id,r.name,r.race,r.class_name,r.discord_id
    ORDER BY balance DESC,r.name ASC LIMIT 50");
$leaderStmt->execute([nexus_faction()]);
$leaders = $leaderStmt->fetchAll(PDO::FETCH_ASSOC);

// El selector usa los miembros activos de la página Miembros, no solo el roster de Admin.
// El saldo sigue siendo por cuenta Discord; se muestra también el nombre de sus personajes.
$balanceTargetsStmt = $pdo->prepare("SELECT m.discord_id,
    CONCAT(m.discord_username, CASE WHEN COUNT(DISTINCT mp.name)>0 THEN CONCAT(' — ', GROUP_CONCAT(DISTINCT mp.name ORDER BY mp.name SEPARATOR ', ')) ELSE '' END) name,
    COALESCE((SELECT SUM(dl.amount) FROM dkp_ledger dl WHERE dl.member_discord_id=m.discord_id),0) balance
    FROM members m
    INNER JOIN member_profiles mp ON mp.discord_id=m.discord_id
    WHERE m.is_member=1 AND mp.faction=? AND m.discord_id IS NOT NULL AND m.discord_id<>''
    GROUP BY m.discord_id,m.discord_username
    ORDER BY m.discord_username ASC");
$balanceTargetsStmt->execute([nexus_faction()]);
$balanceTargets = $balanceTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

// Los movimientos opening_balance conservan el saldo al limpiar, pero no se muestran como historial.
$ledger = $pdo->query("SELECT dl.*,e.title event_title,
    COALESCE((SELECT SUM(dl2.amount) FROM dkp_ledger dl2 WHERE dl2.member_discord_id=dl.member_discord_id AND dl2.id<=dl.id),0) balance_after
    FROM dkp_ledger dl LEFT JOIN events e ON e.id=dl.event_id
    WHERE COALESCE(dl.category,'') <> 'opening_balance'
    ORDER BY dl.id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);

admin_header('RSP', 'rsp');
?>

<div class="eyebrow">NEXUS · <?=e(mb_strtoupper(nexus_faction_label(),'UTF-8'))?> · RSP</div>
<h1>Sistema RSP</h1>
<p class="muted">Premia asistencia, puntualidad, frascos, comida, pociones, utilidades y cualquier aportación a las raids. Todo queda registrado y es reversible.</p>

<div class="admin-card" id="rsp-addon-sync" style="margin:18px 0;padding:22px">
  <div class="eyebrow">NEXUS RSP · ADDON Y SINCRONIZACIÓN</div>
  <h2 style="margin-top:5px">Sincronizar los puntos entre WoW Forever y la web</h2>
  <p class="muted">Toda la gestión del intercambio está reunida aquí, dentro de RSP. La web sigue siendo el registro oficial de los saldos. La sincronización actual es manual mediante archivos; no hay conexión HTTP directa desde el addon.</p>
  <div class="rsp-sync-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:16px">
    <section style="border:1px solid rgba(180,180,180,.25);border-radius:12px;padding:16px">
      <h3>🎮 Juego → web</h3>
      <ol style="padding-left:20px;line-height:1.7">
        <li>En el addon, pulsa <strong>Exportar JSON</strong> y elige <strong>JSON movimientos</strong>.</li>
        <li>Copia el contenido y guárdalo como un archivo <code>.json</code>.</li>
        <li>Pulsa el botón de abajo para importar el archivo aquí mismo dentro de la sección RSP.</li>
        <li>Revisa la vista previa y confirma. Los duplicados se omiten por identificador.</li>
      </ol>
      <a class="btn primary" href="/Admin/rsp_import.php">📥 Importar movimientos JSON del addon</a>
    </section>
    <section style="border:1px solid rgba(180,180,180,.25);border-radius:12px;padding:16px">
      <h3>🌐 Web → juego</h3>
      <ol style="padding-left:20px;line-height:1.7">
        <li>Pulsa <strong>Descargar saldos JSON</strong> para obtener los saldos oficiales de esta facción.</li>
        <li>En el addon, pulsa <strong>Importar JSON</strong> o escribe <code>/rsp import</code>.</li>
        <li>Abre el JSON descargado, copia todo su contenido y pégalo en el cuadro del addon.</li>
        <li>Confirma para actualizar los saldos locales; no se crean movimientos nuevos.</li>
      </ol>
      <a class="btn" href="/Admin/rsp.php?download=json">⬇ Descargar saldos JSON</a>
      <p class="muted small" style="margin-top:10px">Se exporta un personaje principal por cuenta de Discord para evitar duplicar el mismo saldo en alters. Facción: <?=e(nexus_faction_label())?>.</p>
    </section>
  </div>
  <div class="notice" style="margin-top:16px">
    <strong>Importante antes de sincronizar</strong>
    <ul style="margin-bottom:0;line-height:1.7">
      <li>Haz una copia de seguridad de los datos guardados del addon antes de importar.</li>
      <li>Todo el intercambio usa JSON: saldos para descargar de la web y movimientos para subir desde el addon.</li>
      <li>La importación de movimientos JSON añade registros al historial web después de la vista previa y aplica las reglas/límites RSP configurados.</li>
      <li>La web calcula los puntos por cuenta Discord, mientras que el addon guarda saldos por personaje. Comprueba los nombres y evita usar alters como si fueran cuentas independientes.</li>
      <li>Tras importar desde el juego, revisa el ranking RSP de esta página para confirmar el resultado.</li>
    </ul>
  </div>
</div>
<div class="rsp-toolbar">
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="sync_ranking"><button class="btn primary" type="submit">🔄 Sincronizar ranking RSP con Discord</button></form>
<form class="rsp-target-form" method="post" onsubmit="return confirm('¿Aplicar el saldo indicado únicamente al jugador seleccionado?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_player_balance"><label>Jugador <select name="discord_id" required><option value="">Selecciona un jugador…</option><?php foreach($balanceTargets as $targetPlayer): ?><option value="<?=e($targetPlayer['discord_id'])?>"><?=e($targetPlayer['name'])?> · <?=e((int)$targetPlayer['balance'])?> RSP</option><?php endforeach; ?></select></label><label>Saldo RSP <input type="number" name="target_balance" min="0" value="0" required></label><button class="btn ghost" type="submit">🎯 Aplicar saldo</button></form>

</div>

<?php if ($msg): ?><div class="flash"><?=e($msg)?></div><?php endif; ?>
<?php if ($error): ?><div class="flash" style="border-color:#a33"><?=e($error)?></div><?php endif; ?>

<div class="admin-grid">
    <div class="admin-card">
        <h2>Aplicar RSP a una raid</h2>
        <form class="admin-form" method="get">
            <label>Raid / evento
                <select name="event_id" onchange="this.form.submit()">
                    <?php foreach($events as $ev): ?>
                    <option value="<?=$ev['id']?>" <?=$selectedEvent===$ev['id']?'selected':''?>>
                        <?=e($ev['event_date'].' · '.$ev['title'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <form class="admin-form" method="post" onsubmit="return confirm('¿Aplicar estos RSP a los personajes seleccionados?');">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="award_bulk">
            <input type="hidden" name="event_id" value="<?=$selectedEvent?>">
            <fieldset class="rsp-rule-fieldset">
                <legend>Conceptos RSP <span class="muted small">Puedes elegir varios</span></legend>
                <?php
                $rspGroups = [
                    'raid' => ['title' => '🏰 Raid y preparación', 'codes' => ['raid_attendance','raid_ontime','full_consumables','flask','food','potions','oil_sharpening','materials','extra_help']],
                    'community' => ['title' => '🎓 Comunidad y ayuda', 'codes' => ['mentor','guide']],
                    'pvp' => ['title' => '⚔️ PvP', 'codes' => ['pvp_premade','pvp_bg_group']],
                ];
                $rulesByCode = []; foreach ($rules as $rule) { if (!empty($rule['active'])) $rulesByCode[$rule['code']] = $rule; }
                foreach ($rspGroups as $group): ?>
                    <div class="rsp-rule-group">
                        <h3><?=e($group['title'])?></h3>
                        <div class="rsp-rule-grid">
                        <?php foreach ($group['codes'] as $code): if (!isset($rulesByCode[$code])) continue; $rule=$rulesByCode[$code]; ?>
                            <label class="rsp-rule-tile">
                                <input type="checkbox" name="rule_codes[]" value="<?=e($rule['code'])?>" data-amount="<?=e((int)$rule['amount'])?>" class="rsp-rule-check">
                                <span class="rsp-rule-tick" aria-hidden="true">✓</span>
                                <span class="rsp-rule-copy"><strong><?=e($rule['label'])?></strong><small>+<?=e((int)$rule['amount'])?> RSP</small></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="rsp-rule-summary" id="rspRuleSummary"><strong>0</strong> conceptos seleccionados <span>·</span> <strong>0 RSP</strong> por personaje</div>
            </fieldset>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
                <button type="button" class="btn ghost" onclick="toggleAll(true)">Seleccionar todos</button>
                <button type="button" class="btn ghost" onclick="toggleAll(false)">Quitar todos</button>
            </div>
            <div class="rsp-character-grid">
            <?php if(!$attendees): ?><p class="muted">No hay inscritos en esta raid.</p><?php endif; ?>
            <?php foreach($attendees as $a): $classKey = mb_strtolower((string)($a['class_name'] ?? ''),'UTF-8'); $classSlug = preg_replace('/[^a-z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$classKey) ?: ''); ?>
                <label class="rsp-character-card <?=((int)$a['attended']===1?'is-attended':'')?>">
                    <input class="rsp-member" type="checkbox" name="members[]" value="<?=e($a['member_discord_id'])?>" <?=((int)$a['attended']===1?'checked':'')?> aria-label="Seleccionar <?=e($a['member_username'])?>">
                    <span class="rsp-character-mark" aria-hidden="true">✦</span>
                    <span class="rsp-character-info"><strong><?=e($a['member_username'])?></strong><small><?=e($a['race']?:'Raza no indicada')?> · <?=e($a['class_name']?:'Clase no indicada')?></small><small><?=((int)$a['attended']===1?'✓ Asistió':'◷ Pendiente de asistencia')?></small></span>
                    <span class="rsp-character-balance"><strong><?=e((int)$a['balance'])?></strong><small>RSP</small></span>
                </label>
            <?php endforeach; ?>
            </div>
            <p class="muted small">PvP Premade: +20 RSP. BG en grupo: +5 RSP y requiere seleccionar de 3 a 5 miembros. Las actividades PvP pueden registrarse sin crear un evento de calendario.</p>
            <button class="btn primary" type="submit">Aplicar RSP</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>Movimiento manual</h2>
        <p class="muted">Úsalo para bonos, penalizaciones o correcciones. Para quitar RSP introduce una cantidad negativa.</p>
        <form class="admin-form" method="post">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="manual">
            <label>Personaje <select name="character_id" required><option value="">Selecciona un personaje…</option><?php
                $manualCharactersStmt=$pdo->prepare("SELECT mp.id,mp.name,mp.race,mp.class_name,mp.discord_id,m.discord_username
                    FROM member_profiles mp
                    LEFT JOIN members m ON m.discord_id=mp.discord_id
                    WHERE mp.faction=? AND mp.discord_id IS NOT NULL AND mp.discord_id<>''
                    ORDER BY COALESCE(m.discord_username,mp.discord_id) ASC,mp.name ASC");
                $manualCharactersStmt->execute([nexus_faction()]);
                $manualCharacters = $manualCharactersStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($manualCharacters as $character):
                    ?><option value="<?=e($character['id'])?>"><?=e($character['name'])?> · <?=e($character['race']?:'Raza no indicada')?> <?=e($character['class_name']?:'Clase no indicada')?> · <?=e($character['discord_username'] ?: 'Miembro') ?> (Discord <?=e($character['discord_id'])?>)</option><?php endforeach; ?><?php if (!$manualCharacters): ?><option value="" disabled>No hay personajes registrados para esta facción. Comprueba Miembros y la facción elegida.</option><?php endif; ?></select></label>
            <label>RSP <input type="number" name="amount" required placeholder="10"></label>
            <label>Motivo <input name="reason" maxlength="255" required placeholder="Ayuda con materiales / corrección / penalización"></label>
            <label>Evento (opcional)
                <select name="event_id">
                    <option value="0">Sin evento</option>
                    <?php foreach($events as $ev): ?><option value="<?=$ev['id']?>"><?=e($ev['event_date'].' · '.$ev['title'])?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="btn primary" type="submit">Registrar movimiento</button>
        </form>

        <h2 style="margin-top:30px">Reglas RSP</h2>
        <div style="overflow:auto">
        <table class="admin-table">
            <thead><tr><th>Concepto</th><th>RSP</th><th>Activo</th><th></th></tr></thead>
            <tbody>
            <?php foreach($rules as $rule): ?>
            <tr><form method="post">
                <td><?=e($rule['label'])?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_rule"><input type="hidden" name="id" value="<?=$rule['id']?>"></td>
                <td><input type="number" name="amount" value="<?=e($rule['amount'])?>" style="width:80px"></td>
                <td><input type="checkbox" name="active" value="1" <?=$rule['active']?'checked':''?>></td>
                <td><button class="btn ghost" type="submit">Guardar</button></td>
            </form></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top:18px">
<h2>Ranking RSP</h2>
<div style="overflow:auto"><table class="admin-table">
<thead><tr><th>#</th><th>Personaje</th><th>Raza</th><th>Clase</th><th>RSP</th><th>Discord ID</th><th>Acción</th></tr></thead>
<tbody>
<?php foreach($leaders as $i=>$l): ?>
<tr><td><?=$i+1?></td><td><strong><?=e($l['name'])?></strong></td><td><?=e($l['race'])?></td><td><?=e($l['class_name'])?></td><td><strong><?=e((int)$l['balance'])?></strong></td><td><?=e($l['discord_id']?:'—')?></td><td><?php if(!empty($l['discord_id'])): ?><form class="rsp-inline-target" method="post" onsubmit="return confirm('¿Cambiar el saldo de <?=e($l['name'])?> al valor indicado?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_player_balance"><input type="hidden" name="discord_id" value="<?=e($l['discord_id'])?>"><input type="number" name="target_balance" min="0" value="<?=e((int)$l['balance'])?>" aria-label="Saldo RSP deseado para <?=e($l['name'])?>"><button class="btn ghost" type="submit">Guardar saldo</button></form><?php else: ?><span class="muted">Sin Discord vinculado</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<div class="admin-card" style="margin-top:18px">
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px">
<h2 style="margin:0">Historial RSP</h2>
<form method="post" onsubmit="return confirm('¿Limpiar el historial detallado? Se borrarán los movimientos antiguos y se conservará el saldo actual de cada personaje como saldo inicial. Esta acción no se puede deshacer.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="clear_history"><button class="btn danger" type="submit">🧹 Limpiar historial RSP</button></form>
</div>
<div style="overflow:auto"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Jugador</th><th>Movimiento</th><th>Total</th><th>Motivo</th><th>Raid</th><th>Admin</th></tr></thead>
<tbody>
<?php foreach($ledger as $row): ?>
<tr>
<td><?=e($row['created_at'])?></td>
<td><?=e($row['character_name'] ?: $row['member_discord_id'])?></td>
<td><strong><?=((int)$row['amount']>0?'+':'').e((int)$row['amount'])?></strong></td>
<td><strong><?=e((int)$row['balance_after'])?> RSP</strong></td>
<td><?=e($row['reason'])?></td>
<td><?=e($row['event_title']?:'—')?></td>
<td><?=e($row['admin_user']?:'—')?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<script>
function toggleAll(value){ document.querySelectorAll('.rsp-member').forEach(x=>x.checked=value); }
(function(){
 const checks=[...document.querySelectorAll('.rsp-rule-check')];
 const summary=document.getElementById('rspRuleSummary');
 const update=()=>{const selected=checks.filter(x=>x.checked);const total=selected.reduce((sum,x)=>sum+(parseInt(x.dataset.amount||'0',10)||0),0);if(summary)summary.innerHTML='<strong>'+selected.length+'</strong> concepto(s) seleccionado(s) <span>·</span> <strong>'+total+' RSP</strong> por personaje';};
 checks.forEach(x=>x.addEventListener('change',update)); update();
 const form=document.querySelector('form input[name="action"][value="award_bulk"]')?.closest('form');
 if(form)form.addEventListener('submit',e=>{if(!checks.some(x=>x.checked)){e.preventDefault();alert('Selecciona al menos un concepto RSP.');}});
})();
</script>

<?php admin_footer(); ?>
