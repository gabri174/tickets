<?php
require_once 'includes/config/config.php';
require_once 'includes/classes/Database.php';

$db = new Database();
header('Content-Type: application/json');

$event = $db->getEventById(9);

echo json_encode([
    'event_id_9' => $event
], JSON_PRETTY_PRINT);
