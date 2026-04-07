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

// Usamos CURL manual en el diagnóstico para ver TODO lo que pasa
$ch = curl_init(D1_API_URL . '/api/query');
$payload = json_encode(['sql' => 'SELECT 1 as test', 'params' => [], 'method' => 'first']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . D1_API_TOKEN
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ ÉXITO: Conexión establecida con el Worker.\n";
    echo "Respuesta: " . $response . "\n";
} else {
    echo "❌ FALLO DE CONEXIÓN:\n";
    echo "Código HTTP: " . $httpCode . "\n";
    if ($curlError) echo "Error CURL: " . $curlError . "\n";
    echo "Respuesta de Cloudflare: " . $response . "\n";
    
    if ($httpCode === 401) {
        echo "\n💡 POSIBLE CAUSA: El TOKEN en tu .env no coincide con el de Cloudflare.\n";
        echo "Acción: Ejecuta 'npx wrangler secret put D1_API_TOKEN' y asegúrate de usar el mismo valor.\n";
    }
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
