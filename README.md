# Inventario Aqua · Gestor de artículos (PHP + MySQL)

Aplicación para gestionar artículos con **nombre, marca, unidades y ubicación**.
Incluye crear, editar, eliminar, buscar, filtrar por marca y paginación.

## Requisitos
- PHP 7.4 o superior con la extensión `pdo_mysql`
- Un servidor MySQL/MariaDB accesible

## Instalación
1. Sube la carpeta completa a tu hosting (por ejemplo, la carpeta `www` de AlwaysData).
2. Abre `index.php` en el navegador. Las tablas se crean solas la primera vez.

## Configuración
Las credenciales están en `config.php`. Para producción es mejor definirlas como
variables de entorno: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.

## Estructura
- `index.php`: pantalla principal y lógica (crear, editar, eliminar, buscar)
- `config.php`: conexión PDO, creación de tablas, CSRF y mensajes
- `assets/style.css`: diseño Frutiger Aero
- `assets/app.js`: ventanas y comportamiento

## Tabla creada automáticamente
`articulos (id, nombre, marca, unidades, ubicacion, creado_en, actualizado_en)`
