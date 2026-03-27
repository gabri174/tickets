<?php
/**
 * queue_worker.php - Worker para procesar mensajes de Upstash QStash
 */

$logFile = __DIR__ . '/../queue_debug.txt';
function qLog($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

qLog("=== NUEVA PETICIÓN RECIBIDA EN QUEUE WORKER ===");
qLog("Headers: " . json_encode(getallheaders()));

ini_set('display_errors', 0);
error_reporting(E_ALL);

qLog("Incluyendo dependencias...");
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';
qLog("Dependencias incluidas OK.");

// ── 1. VERIFICACIÓN DE FIRMA HMAC (QStash) ──────────────────────────────────
function verifyQStashSignature(string $body): bool {
    qLog("Iniciando verificación de firma...");
    $expectedSecret = defined('UPSTASH_QSTASH_TOKEN') ? UPSTASH_QSTASH_TOKEN : getenv('UPSTASH_QSTASH_TOKEN');
    
    if (empty($expectedSecret)) {
        qLog("ADVERTENCIA: No se encontró UPSTASH_QSTASH_TOKEN configurado.");
        if (defined('APP_ENV') && APP_ENV === 'production') {
            qLog("Bloqueando por seguridad (production sin token).");
            return false;
        }
        return true; // Solo dev fallback
    }

    $providedSecret = $_SERVER['HTTP_UPSTASH_FORWARD_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
    qLog("Token provisionado por Cloudflare: " . (empty($providedSecret) ? "NINGUNO" : substr($providedSecret, 0, 10)."..."));
    
    if (empty($providedSecret)) {
        qLog("ERROR: El Webhook Secret enviado por Cloudflare está vacío.");
        return false;
    }

    $match = hash_equals($expectedSecret, $providedSecret);
    qLog("¿El token coincide?: " . ($match ? "SI" : "NO"));
    return $match;
}

// ── 2. RECIBIR Y VALIDAR EL REQUEST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    qLog("ERROR: Método no es POST. Saliendo.");
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$rawBody = file_get_contents('php://input');
qLog("Payload recibido: " . $rawBody);

if (!verifyQStashSignature($rawBody)) {
    qLog("ERROR CRÍTICO: verifyQStashSignature falló. Devolviendo 401.");
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}

$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($payload['action'])) {
    qLog("ERROR: JSON inválido o action vacía.");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

// ── 3. PROCESAR SEGÚN ACCIÓN ─────────────────────────────────────────────────
try {
    qLog("Instanciando Database...");
    $db = new Database();

    if ($payload['action'] === 'complete_purchase') {
        $purchaseData = $payload['purchase_data'] ?? null;
        if (!$purchaseData) {
            qLog("ERROR: Faltan datos de compra en el payload.");
            http_response_code(400);
            echo json_encode(['error' => 'Missing purchase_data']);
            exit();
        }

        qLog("Iniciando completePurchase()...");
        $result = completePurchase($purchaseData, $db);
        qLog("completePurchase() finalizó con ÉXITO. Tickets: " . count($result['tickets'] ?? []));

        http_response_code(200);
        echo json_encode([
            'status'  => 'ok',
            'tickets' => count($result['tickets'] ?? []),
            'event'   => $result['event_title'] ?? '',
        ]);

    } else {
        qLog("ERROR: Acción desconocida (" . $payload['action'] . ").");
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $payload['action']]);
    }

} catch (Throwable $e) {
    qLog("EXCEPCIÓN ATRAPADA: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
qLog("=== FIN DEL REQUEST ===");
?>
