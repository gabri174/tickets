<?php
// =================================================================
// SEGURIDAD - Configuración Blindada (v3.0)
// =================================================================

// Prevenir acceso directo al archivo config
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'config.php') {
    http_response_code(403);
    exit('Acceso directo denegado');
}

// -----------------------------------------------------------------
// Helpers básicos
// -----------------------------------------------------------------
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!is_string($path) || !file_exists($path) || !is_readable($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
            ) {
                $value = substr($value, 1, -1);
            }

            if (!defined($key)) {
                define($key, $value);
            }
        }

        return true;
    }
}

if (!function_exists('isHttpsRequest')) {
    function isHttpsRequest() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        );
    }
}

// -----------------------------------------------------------------
// Carga de entorno
// -----------------------------------------------------------------
$possiblePaths = [
    dirname(__DIR__, 2) . '/.env',
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env' : null),
    __DIR__ . '/../../.env',
    getcwd() . '/.env',
];

foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        loadEnv($path);
        break;
    }
}

// -----------------------------------------------------------------
// Defaults seguros
// -----------------------------------------------------------------
if (!defined('APP_ENV')) define('APP_ENV', 'development');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost');
if (!defined('SITE_NAME')) define('SITE_NAME', 'Tickets - Sistema de Ventas');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@tickets.com');

// Cloudflare D1
if (!defined('D1_API_URL')) define('D1_API_URL', 'https://tickets-api.crtv-technologies.workers.dev');
if (!defined('D1_API_TOKEN')) define('D1_API_TOKEN', '');

// Mail
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'no-reply@localhost');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Tickets');

// Redis / QStash
if (!defined('REDIS_REST_URL')) define('REDIS_REST_URL', '');
if (!defined('REDIS_REST_TOKEN')) define('REDIS_REST_TOKEN', '');
if (!defined('REDIS_URL')) define('REDIS_URL', '');

if (!defined('UPSTASH_QSTASH_TOKEN')) define('UPSTASH_QSTASH_TOKEN', '');
if (!defined('QSTASH_URL')) define('QSTASH_URL', 'https://qstash.upstash.io');
if (!defined('QSTASH_CURRENT_SIGNING_KEY')) define('QSTASH_CURRENT_SIGNING_KEY', '');
if (!defined('QSTASH_NEXT_SIGNING_KEY')) define('QSTASH_NEXT_SIGNING_KEY', '');
if (!defined('QUEUE_WORKER_URL')) define('QUEUE_WORKER_URL', rtrim(SITE_URL, '/') . '/queue_worker.php');

// Seguridad base
if (!defined('HASH_ALGO')) define('HASH_ALGO', 'sha256');
if (!defined('SALT_LENGTH')) define('SALT_LENGTH', 32);

// -----------------------------------------------------------------
// Rutas
// -----------------------------------------------------------------
defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 2));
defined('UPLOADS_PATH') || define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
defined('QRCODES_PATH') || define('QRCODES_PATH', ROOT_PATH . '/public/qrcodes');

// Directorio de logs fuera del webroot si existe posibilidad
$preferredLogDir = dirname(ROOT_PATH) . '/var/log';
$fallbackLogDir  = ROOT_PATH . '/storage/logs';
$legacyLogDir    = ROOT_PATH;

if (!is_dir($preferredLogDir)) {
    @mkdir($preferredLogDir, 0750, true);
}
if (!is_dir($fallbackLogDir)) {
    @mkdir($fallbackLogDir, 0750, true);
}

if (is_dir($preferredLogDir) && is_writable($preferredLogDir)) {
    defined('APP_LOG_PATH') || define('APP_LOG_PATH', $preferredLogDir . '/tickets-app.log');
} elseif (is_dir($fallbackLogDir) && is_writable($fallbackLogDir)) {
    defined('APP_LOG_PATH') || define('APP_LOG_PATH', $fallbackLogDir . '/tickets-app.log');
} else {
    defined('APP_LOG_PATH') || define('APP_LOG_PATH', $legacyLogDir . '/logs_compra.txt');
}

// -----------------------------------------------------------------
// Errores y logging
// -----------------------------------------------------------------
$isProduction = (APP_ENV === 'production');

ini_set('expose_php', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_LOG_PATH);

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// -----------------------------------------------------------------
// Zona horaria
// -----------------------------------------------------------------
date_default_timezone_set('Europe/Madrid');

// -----------------------------------------------------------------
// Sesión segura
// -----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = isHttpsRequest() || strpos(SITE_URL, 'https://') === 0;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isSecure ? '1' : '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif ((time() - (int) $_SESSION['_created']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    if (isset($_SESSION['_last_activity']) && (time() - (int) $_SESSION['_last_activity']) > 7200) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        session_start();
        $_SESSION['_created'] = time();
    }

    $_SESSION['_last_activity'] = time();
}

// Compatibilidad con código legado
$pdo = null;

/**
 * Log personalizado de la aplicación.
 * No rompe ejecución si no puede escribir.
 */
if (!function_exists('qLog')) {
    function qLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        $line = '[' . $timestamp . '] ' . $message . PHP_EOL;

        if (defined('APP_LOG_PATH')) {
            @file_put_contents(APP_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($message);
        }
    }
}
