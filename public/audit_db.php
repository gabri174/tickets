<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: application/json');

$db = new Database();
$report = [];

// 1. Test de Identidad (¿Quién soy?)
try {
    $me = $db->query("SELECT 'OK' as status", [], 'first');
    $report['connection'] = $me['status'] === 'OK' ? '✅ Conectado a D1 Proxy' : '❌ Error de conexión';
} catch (Exception $e) {
    $report['connection'] = '❌ FALLO: ' . $e->getMessage();
}

// 2. Verificar Existencia de Tablas y Columnas
try {
    $schema = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name='tickets'", [], 'first');
    $report['table_schema'] = $schema;
} catch (Exception $e) {
    $report['table_schema'] = '❌ Error leyendo schema: ' . $e->getMessage();
}

// 3. Intento de Escritura Real con datos de prueba
$testEmail = 'audit_test_' . time() . '@test.com';
$testCode = 'AUDIT-' . strtoupper(substr(md5(time()), 0, 8));

try {
    $sql = "INSERT INTO tickets (event_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $params = [9, $testCode, 'Audit User', $testEmail, '123456789', '/qrcodes/test.png'];
    
    $report['write_attempt'] = [
        'sql' => $sql,
        'params' => $params
    ];
    
    $result = $db->query($sql, $params, 'run');
    $report['write_result'] = $result;

    // 4. Verificación de Lectura Inmediata
    $verify = $db->query("SELECT * FROM tickets WHERE ticket_code = ?", [$testCode], 'first');
    $report['read_verification'] = $verify ?: '❌ NO SE ENCONTRÓ EL TICKET INSERTADO';

    // 5. Limpieza
    if ($verify) {
        $db->query("DELETE FROM tickets WHERE ticket_code = ?", [$testCode], 'run');
        $report['cleanup'] = '✅ Registro de auditoría borrado';
    }

} catch (Exception $e) {
    $report['error_critico'] = [
        'mensaje' => $e->getMessage(),
        'linea' => $e->getLine(),
        'archivo' => $e->getFile()
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT);
