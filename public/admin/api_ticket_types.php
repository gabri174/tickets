<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];

// Verificar que el evento pertenece al admin
$event = $db->getEventById($eventId, $adminId);

if ($event) {
    $types = $db->getTicketTypesByEvent($eventId);
    header('Content-Type: application/json');
    echo json_encode($types);
} else {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'No tienes permiso para ver este evento']);
}
?>
