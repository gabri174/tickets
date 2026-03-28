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

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'tickets_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ──────────────────────────────────────────────────────────────────────
// Configuración del sitio
// ──────────────────────────────────────────────────────────────────────

define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');
define('SITE_NAME', getenv('SITE_NAME') ?: 'Tickets - Sistema de Ventas');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@tickets.com');

// ──────────────────────────────────────────────────────────────────────
// Configuración de correo (PHPMailer)
// ──────────────────────────────────────────────────────────────────────

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'localhost');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@localhost');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Tickets');

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

define('APP_ENV', getenv('APP_ENV') ?: 'production');

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

define('REDIS_REST_URL', getenv('REDIS_REST_URL') ?: '');
define('REDIS_REST_TOKEN', getenv('REDIS_REST_TOKEN') ?: '');
define('REDIS_URL', getenv('REDIS_URL') ?: '');

// ──────────────────────────────────────────────────────────────────────
// Upstash QStash (Cola de Escritura)
// ──────────────────────────────────────────────────────────────────────

define('UPSTASH_QSTASH_TOKEN', getenv('UPSTASH_QSTASH_TOKEN') ?: '');
define('QSTASH_URL', getenv('QSTASH_URL') ?: 'https://qstash.upstash.io');
define('QSTASH_CURRENT_SIGNING_KEY', getenv('QSTASH_CURRENT_SIGNING_KEY') ?: '');
define('QSTASH_NEXT_SIGNING_KEY', getenv('QSTASH_NEXT_SIGNING_KEY') ?: '');
define('QUEUE_WORKER_URL', getenv('QUEUE_WORKER_URL') ?: SITE_URL . '/queue_worker.php');

// ──────────────────────────────────────────────────────────────────────
// Cloudflare D1 (Migración Dual-Write)
// ──────────────────────────────────────────────────────────────────────

define('D1_SYNC_URL', getenv('D1_SYNC_URL') ?: SITE_URL . '/api/d1-sync');
define('D1_SYNC_TOKEN', getenv('D1_SYNC_TOKEN') ?: '');

// ──────────────────────────────────────────────────────────────────────
// Conexión a la base de datos
// ──────────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed']));
}
?>
