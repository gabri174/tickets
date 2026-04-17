<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== DIAGNÓSTICO DE CREACIÓN DE TICKET ===\n\n";

$db = new Database();

// Parámetros de prueba
$eventId = 9; // El evento Joel Acosta
$ticketCode = 'TEST-' . time();
$name = 'Test User';
$email = 'test' . time() . '@example.com'; // Email único cada vez
$phone = '600000000';
$ticketTypeId = null;

echo "Intentando generar QR real...\n";
try {
    $qrData = SITE_URL . "/ticket.php?code=" . $ticketCode;
    $qrPath = generateQRCode($qrData, $ticketCode);
    echo "✅ ÉXITO: QR generado en $qrPath\n";
} catch (Exception $e) {
    echo "❌ FALLO al generar QR: " . $e->getMessage() . "\n";
    $qrPath = 'qrcodes/failed.png';
}

echo "Intentando crear ticket en D1: $ticketCode...\n";

$sql = "INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path, referral, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$params = [$eventId, $ticketTypeId, $ticketCode, $name, $email, $phone, $qrPath, null, '28001'];

// Usamos callD1 directamente para ver la respuesta cruda en este test
// Accedemos a callD1 vía reflexión porque es privado, o simplemente usamos query()
$res = $db->query($sql, $params, 'run');

if ($res) {
    echo "✅ ÉXITO: Ticket creado (ID: " . ($res['meta']['last_row_id'] ?? '??') . ").\n";
    
    echo "\nProbando búsqueda de tickets recientes para: $email...\n";
    $recent = $db->getRecentTicketsByEmail($email, $eventId, 10);
    
    if (count($recent) > 0) {
        echo "✅ ÉXITO: Se encontraron " . count($recent) . " tickets recientes.\n";
        print_r($recent);
    } else {
        echo "❌ FALLO: No se encontraron tickets a pesar de haberlo creado justo ahora.\n";
        echo "Esto confirma un desajuste en la comparación de fechas (SQLite datetime).\n";
    }
} else {
    echo "❌ FALLO: No se pudo crear el ticket.\n";
    echo "Revisa el error_log del servidor para ver el mensaje detallado de la API D1.\n";
}

echo "\nPruebas completadas.";
