<?php
require_once 'includes/config/config.php';
require_once 'includes/classes/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== DIAGNÓSTICO DE CREACIÓN DE TICKET ===\n\n";

$db = new Database();

// Parámetros de prueba
$eventId = 9; // El evento Joel Acosta
$ticketCode = 'TEST-' . time();
$name = 'Test User';
$email = 'test@example.com';
$phone = '123456789';
$qrPath = 'qrcodes/test.png';
$ticketTypeId = null;

echo "Intentando crear ticket: $ticketCode...\n";

$sql = "INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path, referral, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$params = [$eventId, $ticketTypeId, $ticketCode, $name, $email, $phone, $qrPath, null, '28001'];

// Usamos callD1 directamente para ver la respuesta cruda en este test
// Accedemos a callD1 vía reflexión porque es privado, o simplemente usamos query()
$res = $db->query($sql, $params, 'run');

if ($res) {
    echo "✅ ÉXITO: Ticket creado.\n";
    print_r($res);
} else {
    echo "❌ FALLO: No se pudo crear el ticket.\n";
    echo "Revisa el error_log del servidor para ver el mensaje detallado de la API D1.\n";
}

echo "\nPruebas completadas.";
