<?php
/**
 * DIAGNÓSTICO DEFINITIVO — CLOUDFLARE D1 (v4.1)
 * MODO RESCATE ACTIVADO
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Forzamos el entorno para evitar bloqueos de seguridad durante el test
if (!defined('APP_ENV')) define('APP_ENV', 'development');

require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "====================================================\n";
echo "🔍 DIAGNÓSTICO DE RESCATE (v4.1)\n";
echo "====================================================\n\n";

try {
    $db = new Database();
    $test = $db->testConnection();
    
    echo "1. DATOS DE CONEXIÓN\n";
    echo "--------------------------\n";
    echo "API URL: " . ($test['url'] ?? '❌ ERROR') . "\n";
    echo "Token  : " . ($test['token'] ?? '❌ ERROR') . "\n";
    echo "Status : " . ($test['result'] ? "✅ CONECTADO" : "❌ FALLO") . "\n\n";

    echo "2. TABLAS\n";
    echo "--------------------------\n";
    $tables = $db->listTables();
    if ($tables) {
        foreach ($tables as $t) echo "- " . $t['name'] . "\n";
    } else {
        echo "❌ No se pudieron listar tablas.\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
