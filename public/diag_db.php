<?php
// ─── DIAGNÓSTICO DE CONEXIÓN D1 ───────────────────────────────────────────
// Accede a: https://ensupresencia.eu/diag_db.php
// IMPORTANTE: Eliminar este archivo después del diagnóstico.
// ─────────────────────────────────────────────────────────────────────────

require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. ¿Están definidas las constantes de config?
$results['config'] = [
    'D1_API_URL'   => defined('D1_API_URL')   ? D1_API_URL   : '❌ NO DEFINIDO',
    'D1_API_TOKEN' => defined('D1_API_TOKEN')  ? (empty(D1_API_TOKEN) ? '❌ VACÍO' : '✅ Definido (' . strlen(D1_API_TOKEN) . ' chars)') : '❌ NO DEFINIDO',
    'SITE_URL'     => defined('SITE_URL')      ? SITE_URL     : '❌ NO DEFINIDO',
];

// 2. Test de conexión básica (SELECT 1)
$db = new Database();
$connTest = $db->testConnection();
$results['connection_test'] = $connTest;

// 3. Listar tablas
try {
    $tables = $db->listTables();
    $results['tables'] = $tables ?: '❌ Sin tablas o error';
} catch (Throwable $e) {
    $results['tables'] = '❌ Error: ' . $e->getMessage();
}

// 4. Contar tickets existentes en event_id=9
try {
    $count = $db->query("SELECT COUNT(*) as total FROM tickets WHERE event_id = ?", [9], 'first');
    $results['tickets_event_9'] = $count;
} catch (Throwable $e) {
    $results['tickets_event_9'] = '❌ Error: ' . $e->getMessage();
}

// 5. Intentar insertar un ticket de PRUEBA
$testCode = 'TEST-DIAG-' . time();
try {
    $writeResult = $db->query(
        "INSERT INTO tickets (event_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path) VALUES (?, ?, ?, ?, ?, ?)",
        [9, $testCode, 'Test Diag', 'test@diag.com', '000000000', '/tmp/test.png'],
        'run'
    );
    $results['test_write'] = [
        'success' => true,
        'ticket_code' => $testCode,
        'result' => $writeResult
    ];

    // Limpiamos el ticket de prueba
    $db->query("DELETE FROM tickets WHERE ticket_code = ?", [$testCode], 'run');
    $results['test_cleanup'] = '✅ Ticket de prueba eliminado';

} catch (Throwable $e) {
    $results['test_write'] = '❌ FALLO ESCRITURA: ' . $e->getMessage();
}

// 6. Buscar tickets de ggaboomar@gmail.com en últimas 24 horas
try {
    $recent = $db->query(
        "SELECT ticket_code, attendee_email, attendee_phone, purchase_date FROM tickets WHERE event_id = 9 AND purchase_date > datetime('now', '-24 hours') ORDER BY id DESC LIMIT 10",
        []
    );
    $results['recent_tickets_24h'] = $recent ?: '0 tickets en las últimas 24h';
} catch (Throwable $e) {
    $results['recent_tickets_24h'] = '❌ Error: ' . $e->getMessage();
}

// 7. Método de pago del admin para evento 9
try {
    $event9 = $db->query("SELECT e.id, e.title, e.price, e.admin_id, a.preferred_payment_method, a.payment_config FROM events e JOIN admins a ON e.admin_id = a.id WHERE e.id = 9", [], 'first');
    $results['event_9_payment'] = [
        'title'                    => $event9['title'] ?? '?',
        'price'                    => $event9['price'] ?? '?',
        'preferred_payment_method' => $event9['preferred_payment_method'] ?? '❌ NO ENCONTRADO',
        'payment_config_keys'      => array_keys(json_decode($event9['payment_config'] ?? '{}', true)),
    ];
} catch (Throwable $e) {
    $results['event_9_payment'] = '❌ Error: ' . $e->getMessage();
}

// 8. ¿Existe queue_worker.php?
$results['queue_worker_exists'] = file_exists(__DIR__ . '/queue_worker.php') ? '✅ Existe' : '❌ No existe';

// 9. Verificar si la sesión actual tiene purchase data
$results['session_purchase_data'] = [
    'pending_purchase'  => isset($_SESSION['pending_purchase'])  ? '✅ EXISTE' : '❌ No existe',
    'purchase_success'  => isset($_SESSION['purchase_success'])  ? '✅ EXISTE' : '❌ No existe',
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
