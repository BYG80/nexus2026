<?php $awardIcons=nexus_award_icons(); ?>
<?php if($memberAwards): ?>
<div class="member-awards-scroll" role="region" aria-label="Tabla de reconocimientos" tabindex="0">
<table class="member-awards-table">
    <caption class="member-awards-caption">Reconocimientos otorgados por la hermandad NEXUS</caption>
    <thead><tr><th scope="col">Hazaña</th><th scope="col">Personaje</th><th scope="col">Fecha</th></tr></thead>
    <tbody><?php foreach($memberAwards as $award): ?>
        <tr><td><div class="member-award-title"><span class="member-award-icon" aria-hidden="true"><?=e(($awardIcons[$award['icon']]??$awardIcons['trophy'])['symbol'])?></span><div><strong><?=e($award['title'])?></strong><span class="member-award-source"><?=$award['source']==='weekly'?'Jugador de la semana':'Reconocimiento de NEXUS'?></span></div></div><?php if($award['description']): ?><p class="member-award-description"><?=e($award['description'])?></p><?php endif; ?></td>
        <td><?=e($award['character_name'])?></td><td><time datetime="<?=e((new DateTimeImmutable($award['awarded_at'],new DateTimeZone('UTC')))->format('c'))?>"><?=e((new DateTimeImmutable($award['awarded_at'],new DateTimeZone('UTC')))->setTimezone(app_timezone())->format('d/m/Y'))?></time></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php else: ?><p class="muted">Todavía no hay reconocimientos otorgados por la hermandad.</p><?php endif; ?>
