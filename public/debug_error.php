<?php
// Script de depuración para identificar el Error 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Buy Error 500</h1>";

try {
    require_once '../includes/config/config.php';
    require_once '../includes/functions/functions.php';
    require_once '../includes/classes/Database.php';

    echo "<p>Carga de archivos base: OK</p>";

    $db = new Database();
    $pdo = $db->getPdo();

    if ($pdo) {
        echo "<p>Conexión a Base de Datos: OK</p>";
    } else {
        echo "<p style='color:red'>Conexión a Base de Datos: FALLIDA (PDO es null)</p>";
    }

    // Comprobar librerías críticas
    echo "<h3>Comprobación de Librerías:</h3>";
    echo "PHPMailer: " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? "OK" : "No cargada (se cargará al enviar)") . "<br>";
    echo "TCPDF2DBarcode: " . (class_exists('TCPDF2DBarcode') ? "OK" : "No cargada") . "<br>";
    echo "QRcode (PHPQRCode): " . (class_exists('QRcode') ? "OK" : "No cargada") . "<br>";

    // Intentar simular una carga de evento
    $stmt = $pdo->query("SELECT id FROM events LIMIT 1");
    $event = $stmt->fetch();
    if ($event) {
        $eventId = $event['id'];
        echo "<p>Evento de prueba ID: $eventId found</p>";
        
        // TEST trackVisit
        echo "Intentando trackVisit... ";
        try {
            $db->trackVisit($eventId, 'debug-session', 'debug-ip');
            echo "<span style='color:green'>OK</span><br>";
        } catch (Throwable $e) {
            echo "<span style='color:red'>FALLÓ: " . $e->getMessage() . "</span><br>";
        }

        // Cargar types
        $types = $db->getTicketTypesByEvent($eventId);
        echo "Tipos de tickets: " . count($types) . "<br>";
    }

    echo "<h2>Todo parece correcto a nivel de carga. El error podría estar en el POST o en una función específica.</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>EXCEPCIÓN CAPTURADA:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "Line: " . $e->getLine() . " in " . $e->getFile();
} catch (Error $e) {
    echo "<h2 style='color:red'>ERROR FATAL CAPTURADO:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "Line: " . $e->getLine() . " in " . $e->getFile();
}
?>
