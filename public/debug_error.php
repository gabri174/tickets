<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

try {
    echo "Requiring config...<br>";
    require_once 'includes/config/config.php';
    echo "Config loaded. SITE_URL: " . SITE_URL . "<br>";

    echo "Requiring functions...<br>";
    require_once 'includes/functions/functions.php';
    echo "Functions loaded.<br>";

    echo "Requiring classes...<br>";
    require_once 'includes/classes/Database.php';
    require_once 'includes/classes/PaymentGateway.php';
    require_once 'includes/classes/FinassetsGateway.php';
    echo "Classes loaded.<br>";

    echo "Connecting to DB...<br>";
    $db = new Database();
    echo "Database object created.<br>";

    $eventId = 8;
    echo "Getting event $eventId...<br>";
    $event = $db->getEventById($eventId);
    if ($event) {
        echo "Event found: " . $event['title'] . "<br>";
    } else {
        echo "Event not found.<br>";
    }

    echo "Getting ticket types...<br>";
    $ticketTypes = $db->getTicketTypesByEvent($eventId);
    echo "Ticket types retrieved: " . count($ticketTypes) . "<br>";

    echo "Debug finished successfully.<br>";
} catch (Exception $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "CAUGHT ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
}
?>
