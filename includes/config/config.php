<?php
// Configuración de la base de datos
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'tickets_system-35303938b2bf');
define('DB_USER', 'Gabrielg');
define('DB_PASS', 'Guerra2020@._');

// Configuración del sitio
define('SITE_URL', 'https://ensupresencia.eu');
define('SITE_NAME', 'Tickets - Sistema de Ventas');
define('ADMIN_EMAIL', 'admin@tickets.com');

// Configuración de correo (PHPMailer)
define('SMTP_HOST', 'smtp.ensupresencia.eu');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'tickets@ensupresencia.eu');
define('SMTP_PASSWORD', 'Tickets2025_esp_@.');
define('SMTP_FROM_EMAIL', 'tickets@ensupresencia.eu');
define('SMTP_FROM_NAME', 'Tickets En Su Presencia');

// Rutas del sistema
define('ROOT_PATH', dirname(__DIR__, 2));
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
define('QRCODES_PATH', ROOT_PATH . '/public/qrcodes');

// Configuración de seguridad
define('HASH_ALGO', 'sha256');
define('SALT_LENGTH', 32);

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Entorno (development / production)
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Reporte de errores (desactivar en producción)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ──────────────────────────────────────────────────────────────────────────────
// ──────────────────────────────────────────────────────────────────────────────
// 🚀 SRE - CAPA 1: REDIS CACHE (Upstash o local)
// ──────────────────────────────────────────────────────────────────────────────
$redisRestUrl   = getenv('REDIS_REST_URL')   ?: 'https://ethical-possum-85011.upstash.io';
$redisRestToken = getenv('REDIS_REST_TOKEN') ?: 'gQAAAAAAAUwTAAIncDJhZGIzYzliYTA5YTA0YzAxYTJhMmRhZDljZGRlNDM5M3AyODUwMTE';
$redisUrl       = getenv('REDIS_URL')        ?: '';

if (!defined('REDIS_REST_URL'))   define('REDIS_REST_URL',   $redisRestUrl);
if (!defined('REDIS_REST_TOKEN')) define('REDIS_REST_TOKEN', $redisRestToken);
if (!defined('REDIS_URL'))        define('REDIS_URL',        $redisUrl);


// Upstash QStash (Cola de Escritura)
define('UPSTASH_QSTASH_TOKEN', $_ENV['UPSTASH_QSTASH_TOKEN'] ?? '');
define('QSTASH_URL', $_ENV['QSTASH_URL'] ?? 'https://qstash.upstash.io');
define('QSTASH_CURRENT_SIGNING_KEY', $_ENV['QSTASH_CURRENT_SIGNING_KEY'] ?? '');
define('QSTASH_NEXT_SIGNING_KEY', $_ENV['QSTASH_NEXT_SIGNING_KEY'] ?? '');
define('QUEUE_WORKER_URL', $_ENV['QUEUE_WORKER_URL'] ?? SITE_URL . '/queue_worker.php');

// Cloudflare D1 (Migración Dual-Write)
define('D1_SYNC_URL', $_ENV['D1_SYNC_URL'] ?? SITE_URL . '/api/d1-sync');
define('D1_SYNC_TOKEN', $_ENV['D1_SYNC_TOKEN'] ?? '');

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed']));
}
?>
