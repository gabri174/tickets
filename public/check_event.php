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
    $eventId = 9;
    $event = $db->getEventById($eventId);

    if (!$event) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Evento no encontrado.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'event' => [
            'id' => $event['id'] ?? null,
            'title' => $event['title'] ?? '',
            'date_event' => $event['date_event'] ?? '',
            'location' => $event['location'] ?? '',
            'status' => $event['status'] ?? '',
            'available_tickets' => $event['available_tickets'] ?? 0,
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('qLog')) {
        qLog('[WARNING] check_event interno falló: ' . $e->getMessage());
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo completar la comprobación.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
