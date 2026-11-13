<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_admin();
$pdo = db();
$msg = ''; $error = ''; $preview = [];
function rsp_import_json(string $raw): array {
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['format'] ?? '') !== 'nexus-rsp-proposals' || (int)($data['version'] ?? 0) !== 1 || !isset($data['movements']) || !is_array($data['movements'])) {
        throw new RuntimeException('Formato no válido. Debe ser una exportación NEXUS RSP (nexus-rsp-proposals, versión 1).');
    }
    if (count($data['movements']) > 500) throw new RuntimeException('El archivo supera el máximo de 500 propuestas.');
    $out=[];
    foreach ($data['movements'] as $i=>$m) {
        if (!is_array($m)) throw new RuntimeException('Movimiento '.($i+1).' no válido.');
        $name=trim((string)($m['character'] ?? '')); $reason=trim((string)($m['reason'] ?? ''));
        $amount=filter_var($m['amount'] ?? null, FILTER_VALIDATE_INT);
        $id=trim((string)($m['id'] ?? ''));
        if ($name==='' || mb_strlen($name)>80 || $reason==='' || mb_strlen($reason)>255 || $amount===false || $amount===0 || $id==='') throw new RuntimeException('Datos incompletos o inválidos en la propuesta '.($i+1).'.');
        $out[]=['id'=>$id,'character'=>$name,'amount'=>(int)$amount,'reason'=>$reason,'createdBy'=>mb_substr((string)($m['createdBy'] ?? 'GuildArmory'),0,100)];
    }
    return $out;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    check_csrf($_POST['csrf'] ?? null);
    if (empty($_FILES['rsp_file']) || ($_FILES['rsp_file']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Selecciona el archivo JSON exportado desde GuildArmory.');
    if ((int)$_FILES['rsp_file']['size'] > 2*1024*1024) throw new RuntimeException('El archivo no puede superar 2 MB.');
    $raw=file_get_contents((string)$_FILES['rsp_file']['tmp_name']);
    if ($raw===false) throw new RuntimeException('No se pudo leer el archivo.');
    $rawHash=hash('sha256',$raw);
    $preview=rsp_import_json($raw);
    if (($_POST['confirm_import'] ?? '')==='yes') {
      if (empty($_SESSION['rsp_import_hash']) || !hash_equals((string)$_SESSION['rsp_import_hash'], $rawHash)) throw new RuntimeException('El archivo no coincide con la vista previa. Vuelve a analizarlo antes de confirmar.');
      $applied=0; $skipped=0; $issues=[]; $faction=nexus_faction();
      $pdo->beginTransaction();
      $member=$pdo->prepare("SELECT mp.discord_id,mp.name FROM member_profiles mp INNER JOIN members m ON m.discord_id=mp.discord_id WHERE mp.faction=? AND mp.discord_id IS NOT NULL AND mp.discord_id<>'' AND m.is_member=1 AND LOWER(REPLACE(TRIM(mp.name),'-','_'))=LOWER(REPLACE(TRIM(?),'-','_')) LIMIT 1");
      $balanceQ=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=?');
      $exists=$pdo->prepare('SELECT id FROM dkp_ledger WHERE source_key=? LIMIT 1');
      $weekly=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM dkp_ledger WHERE member_discord_id=? AND amount>0 AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)");
      $rosterQ=$pdo->prepare("SELECT id FROM roster WHERE discord_id=? AND name=? AND faction=? AND status='active' ORDER BY id DESC LIMIT 1");
      $ins=$pdo->prepare("INSERT INTO dkp_ledger (member_discord_id,roster_id,character_name,event_id,amount,category,reason,source_type,source_key,admin_user) VALUES (?,?,?,NULL,?,?,?,?,?,?)");
      foreach ($preview as $m) {
        $key='ga-rsp:'.hash('sha256',$m['id'].'|'.$m['character'].'|'.$m['amount'].'|'.$m['reason']);
        $exists->execute([$key]); if ($exists->fetchColumn()) { $skipped++; continue; }
        $member->execute([$faction,$m['character']]); $who=$member->fetch(PDO::FETCH_ASSOC);
        if (!$who) { $issues[]=$m['character'].': no coincide con un personaje de miembro activo de esta facción.'; $skipped++; continue; }
        $balanceQ->execute([$who['discord_id']]); $balance=(int)$balanceQ->fetchColumn();
        $amount=$m['amount'];
        if ($amount>0) { $weekly->execute([$who['discord_id']]); $earned=(int)$weekly->fetchColumn(); $amount=min($amount,max(0,rsp_weekly_cap()-$earned),max(0,100-$balance)); }
        else { $amount=max($amount,-$balance); }
        if ($amount===0) { $issues[]=$m['character'].': sin margen de saldo o límite semanal alcanzado.'; $skipped++; continue; }
        $rosterQ->execute([$who['discord_id'],$who['name'],$faction]); $rosterId=$rosterQ->fetchColumn();
        $ins->execute([$who['discord_id'],$rosterId ?: null,$who['name'],$amount,'addon_proposal',$m['reason'].' (GuildArmory: '.$m['createdBy'].')','addon_import',$key,(string)($_SESSION['admin_user'] ?? 'admin')]);
        $applied++;
      }
      $pdo->commit();
      try { sync_rsp_ranking_to_discord($pdo); } catch (Throwable $e) { $issues[]='No se pudo sincronizar Discord: '.$e->getMessage(); }
      $msg="Importación terminada: $applied movimiento(s) aplicados y $skipped omitido(s).";
      if ($issues) $msg.=' Detalles: '.implode(' | ',array_slice($issues,0,8));
      $preview=[]; unset($_SESSION['rsp_import_hash']);
    } else { $_SESSION['rsp_import_hash']=$rawHash; }
  } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
}
admin_header('Importar RSP desde GuildArmory','rsp');
?>
<main class="admin-main"><section class="admin-section">
<div class="eyebrow">NEXUS · RSP</div><h1>Importar propuestas de GuildArmory</h1>
<p>Sube el JSON exportado desde NEXUS RSP. Se mostrará una vista previa antes de registrar los movimientos. Para buscar personajes, se considera equivalente el separador guion (<code>-</code>) y guion bajo (<code>_</code>); no se modifican los nombres guardados. Solo se importan personajes asociados a miembros activos de la facción actual.</p>
<?php if($msg): ?><div class="notice success"><?=e($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="admin-form" style="max-width:760px">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label for="rsp_file">Archivo JSON de GuildArmory</label><input id="rsp_file" type="file" name="rsp_file" accept="application/json,.json" required>
<button type="submit" class="btn">Analizar archivo</button>
</form>
<?php if($preview): ?>
<h2>Vista previa (<?=count($preview)?> propuestas)</h2><p>Se aplicarán los límites semanales y el máximo de 100 RSP. Los duplicados se omitirán. Comprueba cuidadosamente los nombres y cantidades.</p>
<div style="overflow:auto"><table class="admin-table"><thead><tr><th>Personaje</th><th>RSP</th><th>Motivo</th><th>Registrado por</th></tr></thead><tbody><?php foreach($preview as $m): ?><tr><td><?=e($m['character'])?></td><td><?=e(($m['amount']>0?'+':'').$m['amount'])?></td><td><?=e($m['reason'])?></td><td><?=e($m['createdBy'])?></td></tr><?php endforeach; ?></tbody></table></div>
<form method="post" enctype="multipart/form-data" class="admin-form" style="margin-top:1rem"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Vuelve a seleccionar el mismo archivo para confirmar</label><input type="file" name="rsp_file" accept="application/json,.json" required><input type="hidden" name="confirm_import" value="yes"><button type="submit" class="btn" onclick="return confirm('¿Importar las propuestas RSP mostradas? Se aplicarán límites y se evitarán duplicados.');">Confirmar importación</button></form>
<?php endif; ?>
<hr><h2>Uso dentro del juego</h2><ul><li>En NEXUS RSP, pulsa <strong>Exportar JSON</strong> y selecciona <strong>JSON movimientos</strong>.</li><li>Copia el texto completo y guárdalo como archivo <code>.json</code>.</li><li>Vuelve a esta sección para analizarlo y confirmar la importación.</li></ul><p><strong>Importante:</strong> las propuestas no son una prueba automática de asistencia; un administrador debe revisarlas. La web es la fuente oficial de saldos.</p>
</section></main><?php admin_footer(); ?>
