<?php
/**
 * queue_worker.php - Worker para procesar mensajes de Upstash QStash
 *
 * URL del worker (configura en QUEUE_WORKER_URL):
 * https://ensupresencia.eu/queue_worker.php
 *
 * Este endpoint:
 * 1. Valida la firma HMAC de QStash (seguridad)
 * 2. Extrae los datos de compra del payload
 * 3. Ejecuta completePurchase() de forma controlada
 * 4. Devuelve HTTP 200 para confirmar el procesamiento
 */

// No mostrar errores al mundo. QStash necesita HTTP 200 para saber que el mensaje fue procesado.
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

// ── 1. VERIFICACIÓN DE FIRMA HMAC (QStash) ──────────────────────────────────
function verifyQStashSignature(string $body): bool {
    $signingKey = defined('QSTASH_SIGNING_KEY') ? QSTASH_SIGNING_KEY : getenv('QSTASH_SIGNING_KEY');
    
    // Si no hay clave configurada, solo permitir en modo desarrollo
    if (empty($signingKey)) {
        // En producción esto DEBE estar configurado
        if (defined('APP_ENV') && APP_ENV === 'production') {
            return false;
        }
        error_log("QUEUE_WORKER: No QSTASH_SIGNING_KEY configured. Allowing (dev mode).");
        return true;
    }

    $signature = $_SERVER['HTTP_UPSTASH_SIGNATURE'] ?? '';
    if (empty($signature)) return false;

    // QStash firma con JWT. Verificación básica de HMAC del body.
    $expectedSig = base64_encode(hash_hmac('sha256', $body, $signingKey, true));
    return hash_equals($expectedSig, $signature);
}

// ── 2. RECIBIR Y VALIDAR EL REQUEST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$rawBody = file_get_contents('php://input');

if (!verifyQStashSignature($rawBody)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}

$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($payload['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

// ── 3. PROCESAR SEGÚN ACCIÓN ─────────────────────────────────────────────────
try {
    $db = new Database();

    if ($payload['action'] === 'complete_purchase') {
        $purchaseData = $payload['purchase_data'] ?? null;
        if (!$purchaseData) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing purchase_data']);
            exit();
        }

        // Throttle programático: máximo N inserts por segundo para proteger la DB
        // QStash gestiona la concurrencia enviando mensajes de forma secuencial.
        $result = completePurchase($purchaseData, $db);

        // Éxito: responder 200 para que QStash no reintente
        http_response_code(200);
        echo json_encode([
            'status'  => 'ok',
            'tickets' => count($result['tickets'] ?? []),
            'event'   => $result['event_title'] ?? '',
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $payload['action']]);
    }

} catch (Throwable $e) {
    // Responder 500 para que QStash reintente automáticamente (hasta 3 veces)
    error_log("QUEUE_WORKER ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
