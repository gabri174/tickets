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
if ($status['result']) {
    echo "✅ ÉXITO: La base de datos responde.\n";
    print_r($status['result']);
} else {
    echo "❌ ERROR: No se recibió respuesta de Cloudflare.\n";
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
