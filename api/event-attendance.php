<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
start_secure_session();

if(empty($_SESSION['admin_user'])) json_response(['error'=>'No autorizado'],401);
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['error'=>'Método no permitido'],405);

if (!csrf_check($_POST['csrf'] ?? null)) json_response(['error'=>'Recarga la página: token de sesión no válido.'],403);
$pdo = null;
$eventId=(int)($_POST['event_id']??0);
$discordId=trim((string)($_POST['discord_id']??''));
$attended=(int)($_POST['attended']??0);

if($eventId<1 || $discordId==='') json_response(['error'=>'Datos no válidos'],400);

try {
    $pdo=db();
    $pdo->beginTransaction();

    $before=$pdo->prepare("SELECT attended,member_username FROM event_attendees WHERE event_id=? AND member_discord_id=? LIMIT 1");
    $before->execute([$eventId,$discordId]);
    $attendee=$before->fetch(PDO::FETCH_ASSOC);
    if(!$attendee) {
        $pdo->rollBack();
        json_response(['error'=>'Participante no encontrado'],404);
    }

    $q=$pdo->prepare("UPDATE event_attendees SET attended=?,attendance_source='manual',attendance_at=NOW() WHERE event_id=? AND member_discord_id=?");
    $q->execute([$attended?1:0,$eventId,$discordId]);

    $awarded=0;
    $rule=$pdo->query("SELECT amount FROM dkp_rules WHERE code='raid_attendance' AND active=1 LIMIT 1")->fetchColumn();
    $rule=(int)($rule?:10);

    $st=$pdo->prepare("SELECT id,name FROM roster WHERE discord_id=? AND status='active' ORDER BY id LIMIT 1");
    $st->execute([$discordId]);
    $roster=$st->fetch(PDO::FETCH_ASSOC);

    $netStmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE event_id=? AND member_discord_id=? AND source_type IN ('attendance','attendance_reversal')");
    $netStmt->execute([$eventId,$discordId]);
    $attendanceNet=(int)$netStmt->fetchColumn();

    $ins=$pdo->prepare("INSERT INTO dkp_ledger
        (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user)
        VALUES (?,?,?,?,?,?,?,?,?,?)");

    if($attended && $attendanceNet <= 0) {
        $weeklyEarned = rsp_weekly_earned($pdo, $discordId);
        $currentBalanceStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=?');
        $currentBalanceStmt->execute([$discordId]);
        $currentBalance = (int)$currentBalanceStmt->fetchColumn();
        // La asistencia se registra siempre. Si el jugador ha alcanzado el límite de RSP,
        // simplemente no se concede RSP adicional.
        $awardAmount = min($rule, max(0, rsp_weekly_cap() - $weeklyEarned), max(0, 100 - $currentBalance));
        if ($awardAmount > 0) {
            $ins->execute([
            $discordId,
            $roster['id']??null,
            $roster['name']??($attendee['member_username']??null),
            $eventId,
            $awardAmount,
            'raid_attendance',
            'Asistencia a raid',
            'attendance',
            'attendance:'.$eventId.':'.$discordId.':'.bin2hex(random_bytes(6)),
            (string)($_SESSION['admin_user']??'admin')
            ]);
            $awarded=$awardAmount;
        }
    } elseif(!$attended && (int)$attendee['attended'] === 1 && $attendanceNet > 0) {
        $ins->execute([
            $discordId,
            $roster['id']??null,
            $roster['name']??($attendee['member_username']??null),
            $eventId,
            -$attendanceNet,
            'raid_attendance',
            'Corrección: asistencia anulada',
            'attendance_reversal',
            'attendance_reversal:'.$eventId.':'.$discordId.':'.bin2hex(random_bytes(6)),
            (string)($_SESSION['admin_user']??'admin')
        ]);
        $awarded=-$attendanceNet;
    }

    $pdo->commit();
    if ($awarded !== 0) {
        try {
            $ev=$pdo->prepare('SELECT title FROM events WHERE id=? LIMIT 1');
            $ev->execute([$eventId]);
            $title=(string)($ev->fetchColumn() ?: 'Evento NEXUS');
            rsp_publish_discord_award($pdo,$eventId,$title,$attended?'Asistencia a evento':'Corrección de asistencia',$awarded,[$discordId],1);
        } catch(Throwable $discordError) { /* La asistencia/RSP no se revierte si Discord falla. */ }
    }
    json_response(['ok'=>true,'attended'=>$attended?1:0,'rsp_awarded'=>$awarded,'dkp_awarded'=>$awarded]);
} catch(Throwable $e) {
    if($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['error'=>$e->getMessage()],500);
}
