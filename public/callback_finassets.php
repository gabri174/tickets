<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();

// Log para depuración
if (function_exists('qLog')) {
    qLog("[CALLBACK] Recibida respuesta de pago. Session pending: " . (isset($_SESSION['pending_purchase']) ? 'SÍ' : 'NO'));
}

// 1. Verificar si tenemos una compra pendiente en sesión
if (!isset($_SESSION['pending_purchase'])) {
    if (function_exists('qLog')) qLog("[CALLBACK] Error: No hay compra pendiente en sesión.");
    header('Location: index.php');
    exit();
}

$purchaseData = $_SESSION['pending_purchase'];

try {
    // 2. Procesar la compra (crear tickets, descontar stock, enviar email)
    // En una integración real, aquí validaríamos que la pasarela confirma el pago (status=success)
    $result = completePurchase($purchaseData, $db);
    
    // 3. Guardar resultado en sesión para success.php
    $_SESSION['purchase_success'] = $result;
    
    // 4. Limpiar compra pendiente
    unset($_SESSION['pending_purchase']);
    
    if (function_exists('qLog')) qLog("[CALLBACK] Compra completada con éxito para: " . $purchaseData['attendees'][0]['email']);
    
    // 5. Redirigir a la página de éxito normal
    header('Location: success.php');
    exit();

} catch (Throwable $e) {
    if (function_exists('qLog')) qLog("[CALLBACK] Error crítico procesando compra: " . $e->getMessage());
    $_SESSION['email_error'] = "Error al procesar tu compra después del pago: " . $e->getMessage();
    header('Location: success.php?error=true');
    exit();
}
