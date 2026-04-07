<?php
/**
 * DIAGNÓSTICO DEFINITIVO — CLOUDFLARE D1 (v4)
 * URL: https://ensupresencia.eu/debug_d1.php
 * ⚠️ ELIMINAR ESTE ARCHIVO EN PRODUCCIÓN
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "====================================================\n";
echo "🔍 DIAGNÓSTICO DEFINITIVO — CLOUDFLARE D1 (v4)\n";
echo "====================================================\n\n";

// ── 1. VARIABLES DE ENTORNO ──────────────────────────────
echo "1. VARIABLES DE ENTORNO\n";
echo "----------------------------------------------------\n";
echo "D1_API_URL  : " . (defined('D1_API_URL')   ? D1_API_URL   : '❌ NO DEFINIDA') . "\n";
echo "D1_API_TOKEN: " . (defined('D1_API_TOKEN') && !empty(D1_API_TOKEN)
    ? '✅ SÍ (' . strlen(D1_API_TOKEN) . ' chars)'
    : '❌ NO DEFINIDA') . "\n";
echo "\n";

// ── 2. CLASE DATABASE (URL y TOKEN internos) ─────────────
echo "2. CLASE DATABASE (valores internos)\n";
echo "----------------------------------------------------\n";
$db = new Database();
$test = $db->testConnection();
echo "URL interna : " . $test['url'] . "\n";
echo "Token intern: " . $test['token'] . "\n";

if ($test['result']) {
    echo "SELECT 1    : ✅ ÉXITO — " . json_encode($test['result']) . "\n";
} else {
    echo "SELECT 1    : ❌ FALLO (el Worker no respondió correctamente)\n";
}
echo "\n";

// ── 3. PRUEBA CURL MANUAL (cabeceras incluidas) ──────────
echo "3. PRUEBA CURL MANUAL (mostrando código HTTP y cuerpo)\n";
echo "----------------------------------------------------\n";
$apiUrl = rtrim(D1_API_URL, '/') . '/api/query';
$ch = curl_init($apiUrl);
$payload = json_encode(['sql' => 'SELECT 1 as ping', 'params' => [], 'method' => 'first']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . D1_API_TOKEN,
    ],
]);
$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr     = curl_error($ch);
curl_close($ch);

echo "Endpoint    : $apiUrl\n";
echo "HTTP Code   : $httpCode\n";
if ($curlErr) echo "CURL Error  : $curlErr\n";
echo "Respuesta   : $rawResponse\n\n";

if ($httpCode === 401) {
    echo "💡 401 = Token incorrecto. Ejecuta: npx wrangler secret put D1_API_TOKEN\n\n";
} elseif ($httpCode === 404) {
    echo "💡 404 = La URL o el endpoint /api/query no existe en el Worker.\n\n";
}

// ── 4. TABLAS EN CLOUDFLARE D1 ───────────────────────────
echo "4. TABLAS EN CLOUDFLARE D1\n";
echo "----------------------------------------------------\n";
$tables = $db->listTables();
if ($tables === null) {
    echo "❌ No se pudo listar tablas. Revisa los pasos anteriores.\n";
} else {
    $names = array_column($tables, 'name');
    $required = ['admins', 'events', 'tickets', 'ticket_types', 'password_resets'];
    foreach ($required as $t) {
        echo "Tabla '$t': " . (in_array($t, $names) ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";
    }
}
echo "\n";

// ── 5. CONTEO DE ADMINS ──────────────────────────────────
echo "5. DATOS EN TABLA ADMINS\n";
echo "----------------------------------------------------\n";
$count = $db->countAdmins();
if ($count !== null) {
    echo "✅ Administradores registrados: " . $count['total'] . "\n";
} else {
    echo "❌ No se pudo consultar la tabla admins.\n";
}

echo "\n====================================================\n";
echo "✅ Si los puntos 2, 3, 4 y 5 están en verde, el sistema\n";
echo "   de registro y login DEBE funcionar correctamente.\n";
echo "====================================================\n";
