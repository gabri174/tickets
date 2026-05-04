<?php
// =================================================================
// SEGURIDAD - Configuración Blindada (v2.0)
// =================================================================

// Prevenir acceso directo al archivo config (bloquea si se abre directamente por URL)
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'config.php') {
    die('Acceso directo denegado');
}

// =================================================================
// PROTECCIÓN CONTRA FUGAS DE INFORMACIÓN
// =================================================================

// Nunca mostrar errores en producción
if (!defined('APP_ENV') || APP_ENV !== 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Ocultar versión de PHP
ini_set('expose_php', 'off');

// Configuración de Sesión (Debe ser lo primero)
if (session_status() === PHP_SESSION_NONE) {
    // Detectar si estamos en HTTPS (añadimos más checks para proxies comunes)
    $isSecure = (
        (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (strpos(SITE_URL, 'https://') === 0) // Si SITE_URL es https, forzamos
    );
    
    // Configurar cookies seguras
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax' // Volvemos a Lax (estándar) para máxima compatibilidad interna
    ]);



    // Iniciar sesión con configuración de seguridad
    session_start();

    // Regenerar ID de sesión periódicamente para prevenir session fixation
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) { // 30 minutos
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    // Timeout de sesión después de 2 horas de inactividad
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > 7200)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['_last_activity'] = time();
}

// ──────────────────────────────────────────────────────────────────────
// Carga de variables de entorno desde .env
// ──────────────────────────────────────────────────────────────────────

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsear KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');

            if (!empty($key) && !defined($key)) {
                define($key, $value);
            }
        }
    }

    return true;
}

// Cargar .env desde la raíz del proyecto (probando varias rutas comunes)
$possiblePaths = [
    dirname(__DIR__, 2) . '/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/.env',
    '../../.env',
    './.env'
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        loadEnv($path);
        break;
    }
}

// ──────────────────────────────────────────────────────────────────────
// Cloudflare D1 (Proxy API)
// ──────────────────────────────────────────────────────────────────────

if (!defined('D1_API_URL')) define('D1_API_URL', 'https://tickets-api.crtv-technologies.workers.dev');
if (!defined('D1_API_TOKEN')) define('D1_API_TOKEN', '');

// Eliminamos la conexión PDO directa a MySQL
$pdo = null;

// ──────────────────────────────────────────────────────────────────────
// Configuración del sitio
// ──────────────────────────────────────────────────────────────────────

if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost');
if (!defined('SITE_NAME')) define('SITE_NAME', 'Tickets - Sistema de Ventas');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@tickets.com');

// ──────────────────────────────────────────────────────────────────────
// Configuración de correo (PHPMailer)
// ──────────────────────────────────────────────────────────────────────

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'no-reply@localhost');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Tickets');

// ──────────────────────────────────────────────────────────────────────
// Rutas del sistema
// ──────────────────────────────────────────────────────────────────────

define('ROOT_PATH', dirname(__DIR__, 2));
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
define('QRCODES_PATH', ROOT_PATH . '/public/qrcodes');

// ──────────────────────────────────────────────────────────────────────
// Configuración de seguridad
// ──────────────────────────────────────────────────────────────────────

define('HASH_ALGO', 'sha256');
define('SALT_LENGTH', 32);

// ──────────────────────────────────────────────────────────────────────
// Zona horaria
// ──────────────────────────────────────────────────────────────────────

date_default_timezone_set('Europe/Madrid');

// ──────────────────────────────────────────────────────────────────────
// Entorno (development / production)
// ──────────────────────────────────────────────────────────────────────

if (!defined('APP_ENV')) define('APP_ENV', 'production');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ──────────────────────────────────────────────────────────────────────
// 🚀 SRE - CAPA 1: REDIS CACHE (Upstash o local)
// ──────────────────────────────────────────────────────────────────────

if (!defined('REDIS_REST_URL')) define('REDIS_REST_URL', '');
if (!defined('REDIS_REST_TOKEN')) define('REDIS_REST_TOKEN', '');
if (!defined('REDIS_URL')) define('REDIS_URL', '');

// ──────────────────────────────────────────────────────────────────────
// Upstash QStash (Cola de Escritura)
// ──────────────────────────────────────────────────────────────────────

if (!defined('UPSTASH_QSTASH_TOKEN')) define('UPSTASH_QSTASH_TOKEN', '');
if (!defined('QSTASH_URL')) define('QSTASH_URL', 'https://qstash.upstash.io');
if (!defined('QSTASH_CURRENT_SIGNING_KEY')) define('QSTASH_CURRENT_SIGNING_KEY', '');
if (!defined('QSTASH_NEXT_SIGNING_KEY')) define('QSTASH_NEXT_SIGNING_KEY', '');
if (!defined('QUEUE_WORKER_URL'))    define('QUEUE_WORKER_URL', SITE_URL . '/queue_worker.php');
