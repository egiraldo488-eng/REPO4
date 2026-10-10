<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Bogota');

/*
 * Credenciales de la base de datos.
 * Puedes sobrescribirlas con variables de entorno (DB_HOST, DB_USER, DB_PASS, DB_NAME)
 * para no dejar la clave dentro del código en producción.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'mysql-joestar.alwaysdata.net');
define('DB_USER', getenv('DB_USER') ?: 'joestar');
define('DB_PASS', getenv('DB_PASS') ?: 'splatwoomy2727');
define('DB_NAME', getenv('DB_NAME') ?: 'joestar_articulosgit');

/** Escapa texto para imprimirlo de forma segura en HTML. */
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Devuelve la conexión PDO y se asegura de que las tablas existan. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        crear_tablas($pdo);
    } catch (PDOException $e) {
        error_log('[articulos] ' . $e->getMessage());
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
           . '<body style="font-family:Segoe UI,sans-serif;padding:3rem;text-align:center">'
           . '<h1>No pudimos conectar con la base de datos</h1>'
           . '<p>Revisa las credenciales en <code>config.php</code> e inténtalo de nuevo.</p></body>';
        exit;
    }
    return $pdo;
}

/** Crea las tablas necesarias si todavía no existen. */
function crear_tablas(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS articulos (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre         VARCHAR(150) NOT NULL,
            marca          VARCHAR(100) NOT NULL,
            unidades       INT UNSIGNED NOT NULL DEFAULT 0,
            ubicacion      VARCHAR(150) NOT NULL,
            creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_nombre (nombre),
            KEY idx_marca (marca)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/* ---------- Protección CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------- Mensajes flash ---------- */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
