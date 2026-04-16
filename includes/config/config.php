<?php
// =================================================================
// SEGURIDAD - Configuración Blindada (v2.0)
// =================================================================

// Prevenir acceso directo al archivo config
defined('APP_ENV') || die('Acceso directo denegado');

// =================================================================
// PROTECCIÓN CONTRA FUGAS DE INFORMACIÓN
// =================================================================

// Nunca mostrar errores en producción
if (!defined('APP_ENV') || APP_ENV !== 'development') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Ocultar versión de PHP
ini_set('expose_php', 'off');

// Configuración de Sesión (Debe ser lo primero)
if (session_status() === PHP_SESSION_NONE) {
    // Detectar si estamos en HTTPS
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                 isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ||
                 (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // Configurar cookies seguras (adaptar al entorno)
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,      // Solo HTTPS en producción
        'httponly' => true,         // No accesible desde JS
        'samesite' => 'Lax'         // Prevenir CSRF pero permitir navegación
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
if (!defined('QUEUE_WORKER_URL')) define('QUEUE_WORKER_URL', SITE_URL . '/queue_worker.php');

// =================================================================
// FUNCIONES DE SEGURIDAD
// =================================================================

/**
 * Genera un token CSRF único por sesión
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF con timing-safe comparison
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenera el token CSRF (usar después de login)
 */
function regenerate_csrf_token() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Sanitiza input contra XSS
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Remover caracteres de control
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    return $data;
}

/**
 * Valida y sanitiza email
 */
function sanitize_email($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

/**
 * Valida teléfono (formato internacional)
 */
function sanitize_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return (strlen($phone) >= 10 && strlen($phone) <= 15) ? $phone : false;
}

/**
 * Hash seguro para contraseñas (bcrypt con coste 12)
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica contraseña contra hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Log de seguridad (auditoría)
 */
function security_log($action, $details = []) {
    $logFile = ROOT_PATH . '/logs/security.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    $logEntry = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'action' => $action,
        'details' => $details,
        'user' => $_SESSION['admin_id'] ?? 'anonymous'
    ]);

    file_put_contents($logFile, $logEntry . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Rate limiting simple basado en sesión
 */
function rate_limit($action, $maxAttempts = 5, $windowSeconds = 300) {
    $key = 'rate_' . $action;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    if ($now > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    $_SESSION[$key]['count']++;

    if ($_SESSION[$key]['count'] > $maxAttempts) {
        security_log('rate_limit_exceeded', ['action' => $action, 'attempts' => $_SESSION[$key]['count']]);
        return false;
    }

    return true;
}

/**
 * Headers de seguridad para enviar en respuestas
 */
function send_security_headers() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

?>

