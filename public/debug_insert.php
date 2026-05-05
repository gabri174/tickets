<?php
require_once 'includes/config/config.php';
require_once 'includes/classes/Database.php';

$db = new Database();
header('Content-Type: application/json');

try {
    // 1. Verificar si el evento existe
    $event_id = 9; // El que estás usando
    $event = $db->getEventById($event_id);
    
    if (!$event) {
        die(json_encode(['error' => 'El evento 9 no existe en la base de datos D1.']));
    }

    // 2. Intentar una inserción de PRUEBA MANUAL
    $test_code = 'TEST-DIAG-' . time();
    $sql = "INSERT INTO tickets (event_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $params = [$event_id, $test_code, 'Test Auditor', 'test@example.com', '123456789', 'qrcodes/test.png'];
    
    // Usamos callD1 directamente para capturar la respuesta cruda
    $method = 'run';
    $ch = curl_init(D1_API_URL . '/api/query');
    $payload = json_encode([
        'sql' => $sql,
        'params' => $params,
        'method' => $method
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . D1_API_TOKEN
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    echo json_encode([
        'diagnóstico' => 'Auditoría de Inserción Directa',
        'event_found' => $event['title'],
        'attempted_sql' => $sql,
        'params' => $params,
        'raw_response_from_d1' => $data
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
