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

// 10. Tipos de entrada del evento 9
try {
    $types = $db->query("SELECT id, name, price, available_tickets FROM ticket_types WHERE event_id = 9", []);
    $results['ticket_types_event_9'] = $types ?: 'Sin tipos de entrada (usa precio del evento)';
} catch (Throwable $e) {
    $results['ticket_types_event_9'] = '❌ Error: ' . $e->getMessage();
}

// 11. Verificar directorio de QR codes
$qrDir = __DIR__ . '/qrcodes';
$results['qr_directory'] = [
    'path'     => $qrDir,
    'exists'   => is_dir($qrDir)    ? '✅ Existe' : '❌ NO EXISTE',
    'writable' => is_writable($qrDir) ? '✅ Escribible' : '❌ NO ESCRIBIBLE',
];

// Si no existe, intentar crearlo
if (!is_dir($qrDir)) {
    $created = mkdir($qrDir, 0755, true);
    $results['qr_directory']['created_now'] = $created ? '✅ Creado ahora' : '❌ No se pudo crear';
}

// 12. Test de completePurchase() con datos reales
require_once '../includes/functions/functions.php';
$testPurchaseData = [
    'event_id'       => 9,
    'ticket_type_id' => null,
    'quantity'       => 1,
    'attendees'      => [['name' => 'Test Diagnostico', 'email' => 'diag@test.local', 'phone' => '000000001']],
    'phone'          => '000000001',
    'zip_code'       => null,
    'total_price'    => 0,
    'referral'       => null,
];
try {
    $purchaseResult = completePurchase($testPurchaseData, $db);
    $results['complete_purchase_test'] = [
        'success'      => '✅ completePurchase() funcionó',
        'tickets_count' => count($purchaseResult['tickets'] ?? []),
        'event_title'   => $purchaseResult['event_title'] ?? '?',
    ];
    // Limpiar: borrar el ticket de prueba
    $db->query("DELETE FROM tickets WHERE attendee_email = ?", ['diag@test.local'], 'run');
    $results['complete_purchase_test']['cleanup'] = '✅ Ticket de prueba eliminado';
} catch (Throwable $e) {
    $results['complete_purchase_test'] = '❌ FALLO: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine();
}

// 13. Verificar qué versión de buy.php está en el servidor
$buyPhpPath = __DIR__ . '/buy.php';
if (file_exists($buyPhpPath)) {
    $buyContent = file_get_contents($buyPhpPath);
    $results['buy_php_version_check'] = [
        'file_exists'                  => '✅ buy.php encontrado',
        'has_async_success_redirect'   => strpos($buyContent, 'async_success') !== false
                                          ? '❌ VIEJO: tiene async_success en redirect'
                                          : '✅ NUEVO: no genera async_success',
        'has_totalPrice_check'         => strpos($buyContent, 'totalPrice <= 0') !== false
                                          ? '✅ NUEVO: tiene check de precio cero'
                                          : '❌ VIEJO: no tiene check de precio cero',
        'has_empty_paymentConfig_check' => strpos($buyContent, 'empty($paymentConfig)') !== false
                                          ? '✅ NUEVO: valida config de pago'
                                          : '❌ VIEJO: no valida config de pago',
        'finassets_success_url_line'   => '',
    ];
    // Extraer la línea con el successUrl de Finassets
    preg_match('/successUrl\s*=\s*[^;]+;/', $buyContent, $matches);
    $results['buy_php_version_check']['finassets_success_url_line'] = $matches[0] ?? 'No encontrado';
} else {
    $results['buy_php_version_check'] = '❌ buy.php NO ENCONTRADO en ' . $buyPhpPath;
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
