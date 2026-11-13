# NEXUS — revisión del proyecto

## Cambios

- Carrusel en `index.php` con las dos fotografías existentes: reproducción automática cada 5,5 segundos, flechas, indicadores, pausa, teclado (flechas, Inicio y Fin) y deslizamiento táctil. Se pausa al enfocar, pasar el ratón o esconder la pestaña. Respeta la preferencia de movimiento reducido.
- Fotografías del inicio en WebP: 681.794 bytes frente a 1.126.754 bytes originales (39,5 % menos). Los JPG originales se conservan para las páginas que aún los utilizan. Dimensiones explícitas y decodificación asíncrona.
- Navegación nativa de PHP para evitar los fallos del router anterior: botones sin inicializar al regresar, scripts duplicados, estilos persistentes y problemas con el historial. Cada página ejecuta sus propias inicializaciones. La música guarda posición y preferencias; al cambiar de página debe reanudarse tras un gesto y puede haber una interrupción.
- Código común cargado una vez desde `includes/public-nav.php`, también en las páginas legales y el panel. Menú con Escape, estado accesible y marcado de la página activa.
- Separación de responsabilidades: `js/script.js` (interfaz), `js/music.js` (audio), `js/privacy.js` (privacidad), `js/home-slider.js` (portada); calendario y galería conservan sus módulos.
- El audio no se descarga ni decodifica durante la carga inicial. El almacenamiento bloqueado no impide iniciar el reproductor o los demás módulos.
- La galería cambia de imagen sin reconstruir filtros, miniaturas y controles. Sus atajos de teclado quedan limitados a la galería o al visor abierto.
- Consentimiento de YouTube separado del PHP; retirar la autorización descarga el iframe activo y vuelve a mostrar el aviso.
- Sesiones iniciadas antes del HTML en las páginas públicas. Corregida una variable PDO no definida en RSP cuando la conexión falla.
- Limpieza compartida de noticias en `includes/news-html.php`: etiquetas editoriales permitidas, eliminación de atributos arbitrarios y enlaces limitados a HTTP(S). Sin DOM, se muestra texto escapado. Resúmenes con entidades decodificadas antes de recortar.
- Corregida una coma ausente que invalidaba `datos/galeria.json`.
- `.htaccess` para URLs sin extensión, compresión cuando está disponible y bloqueo HTTP de volcados SQL, copias y datos internos.

## Publicación

1. Conserva una copia del alojamiento actual y su configuración.
2. Descomprime el ZIP y sube su contenido a la raíz de la web, incluyendo los archivos ocultos `.htaccess`. Conserva tu `config/config.php` de producción: se excluye de esta entrega pública. Para instalaciones nuevas, usa `config/config.php.example`.
3. Usa PHP 8.1 o posterior con PDO MySQL, mbstring y DOM/XML. Las pruebas locales se hicieron con PHP 8.4.
4. Para Apache, habilita `mod_rewrite` y las directivas de `.htaccess`. En Nginx, configura en el servidor el equivalente de URLs sin extensión y bloqueo de `/datos/`, `/config/`, `.sql`, `.bak` y archivos internos; Nginx no lee `.htaccess`.
5. Esta revisión no necesita importar SQL ni modificar el esquema. Los SQL originales se conservan como parte del proyecto, sin ejecutarse.
6. Comprueba inicio, galería, calendario, formularios, login de Discord y administración con tu base de datos real. Si el alojamiento conserva recursos antiguos en caché, invalida esa caché.

## Comprobaciones realizadas

- Sintaxis correcta en 64 PHP y 6 JavaScript; JSON válidos.
- Chromium automatizado a 1440 px y 390 px: carrusel, pausa, teclado, historial, regreso desde galería, menú y Escape, ausencia de desbordamiento horizontal, movimiento reducido y almacenamiento bloqueado.
- Reloj simulado: rotación automática y pausa persistente.
- YouTube simulado: bloqueo inicial, aceptación y retirada del consentimiento.
- Seis comprobaciones del limpiador de noticias, incluidos enlaces JavaScript, atributos de eventos y conservación del formato permitido.
- Visita de 13 páginas públicas en una instalación aislada sin credenciales. Miembros mantiene su respuesta 503 prevista sin MySQL; la API de medios mantiene el error previsto sin conexión y la galería usa las imágenes locales.

Las comprobaciones no acceden a la base de datos real ni a Discord. No verifican operaciones autenticadas, migraciones SQL o la configuración real de Apache/Nginx. Se revisó el código suministrado y se corrigieron los problemas encontrados; no se certifica la ausencia de cualquier otro fallo.
