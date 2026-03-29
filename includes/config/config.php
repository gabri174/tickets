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

// Si no se definieron desde .env, usar valores por defecto
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'tickets_system');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

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

date_default_timezone_set('America/Mexico_City');

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
if (!defined('QUEUE_WORKER_URL')) define('QUEUE_WORKER_URL', SITE_URL . '/queue_worker.php');

// ──────────────────────────────────────────────────────────────────────
// Cloudflare D1 (Migración Dual-Write)
// ──────────────────────────────────────────────────────────────────────

if (!defined('D1_SYNC_URL')) define('D1_SYNC_URL', SITE_URL . '/api/d1-sync');
if (!defined('D1_SYNC_TOKEN')) define('D1_SYNC_TOKEN', '');

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
