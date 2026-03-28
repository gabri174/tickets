<?php
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

// Cargar .env desde la raíz del proyecto
$envPath = dirname(__DIR__, 2) . '/.env';
loadEnv($envPath);

// ──────────────────────────────────────────────────────────────────────
// Configuración de la base de datos
// ──────────────────────────────────────────────────────────────────────

if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

// Las constantes ya están definidas por loadEnv(), usamos valores por defecto si no
define('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
define('DB_NAME', defined('DB_NAME') ? DB_NAME : 'tickets_system');
define('DB_USER', defined('DB_USER') ? DB_USER : 'root');
define('DB_PASS', defined('DB_PASS') ? DB_PASS : '');

// ──────────────────────────────────────────────────────────────────────
// Configuración del sitio
// ──────────────────────────────────────────────────────────────────────

define('SITE_URL', defined('SITE_URL') ? SITE_URL : 'http://localhost');
define('SITE_NAME', defined('SITE_NAME') ? SITE_NAME : 'Tickets - Sistema de Ventas');
define('ADMIN_EMAIL', defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@tickets.com');

// ──────────────────────────────────────────────────────────────────────
// Configuración de correo (PHPMailer)
// ──────────────────────────────────────────────────────────────────────

define('SMTP_HOST', defined('SMTP_HOST') ? SMTP_HOST : 'localhost');
define('SMTP_PORT', defined('SMTP_PORT') ? SMTP_PORT : 587);
define('SMTP_USERNAME', defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
define('SMTP_PASSWORD', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
define('SMTP_FROM_EMAIL', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@localhost');
define('SMTP_FROM_NAME', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Tickets');

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

date_default_timezone_set('America/Mexico_City');

// ──────────────────────────────────────────────────────────────────────
// Entorno (development / production)
// ──────────────────────────────────────────────────────────────────────

define('APP_ENV', defined('APP_ENV') ? APP_ENV : 'production');

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

define('REDIS_REST_URL', defined('REDIS_REST_URL') ? REDIS_REST_URL : '');
define('REDIS_REST_TOKEN', defined('REDIS_REST_TOKEN') ? REDIS_REST_TOKEN : '');
define('REDIS_URL', defined('REDIS_URL') ? REDIS_URL : '');

// ──────────────────────────────────────────────────────────────────────
// Upstash QStash (Cola de Escritura)
// ──────────────────────────────────────────────────────────────────────

define('UPSTASH_QSTASH_TOKEN', defined('UPSTASH_QSTASH_TOKEN') ? UPSTASH_QSTASH_TOKEN : '');
define('QSTASH_URL', defined('QSTASH_URL') ? QSTASH_URL : 'https://qstash.upstash.io');
define('QSTASH_CURRENT_SIGNING_KEY', defined('QSTASH_CURRENT_SIGNING_KEY') ? QSTASH_CURRENT_SIGNING_KEY : '');
define('QSTASH_NEXT_SIGNING_KEY', defined('QSTASH_NEXT_SIGNING_KEY') ? QSTASH_NEXT_SIGNING_KEY : '');
define('QUEUE_WORKER_URL', defined('QUEUE_WORKER_URL') ? QUEUE_WORKER_URL : SITE_URL . '/queue_worker.php');

// ──────────────────────────────────────────────────────────────────────
// Cloudflare D1 (Migración Dual-Write)
// ──────────────────────────────────────────────────────────────────────

define('D1_SYNC_URL', defined('D1_SYNC_URL') ? D1_SYNC_URL : SITE_URL . '/api/d1-sync');
define('D1_SYNC_TOKEN', defined('D1_SYNC_TOKEN') ? D1_SYNC_TOKEN : '');

// ──────────────────────────────────────────────────────────────────────
// Conexión a la base de datos
// ──────────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    error_log("DB Host: " . DB_HOST . ", DB Name: " . DB_NAME);
    die(json_encode(['error' => 'Database connection failed']));
}
?>
