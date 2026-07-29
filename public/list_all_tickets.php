<?php
require_once 'includes/config/config.php';
require_once 'includes/functions/functions.php';
require_once 'includes/classes/Database.php';

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
    $safeTickets = [];

    foreach (array_slice($tickets, 0, 20) as $ticket) {
        $safeTickets[] = [
            'id' => $ticket['id'] ?? null,
            'event_title' => $ticket['event_title'] ?? '',
            'ticket_code' => $ticket['ticket_code'] ?? '',
            'status' => $ticket['status'] ?? '',
            'purchase_date' => $ticket['purchase_date'] ?? '',
        ];
    }

    echo json_encode([
        'count' => count($safeTickets),
        'tickets' => $safeTickets,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('qLog')) {
        qLog('[WARNING] list_all_tickets interno falló: ' . $e->getMessage());
    }

    echo json_encode([
        'error' => 'No se pudieron obtener los tickets.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
