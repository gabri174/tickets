<?php
/**
 * DIAGNÓSTICO DE CONEXIÓN CLOUDFLARE D1 (v2)
 * Ejecuta este archivo: ensupresencia.eu/debug_d1.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "==================================================\n";
echo "🔍 DIAGNÓSTICO DE SISTEMA DE TICKETS (Cloudflare D1)\n";
echo "==================================================\n\n";

echo "1. VERIFICACIÓN DE CONFIGURACIÓN (.env / config.php)\n";
echo "--------------------------------------------------\n";

$possiblePaths = [
    dirname(__DIR__, 2) . '/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/.env',
    '../../.env',
    './.env'
];

foreach ($possiblePaths as $path) {
    echo "Probando ruta: $path [" . (file_exists($path) ? "✅ ENCONTRADO" : "❌ NO ENCONTRADO") . "]\n";
}
echo "\n";

echo "URL API: " . D1_API_URL . "\n";
$hasToken = (defined('D1_API_TOKEN') && !empty(D1_API_TOKEN));
echo "TOKEN configurado: " . ($hasToken ? "✅ SÍ" : "❌ NO (Revisar .env)") . "\n";
if (!$hasToken) {
    echo "⚠️  ATENCIÓN: Sin TOKEN no puedes conectar con Cloudflare.\n";
}
echo "\n";

$db = new Database();

echo "2. PRUEBA DE CONEXIÓN BÁSICA (SELECT 1)\n";
echo "--------------------------------------------------\n";
$res1 = $db->callD1("SELECT 1 as test", [], 'first');

if ($res1) {
    echo "✅ ÉXITO: Conexión establecida con el Worker.\n";
    echo "Respuesta: " . json_encode($res1) . "\n";
} else {
    echo "❌ FALLO: No se pudo conectar con el Proxy de Cloudflare.\n";
    echo "Acción recomendada: Revisa que D1_API_URL y D1_API_TOKEN sean correctos en tu .env del servidor.\n";
}
echo "\n";

echo "3. VERIFICACIÓN DE TABLAS (D1 Cloudflare)\n";
echo "--------------------------------------------------\n";
$tables = ['admins', 'events', 'tickets', 'ticket_types'];
foreach ($tables as $table) {
    $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
    $exists = $db->callD1($sql, [$table], 'first');
    echo "Tabla '{$table}': " . ($exists ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";
}
echo "\n";

echo "4. PRUEBA DE LECTURA DE DATOS (Admins)\n";
echo "--------------------------------------------------\n";
$admins = $db->callD1("SELECT id, username, email FROM admins LIMIT 1");
if ($admins && isset($admins['results'])) {
    echo "✅ ÉXITO: Se pudo leer la tabla de administradores.\n";
    echo "Total registros encontrados: " . count($admins['results']) . "\n";
} else {
    echo "❌ ERROR: No se pudieron leer datos de 'admins'.\n";
}

echo "\n==================================================\n";
echo "💡 Si todas las marcas son ✅, el registro DEBE funcionar.\n";
echo "==================================================\n";
