<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: text/plain');

echo "--- DIAGNÓSTICO DE CONEXIÓN ---\n\n";

// 1. Verificar Entorno
echo "APP_ENV: " . (defined('APP_ENV') ? APP_ENV : 'NO DEFINIDO') . "\n";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NO DEFINIDO') . "\n";

// 2. Verificar D1 Config
$db = new Database();
$status = $db->testConnection();

echo "\n--- CLOUDFLARE D1 ---\n";
echo "API URL: " . $status['url'] . "\n";
echo "Token configurado: " . $status['token'] . "\n";

echo "\n--- PRUEBA DE CONSULTA ---\n";
// Para ver el error exacto, llamaremos a callD1 pero capturando el error si falla
$ch = curl_init($status['url'] . '/api/query');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sql' => 'SELECT 1 as test', 'method' => 'first']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . (defined('D1_API_TOKEN') ? D1_API_TOKEN : '')
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    echo "❌ ERROR DE RED (CURL): " . $curlError . "\n";
} elseif ($httpCode !== 200) {
    echo "❌ ERROR HTTP " . $httpCode . ": El servidor de Cloudflare respondió con un error.\n";
    echo "Respuesta: " . $response . "\n";
} else {
    echo "✅ ÉXITO: Conexión establecida.\n";
    print_r(json_decode($response, true));
}

// 3. Verificar Tablas
echo "\n--- TABLAS DISPONIBLES ---\n";
$tables = $db->listTables();
if ($tables) {
    foreach ($tables as $t) {
        echo "- " . $t['name'] . "\n";
    }
} else {
    echo "❌ No se pudieron listar las tablas.\n";
}

// 4. Buscar evento de Joel
echo "\n--- BÚSQUEDA DE EVENTOS ---\n";
$events = $db->getActiveEvents();
if (!empty($events)) {
    echo "Se encontraron " . count($events) . " eventos:\n";
    foreach ($events as $e) {
        echo "- [" . $e['id'] . "] " . $e['title'] . " (Status: " . ($e['status'] ?? 'N/A') . ")\n";
    }
} else {
    echo "⚠️ La lista de eventos está VACÍA.\n";
}
?>
