<?php
/**
 * QueueService - Capa 2: Cola de Mensajes de Escritura
 *
 * Envía datos de compras confirmadas a Upstash QStash para procesamiento
 * asíncrono y controlado. Esto evita bloqueos de DB bajo alta concurrencia.
 *
 * Fallback automático: si QStash no está configurado, ejecuta completePurchase()
 * de forma directa (para desarrollo local o configuraciones sin cola).
 */
class QueueService {
    private $qstashUrl;
    private $qstashToken;
    private $workerUrl;
    private bool $enabled;

    public function __construct() {
        $this->qstashToken = defined('UPSTASH_QSTASH_TOKEN') ? UPSTASH_QSTASH_TOKEN : getenv('UPSTASH_QSTASH_TOKEN');
        $this->workerUrl   = defined('QUEUE_WORKER_URL')     ? QUEUE_WORKER_URL     : getenv('QUEUE_WORKER_URL');
        $this->qstashUrl   = 'https://qstash.upstash.io/v2/publish/';
        $this->enabled     = !empty($this->qstashToken) && !empty($this->workerUrl);
    }

    /**
     * Envía los datos de una compra confirmada a la cola.
     *
     * @param array $purchaseData Los datos completos de la compra
     * @return array ['queued' => bool, 'message_id' => string|null]
     */
    public function enqueuePurchase(array $purchaseData): array {
        if (!$this->enabled) {
            // Sin configuración de cola → retornar para procesamiento directo
            return ['queued' => false, 'message_id' => null];
        }

        $payload = json_encode([
            'action'        => 'complete_purchase',
            'purchase_data' => $purchaseData,
            'enqueued_at'   => time(),
            'attempt'       => 1,
        ]);

        $ch = curl_init($this->qstashUrl . urlencode($this->workerUrl));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->qstashToken}",
                "Content-Type: application/json",
                "Upstash-Retries: 3",          // 3 reintentos automáticos
                "Upstash-Retry-Delay: 2s",     // Espera 2s entre reintentos
                "Upstash-Timeout: 30s",        // Timeout del worker
            ],
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $response = json_decode($raw, true);
            return [
                'queued'     => true,
                'message_id' => $response['messageId'] ?? null,
            ];
        }

        error_log("QueueService::enqueuePurchase failed. HTTP {$httpCode}: {$raw}");
        return ['queued' => false, 'message_id' => null];
    }

    /** ¿Está la cola habilitada con credenciales válidas? */
    public function isEnabled(): bool {
        return $this->enabled;
    }
}
?>
