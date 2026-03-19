<?php
session_start();
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();

// En una integración real, aquí recibiríamos datos del webhook o del redirect
// Para el demo, asumiremos éxito si llegamos aquí con la sesión activa

if (!isset($_SESSION['pending_purchase'])) {
    header('Location: index.php');
    exit();
}

try {
    $purchaseData = $_SESSION['pending_purchase'];
    
    // Aquí se debería validar el estado del pago con el API de Finassets
    // Por ahora, simulamos éxito
    $paymentSuccess = true; 

    if ($paymentSuccess) {
        $result = completePurchase($purchaseData, $db);
        
        unset($_SESSION['pending_purchase']);
        $_SESSION['purchase_success'] = $result;
        
        header('Location: success.php');
        exit();
    } else {
        header('Location: buy.php?id=' . $purchaseData['event_id'] . '&error=payment_failed');
        exit();
    }
} catch (Exception $e) {
    die("Error al completar la compra: " . $e->getMessage());
}
