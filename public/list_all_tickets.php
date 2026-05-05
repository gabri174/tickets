<?php
require_once 'includes/config/config.php';
require_once 'includes/classes/Database.php';

$db = new Database();
header('Content-Type: application/json');

$sql = "SELECT t.*, e.title as event_title FROM tickets t 
        LEFT JOIN events e ON t.event_id = e.id 
        ORDER BY t.id DESC LIMIT 20";
$tickets = $db->query($sql);

echo json_encode([
    'total_in_db' => count($tickets),
    'last_20_tickets' => $tickets
], JSON_PRETTY_PRINT);
