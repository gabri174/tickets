<?php
require_once 'includes/config/config.php';
require_once 'includes/classes/Database.php';

$db = new Database();
header('Content-Type: application/json');

try {
    // 1. Ver el esquema real de la tabla tickets
    $schema = $db->query("SELECT sql FROM sqlite_master WHERE name='tickets'", [], 'first');
    
    // 2. Ver los últimos 5 tickets
    $lastTickets = $db->query("SELECT * FROM tickets ORDER BY id DESC LIMIT 5");
    
    // 3. Ver total de tickets
    $total = $db->query("SELECT COUNT(*) as count FROM tickets", [], 'first');

    echo json_encode([
        'connection' => '✅ Conectado a D1 Proxy',
        'table_schema' => $schema,
        'total_tickets' => $total['count'] ?? 0,
        'last_5_tickets' => $lastTickets
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
