<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

if (APP_ENV === 'production') {
    http_response_code(404);
    exit('Not Found');
}

checkAdminSession();

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$db = new Database();
$report = [
    'timestamp' => date('c'),
    'environment' => APP_ENV,
    'checks' => [],
];

try {
    $eventCount = $db->countEvents();
    $ticketCount = $db->countTickets();

    $report['checks'][] = [
        'name' => 'database_summary',
        'status' => 'ok',
        'events' => $eventCount,
        'tickets' => $ticketCount,
    ];
} catch (Throwable $e) {
    if (function_exists('qLog')) {
        qLog('[WARNING] audit_db admin-only falló: ' . $e->getMessage());
    }

    $report['checks'][] = [
        'name' => 'database_summary',
        'status' => 'error',
        'message' => 'No se pudo completar la auditoría.',
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
