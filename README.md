# NEXUS — Veteranos de Azeroth

Web PHP de la hermandad NEXUS para World of Warcraft Forever, con carrusel en el inicio y revisión de JavaScript, navegación y sesiones.

## Descargar

Pulsa **Code → Download ZIP** o usa [Descargar proyecto actualizado](https://github.com/BYG80/nexus2026/archive/refs/heads/main.zip).

## Novedades: SEO, chat IA, Discord y armería

Lee [las instrucciones de configuración](LEEME_SEO_IA_DISCORD.md) para activar la IA con una clave privada, configurar los canales de Discord y los horarios, y añadir personajes a la armería de WoW Forever EU. Las nuevas pantallas están en el panel de administración.

## Instalar o actualizar

1. Descomprime el ZIP y sube el contenido de la carpeta `nexus2026-main` a la raíz de tu alojamiento, incluidos los archivos `.htaccess`.
2. Conserva tu `config/config.php` actual. Ese archivo contiene credenciales privadas y **no se publica en este repositorio**. Para una instalación nueva, copia `config/config.php.example` como `config/config.php` y configura la base de datos y Discord en tu servidor.
3. Usa PHP 8.1 o superior, PDO MySQL, mbstring y DOM/XML. En Apache, habilita las directivas de `.htaccess` y `mod_rewrite`. En Nginx configura las rutas y restricciones equivalentes.
4. Las tablas nuevas se preparan desde sus pantallas administrativas si el usuario MySQL tiene permiso CREATE. En caso contrario, aplica solo `MIGRACION_IA_DISCORD_ARMERIA.sql`. No importes `database.sql` sobre tu instalación existente.

Consulta [LEEME_REVISION.md](LEEME_REVISION.md) para ver los cambios, las comprobaciones y los límites de la validación local. Las credenciales y las conexiones de producción no se han probado.
