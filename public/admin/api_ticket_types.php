<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
if ($eventId <= 0) {
    jsonResponse(['error' => 'Evento inválido'], 400);
}

$adminRole = $_SESSION['admin_role'] ?? '';
$adminId = $adminRole === 'superadmin' ? null : (int) ($_SESSION['admin_id'] ?? 0);

try {
    $db = new Database();

    $event = $db->getEventById($eventId, $adminId);
    if (!$event) {
        jsonResponse(['error' => 'No tienes permiso para ver este evento'], 403);
    }

    $types = $db->getTicketTypesByEvent($eventId);
    if (!is_array($types)) {
        $types = [];
    }

    $safeTypes = array_map(function ($type) {
        return [
            'id' => (int) ($type['id'] ?? 0),
            'name' => (string) ($type['name'] ?? ''),
            'price' => (float) ($type['price'] ?? 0),
            'stock' => isset($type['stock']) ? (int) $type['stock'] : null,
            'available' => isset($type['available']) ? (bool) $type['available'] : true
        ];
    }, $types);

    jsonResponse($safeTypes, 200);
} catch (Throwable $e) {
    error_log('api_ticket_types error: ' . $e->getMessage());
    jsonResponse(['error' => 'Error interno del servidor'], 500);
}
