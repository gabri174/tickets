<?php
/**
 * DIAGNÓSTICO DE CONEXIÓN CLOUDFLARE D1
 * Ejecuta este archivo en tu navegador: ensupresencia.eu/debug_d1.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: text/plain');

echo "=== DIAGNÓSTICO DE CONEXIÓN D1 ===\n\n";

echo "1. Verificando Configuración:\n";
echo "URL API: " . D1_API_URL . "\n";
echo "TOKEN configurado: " . (defined('D1_API_TOKEN') && !empty(D1_API_TOKEN) ? "SÍ (longitud: " . strlen(D1_API_TOKEN) . ")" : "NO") . "\n\n";

$db = new Database();

echo "2. Probando consulta simple (SELECT 1):\n";
$res = $db->callD1("SELECT 1 as test");

if ($res) {
    echo "¡ÉXITO! La conexión con el Worker y D1 es correcta.\n";
    echo "Respuesta: " . json_encode($res) . "\n";
} else {
    echo "FALLO: No se pudo conectar con el Proxy de D1.\n";
    echo "Revisa los logs de error de tu servidor PHP (error_log) para ver el detalle del curl.\n";
    
    // Intento de diagnóstico manual con CURL para ver cabeceras
    echo "\n3. Diagnostico Manual (CURL Detallado):\n";
    $ch = curl_init(D1_API_URL . '/api/query');
    $payload = json_encode(['sql' => 'SELECT 1', 'params' => [], 'method' => 'all']);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . D1_API_TOKEN
    ]);
    // Desactivar verificación SSL temporalmente solo para prueba si es necesario (no recomendado en prod)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    
    echo "Código HTTP: " . $info['http_code'] . "\n";
    if ($err) echo "Error CURL: " . $err . "\n";
    echo "Respuesta Cruda: " . $response . "\n";
    
    curl_close($ch);
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
