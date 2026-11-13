# SEO, asistente IA, Discord y armería

## Actualizar la web

Sube el contenido del ZIP a la raíz del alojamiento, incluidos `.htaccess` y las nuevas carpetas. Conserva tu `config/config.php` privado: no está en GitHub. Esta actualización añade funciones; sustituir solo el index o el CSS no es suficiente.

Requisitos: PHP 8.1 o posterior, PDO MySQL, cURL, mbstring, DOM/XML y fileinfo; MySQL/MariaDB y permiso de escritura para imágenes y sesiones. La caché de armería y las cuotas de IA usan el directorio temporal del servidor. En varios servidores, usa una cuota compartida en lugar de un directorio temporal por máquina.

La aplicación ya utiliza `nexus_settings`. Las tablas adicionales `discord_media_publications` y `armory_characters` se crean desde sus pantallas administrativas cuando hay permiso CREATE. Si el usuario MySQL no lo tiene, ejecuta una sola vez `MIGRACION_IA_DISCORD_ARMERIA.sql` en la base existente. No importes `database.sql` sobre una instalación existente.

## Qué cambia en SEO

- Títulos, descripciones, canonical, Open Graph, Twitter y JSON-LD salen de `includes/seo.php`, con una sola definición por página.
- URLs públicas coherentes sin `.php`; Apache redirige las variantes y unifica HTTPS y el dominio `www` en producción. Los POST no se redirigen al quitar la extensión.
- Las tres páginas casi duplicadas de WoW Forever/facciones redirigen a `/hermandad-wow-forever`, que reúne información útil sobre comunidad, Discord y actividades.
- Sitemap dinámico en `/sitemap.php`, con fechas de modificación de los archivos y solo páginas públicas indexables. `/sitemap.xml` se sirve mediante la misma fuente en Apache. Retira la copia antigua de `sitemap.xml` en Nginx o configura su redirección.
- Panel, miembros y administración llevan noindex; los recursos internos se excluyen del rastreo. `robots.txt` no sustituye la autenticación ni las restricciones HTTP.
- Datos estructurados WebSite, Organization, WebPage y rutas de navegación, sin reseñas, premios ni eventos inventados.

Después de instalar: abre Google Search Console, verifica `https://www.nexus-forever.es/`, envía `https://www.nexus-forever.es/sitemap.php` y solicita indexación de inicio, hermandad y reclutamiento. Puedes verificar por DNS o añadir el token de verificación a `google_site_verification` en la configuración privada. Revisa páginas y rendimiento allí después del rastreo. Se mejora la base técnica; no se garantiza una posición ni un plazo de indexación.

En Nginx necesitas reglas equivalentes: `try_files $uri $uri.php =404` para las URLs públicas, redirecciones de variantes, HTTPS y dominio, y bloqueo de `/config/`, `/datos/`, `/includes/`, `/tools/`, `/tests/`, archivos SQL, copias y secretos. Conserva el manejador PHP existente de tu proveedor; no sirvas archivos PHP como texto.

## Asistente IA de la hermandad

El chat usa la API de OpenAI Responses, con `gpt-4.1-mini` como modelo predeterminado configurable. No está conectado a esta conversación de ChatGPT. Para activarlo, configura `OPENAI_API_KEY` en el entorno del servidor o añade `openai_api_key` a tu `config/config.php` privado. Nunca pongas una clave real en GitHub, JavaScript o un formulario público. La API tiene consumo facturable independiente de ChatGPT.

Configuración opcional del archivo privado:

```php
'ai_chat_enabled' => true,
'openai_api_key' => '', // Mejor usar la variable de entorno OPENAI_API_KEY.
'openai_model' => 'gpt-4.1-mini',
'ai_chat_daily_limit' => 100,
'ai_chat_ip_hourly_limit' => 30,
```

Sin clave o sin cURL, el widget muestra información y enlaces reales de NEXUS y explica que la IA aún no está activa. No finge respuestas generadas.

En **Admin → Asistente IA** puedes actualizar la invitación `https://discord.gg/5TuTMhFtD`, los días y horas oficiales de raid, zona horaria, nombre del canal de voz, reclutamiento y otras notas públicas. Los horarios regulares siguen pendientes de confirmar: se dejan vacíos hasta que los complete la administración. El chat no los deduce de eventos aislados. La facción procede de la configuración global de la web, actualmente Horda.

El contexto usa solo esos datos, las normas públicas, noticias publicadas y cinco eventos de los próximos 30 días. No incluye solicitudes, contraseñas ni mensajes de Discord. La IA solo informa: no modifica datos ni inscribe jugadores.

Protecciones: CSRF, entradas de hasta 1200 caracteres, historial de sesión acotado, límite de salida, 8 preguntas por sesión/10 minutos, 30 por IP/hora y 100 para toda la web/día. Los contadores diarios usan UTC y se guardan con bloqueo de archivo fuera del directorio público. Configura también presupuestos en el proyecto de OpenAI; estas cuotas limitan peticiones y no representan un límite monetario exacto.

Se solicita `store: false`. El histórico reciente se utiliza durante 30 minutos de actividad y puede borrarse desde el chat; la eliminación de las sesiones en disco depende del alojamiento. Revisa la sección de privacidad añadida y completa los datos reales del responsable antes de activar el servicio.

## Eventos y Discord

Al crear o editar un evento desde Admin o la API, se intentan independientemente:

1. Crear o actualizar el evento programado, visible en **Eventos** de Discord.
2. Publicar o actualizar el aviso del canal de eventos.

Ya no depende de una casilla inicialmente desmarcada ni de la primera inscripción. El evento web queda guardado si alguna publicación falla. En Admin se muestra el fallo y puedes pulsar **Sincronizar**, incluso si aún no existe un ID remoto.

Configura `discord_bot_token`, `discord_guild_id` y `discord_event_channel_id` en el archivo privado. Con `discord_voice_channel_id` se crea un evento de voz; sin él se crea un evento externo con ubicación «World of Warcraft Forever — NEXUS». La duración predeterminada es 120 minutos y puede cambiarse con `discord_event_duration_minutes`. Debes indicar una hora y una fecha futura para programarlo en Discord. Se convierten desde `app_timezone` a UTC.

El bot necesita **Crear eventos** y los permisos correspondientes sobre el canal de voz, si se utiliza. Para editar o retirar eventos ajenos puede necesitar **Gestionar eventos**. Para avisos necesita **Ver canal**, **Enviar mensajes** e **Insertar enlaces**. Una prueba de lectura correcta no demuestra permisos de escritura; la pantalla de diagnóstico lo indica expresamente.

Se guardan ID y URL remotos antes de otras tareas. Bloqueos MySQL evitan creaciones concurrentes. Ante 403, 429 o fallos temporales no se vuelve a crear a ciegas un mensaje existente; los 404 sí permiten recuperar contenidos borrados. Un enlace al evento web ayuda a recuperar eventos programados cuya respuesta de creación se perdió.

Los interesados se leen por páginas y se actualizan por separado de las inscripciones web. Retirar el interés en Discord no borra una inscripción hecha desde la web ni el historial de asistencia. Una inscripción web no puede marcar unilateralmente «Me interesa» en Discord: ese estado lo controla la cuenta del usuario en Discord.

Al eliminar un evento se intentan retirar primero sus publicaciones remotas. Si Discord falla, el registro web se conserva para poder reintentar. Los eventos ya terminados/cancelados no se pueden reprogramar como si fueran nuevos: crea un evento nuevo para otra fecha.

## Galería → Discord

La subida de imágenes tiene **Publicar automáticamente en Discord** activado por defecto. Se guarda primero la imagen y después se publica un mensaje con su título, descripción y vista previa. Si Discord falla, la imagen sigue disponible y la publicación queda pendiente.

Configura `discord_gallery_channel_id` para usar un canal dedicado. Si está vacío, se usa `discord_event_channel_id`. Las imágenes se muestran mediante su URL HTTPS pública; Discord necesita poder acceder a `/img/uploads/`. El bot necesita Insertar enlaces además de Enviar mensajes. No se publica un archivo privado ni se envía un mensaje de prueba durante la instalación.

En Admin → Multimedia puedes publicar/reintentar imágenes antiguas, actualizar su mensaje vinculado y reintentar retiradas pendientes. Borrar la imagen en la web intenta retirar también su mensaje de Discord. No se vuelven a publicar automáticamente todas las imágenes antiguas al actualizar el código.

La tabla de publicaciones conserva los ID remotos, errores y número de intentos. Los reintentos reutilizan mensajes vinculados y un identificador de Discord ayuda a evitar duplicados recientes. Si una petición terminó en Discord pero se perdió la respuesta y el mensaje ya no está entre los cien últimos, puede hacer falta reconciliarlo manualmente antes de reintentar.

## Otras mejoras de Discord

- Transporte común con tiempos máximos, errores interpretables y tokens ocultos. No se reintentan automáticamente POST de resultado incierto.
- Las menciones masivas quedan desactivadas por defecto; el panel de administración conserva su opción explícita de mencionar a todos.
- OAuth usa estado con caducidad, retorno local validado, scope `identify` sin pedir el correo, tiempos máximos y regeneración de sesión. No altera una sesión administrativa existente.
- Si configuras `discord_member_role_id`, se exige ese ID; un rol con el mismo nombre no permite saltárselo. Al perder el rol se retira el acceso de miembro sin destruir la sesión administrativa.
- Inscripciones, asistencia y sincronización llevan CSRF. Se corrige la anulación de asistencia para descontar los RSP realmente concedidos, incluso si el límite semanal redujo la recompensa inicial.
- RSP, profesiones, pedidos y mensajes administrativos utilizan el transporte común. Sus reglas y permisos funcionales se conservan.
- Admin → Integraciones Discord permite comprobar servidor y canales sin publicar contenido.

Para actualizar interesados y reintentar contenidos pendientes cada cinco minutos, configura cron en tu alojamiento:

```sh
*/5 * * * * /usr/bin/php /RUTA/REAL/DE/LA/WEB/tools/sync-discord.php >> /RUTA/PRIVADA/nexus-discord-cron.log 2>&1
```

Usa las rutas reales y una carpeta de logs fuera de la web. El script solo funciona desde CLI, evita ejecuciones simultáneas y procesa lotes limitados. Tras diez intentos automáticos de una imagen, revisa el error y reintenta desde el panel. El cron no sustituye un bot conectado al Gateway para sincronización instantánea.

## Armería

Página `/armeria`, enlazada en el menú. Región EU. Se ha comprobado la página oficial pública de **Melkir Vossgrave**: Horda y hermandad **NexusForever**. Se muestran objetos equipados, nivel, atributos y encantamientos proporcionados por esa fuente; no se inventa equipamiento.

Esta integración lee el JSON incluido en el HTML público de Blizzard, sin ejecutarlo, y no necesita contraseña de Battle.net ni una clave de API. Ese formato no es un contrato estable: si Blizzard lo cambia o restringe el acceso, se muestra un error y el enlace oficial. No se garantiza actualización instantánea del equipo al cambiarlo en el juego.

Solo se incluyen personajes de miembros registrados en la web cuyo registro de Discord esté verificado como miembro; no se importa el roster completo de Blizzard. Los personajes sin apellido se muestran como pendientes. En **Admin → Armería** puedes vincular un nombre y apellido oficial a uno de esos personajes registrados; no se pueden añadir personajes ajenos a la web. Cada perfil se comprueba contra la Horda y `armory_guild_slug`, cuyo valor inicial es `nexusforever`. No se accede a URLs arbitrarias proporcionadas por visitantes.

Hay caché de diez minutos y un máximo de una comprobación cada dos minutos por personaje. Si Blizzard falla, puede mostrarse la última copia de hasta 24 horas, indicada como desactualizada. Solo se aceptan imágenes del dominio de renderizado de Blizzard.

## Jugador de la semana

En **Admin → Jugador de la semana** escribe el nombre y apellido de un personaje, añade la hazaña o título que tú decidas, un detalle opcional y elige el icono. Al publicar, aparece una sección inmediatamente debajo del carrusel del inicio, con la imagen del personaje y su reconocimiento, sin objetos ni estadísticas de la armería.

El nombre debe corresponder a un miembro verificado de nuestra web; se admiten los nombres completos, vinculaciones de Admin → Armería y nombres registrados con guion bajo. Si solo existe el nombre de pila, se acepta únicamente cuando identifica un solo perfil registrado, y se comprueba además el nombre completo en Blizzard. Las identidades ambiguas deben completarse en Mi panel o Admin → Armería.

Antes de guardar se comprueban identidad, Horda, NexusForever e imagen de cuerpo completo en la armería pública de WoW Forever EU. Si falla la consulta, el reconocimiento anterior se conserva. La portada usa esa imagen verificada sin consultar Blizzard en cada visita. Puedes volver a guardar para actualizarla.

El reconocimiento se mantiene hasta que lo cambies o pulses **Retirar reconocimiento**. No se elige otro jugador automáticamente al cambiar la semana. Si no hay selección, la sección permanece oculta; si el perfil deja de ser un miembro verificado en la web, también se oculta. No se necesita una nueva tabla ni migración: se usa `nexus_settings`.

## Validación y límites

Las pruebas de integración usan una base MariaDB local de pruebas y respuestas simuladas de Discord/OpenAI: no publican mensajes reales. Se comprueban creación/reintento de eventos, independencia del aviso de canal, imágenes sin duplicados vinculados, cuotas, errores HTTP, redirecciones, extracción de equipo y respuestas de IA. `tests/regression.php` exige una base terminada en `_test`.

No se ha probado con tus tokens reales, permisos del bot, facturación de OpenAI ni configuración del alojamiento. Después de instalar, comprueba una subida y un evento futuro de verdad, revisa sus estados en Admin y confirma los permisos en Discord. El SEO y la armería se verifican también con respuestas públicas y navegador; los cambios se entregan en GitHub, no se despliegan automáticamente en tu servidor.

Fuentes técnicas: [Google Search Central](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [OpenAI Responses](https://developers.openai.com/api/reference/resources/responses/methods/create), [eventos programados de Discord](https://docs.discord.com/developers/resources/guild-scheduled-event) y [perfil público de Melkir Vossgrave](https://worldofwarcraft.blizzard.com/es-es/forever/eu/armory/character/melkir-vossgrave).
