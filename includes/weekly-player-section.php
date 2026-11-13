<?php if ($weeklyPlayer): $weeklyIcon = nexus_weekly_icons()[$weeklyPlayer['icon']]; ?>
<section class="weekly-player-section" aria-labelledby="weekly-player-title">
    <div class="container">
        <div class="weekly-player-heading">
            <div class="eyebrow">Reconocimiento NEXUS</div>
            <h2 class="section-title" id="weekly-player-title">Jugador de la semana</h2>
        </div>
        <article class="weekly-player-card">
            <div class="weekly-player-portrait">
                <img src="<?=e($weeklyPlayer['portrait'])?>" alt="Personaje de la semana: <?=e($weeklyPlayer['name'])?>" width="1600" height="1200" loading="lazy" decoding="async" referrerpolicy="no-referrer">
            </div>
            <div class="weekly-player-award">
                <span class="weekly-player-icon" aria-hidden="true"><?=e($weeklyIcon['symbol'])?></span>
                <div class="eyebrow">Hazaña destacada</div>
                <h3><?=e($weeklyPlayer['feat'] ?: 'Reconocimiento de la hermandad')?></h3>
                <?php if ($weeklyPlayer['description'] !== ''): ?><p><?=e($weeklyPlayer['description'])?></p><?php endif; ?>
            </div>
        </article>
    </div>
</section>
<?php endif; ?>
