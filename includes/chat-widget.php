<?php
require_once __DIR__ . '/guild-info.php';
$guildChatInfo = nexus_guild_info();
?>
<link rel="stylesheet" href="/css/chat.css?v=<?=filemtime(NEXUS_ROOT . '/css/chat.css')?>">
<button class="nexus-chat-launcher" id="nexus-chat-launcher" type="button" aria-controls="nexus-chat-panel" aria-expanded="false">
    <span aria-hidden="true">✦</span> Chat NEXUS
</button>
<section class="nexus-chat-panel" id="nexus-chat-panel" role="dialog" aria-modal="false" aria-labelledby="nexus-chat-title" hidden>
    <header class="nexus-chat-header">
        <div><h2 id="nexus-chat-title">Asistente de NEXUS</h2><p>Hermandad de la <?=e(nexus_faction_label())?></p></div>
        <button id="nexus-chat-close" type="button" aria-label="Cerrar chat">×</button>
    </header>
    <div class="nexus-chat-content">
        <div class="nexus-chat-info">
            <p>Discord, raids, reclutamiento y vida de la hermandad.</p>
            <p><strong>Raids:</strong> <?=e($guildChatInfo['raid_schedule'] ?: 'Horarios oficiales por confirmar. Consulta Discord o el calendario.')?><?php if ($guildChatInfo['raid_schedule']): ?> · <?=e($guildChatInfo['raid_timezone'])?><?php endif; ?></p>
            <div class="nexus-chat-links"><a href="<?=e($guildChatInfo['discord_invite'])?>" target="_blank" rel="noopener noreferrer">Entrar en Discord ↗</a><a href="/calendario">Calendario</a><a href="/reclutamiento">Unirse</a></div>
        </div>
        <div class="nexus-chat-messages" id="nexus-chat-messages" role="log" aria-label="Conversación con el asistente" aria-live="polite" aria-relevant="additions"></div>
        <p class="nexus-chat-status" id="nexus-chat-status" role="status">Abre el chat para consultar al asistente.</p>
        <div class="nexus-chat-suggestions" id="nexus-chat-suggestions" hidden>
            <button type="button" data-chat-question="¿Cuál es el Discord de NEXUS?">Discord</button>
            <button type="button" data-chat-question="¿Qué días y a qué hora son las raids?">Horarios de raid</button>
            <button type="button" data-chat-question="¿Cómo puedo unirme a la hermandad?">Cómo unirme</button>
        </div>
    </div>
    <form class="nexus-chat-form" id="nexus-chat-form">
        <label class="nexus-chat-sr" for="nexus-chat-input">Tu pregunta sobre NEXUS</label>
        <textarea id="nexus-chat-input" name="message" rows="2" maxlength="1200" placeholder="Pregunta sobre la hermandad…" aria-describedby="nexus-chat-notice" required disabled></textarea>
        <div class="nexus-chat-actions"><button id="nexus-chat-clear" type="button" disabled>Borrar conversación</button><button id="nexus-chat-send" type="submit" disabled>Enviar</button></div>
        <p id="nexus-chat-notice">Al enviar, tu mensaje y el contexto reciente se comparten con OpenAI para generar una respuesta. La IA puede equivocarse. <a href="/privacidad#asistente-ia">Privacidad</a></p>
    </form>
</section>
<script src="/js/chat.js?v=<?=filemtime(NEXUS_ROOT . '/js/chat.js')?>" defer></script>
