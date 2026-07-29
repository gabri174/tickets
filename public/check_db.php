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

try {
    $tickets = $db->getAllTickets((int) $_SESSION['admin_id']);
    $totalTickets = count($tickets);

    $lastFive = [];
    foreach (array_slice($tickets, 0, 5) as $ticket) {
        $lastFive[] = [
            'id' => $ticket['id'] ?? null,
            'event_title' => $ticket['event_title'] ?? '',
            'ticket_code' => $ticket['ticket_code'] ?? '',
            'status' => $ticket['status'] ?? '',
            'purchase_date' => $ticket['purchase_date'] ?? '',
        ];
    }

    echo json_encode([
        'status' => 'ok',
        'total_tickets' => $totalTickets,
        'last_5_tickets' => $lastFive,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('qLog')) {
        qLog('[WARNING] check_db interno falló: ' . $e->getMessage());
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo completar la comprobación.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
