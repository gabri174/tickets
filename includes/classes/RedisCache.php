<?php
/**
 * RedisCache - Capa 1 de la arquitectura SRE
 * 
 * Cachea los datos de eventos y tipos de entrada en Redis (Upstash o local)
 * para evitar que peticiones masivas lleguen a MySQL.
 * 
 * Compatible con:
 * - Upstash Redis (TLS/HTTPS REST API)
 * - Redis local via Predis
 * - Fallback sin Redis (devuelve null)
 */
class RedisCache {
    private static $instance = null;
    private $client = null;
    private $useRestApi = false;
    private $restUrl = '';
    private $restToken = '';

    const EVENT_TTL        = 30;   // 30 segundos: datos del evento
    const TICKET_TYPES_TTL = 30;   // 30 segundos: tipos de entrada
    const STOCK_TTL        = 3600; // 1 hora: stock de inventario

    private function __construct() {
        $this->restUrl   = defined('REDIS_REST_URL')   ? REDIS_REST_URL   : getenv('REDIS_REST_URL');
        $this->restToken = defined('REDIS_REST_TOKEN') ? REDIS_REST_TOKEN : getenv('REDIS_REST_TOKEN');

        if ($this->restUrl && $this->restToken) {
            // Upstash REST API (HTTP) - Sin extensión PHP redis necesaria
            $this->useRestApi = true;
        } elseif (class_exists('Predis\Client')) {
            // Predis local
            try {
                $redisUrl = defined('REDIS_URL') ? REDIS_URL : getenv('REDIS_URL');
                $this->client = new Predis\Client($redisUrl ?: 'tcp://127.0.0.1:6379');
                $this->client->ping(); // test connection
            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->client = null;
            }
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * GET via Upstash REST API
     */
    private function restGet(string $key): ?string {
        $ch = curl_init("{$this->restUrl}/get/{$key}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->restToken}"],
            CURLOPT_TIMEOUT        => 1, // 1 segundo max para no bloquear
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        return $data['result'] ?? null;
    }

    /**
     * SET via Upstash REST API con TTL
     */
    private function restSet(string $key, string $value, int $ttl): bool {
        $ch = curl_init("{$this->restUrl}/set/{$key}/{$value}?ex={$ttl}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->restToken}",
                "Content-Type: application/json",
            ],
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS    => json_encode(["value" => $value, "ex" => $ttl]),
            CURLOPT_TIMEOUT       => 1,
        ]);
        curl_exec($ch);
        curl_close($ch);
        return true;
    }

    /**
     * SET específico para Upstash con body correcto
     */
    private function restSetCommand(string ...$args): mixed {
        $ch = curl_init("{$this->restUrl}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($args),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->restToken}",
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT => 2,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($raw, true);
        return $data['result'] ?? null;
    }

    // ──────────────────────────────────────────────
    // MÉTODOS PÚBLICOS DE CACHÉ
    // ──────────────────────────────────────────────

    /** Obtiene un evento de la caché. Null = cache miss */
    public function getEvent(int $eventId): ?array {
        $key = "event:{$eventId}";
        try {
            if ($this->useRestApi) {
                $val = $this->restGet($key);
            } elseif ($this->client) {
                $val = $this->client->get($key);
            } else {
                return null;
            }
            return $val ? json_decode($val, true) : null;
        } catch (Throwable $e) {
            error_log("RedisCache::getEvent error: " . $e->getMessage());
            return null;
        }
    }

    /** Guarda un evento en caché */
    public function setEvent(int $eventId, array $event): void {
        $key = "event:{$eventId}";
        $val = json_encode($event);
        try {
            if ($this->useRestApi) {
                $this->restSetCommand("SET", $key, $val, "EX", self::EVENT_TTL);
            } elseif ($this->client) {
                $this->client->setex($key, self::EVENT_TTL, $val);
            }
        } catch (Throwable $e) {
            error_log("RedisCache::setEvent error: " . $e->getMessage());
        }
    }

    /** Invalida la caché de un evento (ej. al actualizar stock) */
    public function invalidateEvent(int $eventId): void {
        try {
            if ($this->useRestApi) {
                $this->restSetCommand("DEL", "event:{$eventId}");
            } elseif ($this->client) {
                $this->client->del("event:{$eventId}");
            }
        } catch (Throwable $e) { /* silent fail */ }
    }

    /** Obtiene los tipos de entrada de un evento desde caché */
    public function getTicketTypes(int $eventId): ?array {
        $key = "ticket_types:{$eventId}";
        try {
            if ($this->useRestApi) {
                $val = $this->restGet($key);
            } elseif ($this->client) {
                $val = $this->client->get($key);
            } else {
                return null;
            }
            return $val ? json_decode($val, true) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Guarda los tipos de entrada en caché */
    public function setTicketTypes(int $eventId, array $types): void {
        $key = "ticket_types:{$eventId}";
        $val = json_encode($types);
        try {
            if ($this->useRestApi) {
                $this->restSetCommand("SET", $key, $val, "EX", self::TICKET_TYPES_TTL);
            } elseif ($this->client) {
                $this->client->setex($key, self::TICKET_TYPES_TTL, $val);
            }
        } catch (Throwable $e) {
            error_log("RedisCache::setTicketTypes error: " . $e->getMessage());
        }
    }

    /** 
     * Inicializa el contador de stock atómico en Redis.
     * Solo debe llamarse una vez al crear/actualizar el evento.
     */
    public function initStock(int $eventId, int $available, ?int $ticketTypeId = null, int $typeAvailable = 0): void {
        try {
            $eventKey = "stock:event:{$eventId}";
            if ($this->useRestApi) {
                $this->restSetCommand("SET", $eventKey, (string)$available, "EX", self::STOCK_TTL);
                if ($ticketTypeId) {
                    $typeKey = "stock:type:{$ticketTypeId}";
                    $this->restSetCommand("SET", $typeKey, (string)$typeAvailable, "EX", self::STOCK_TTL);
                }
            } elseif ($this->client) {
                $this->client->setex($eventKey, self::STOCK_TTL, $available);
                if ($ticketTypeId) {
                    $this->client->setex("stock:type:{$ticketTypeId}", self::STOCK_TTL, $typeAvailable);
                }
            }
        } catch (Throwable $e) {
            error_log("RedisCache::initStock error: " . $e->getMessage());
        }
    }

    /** Devuelve el cliente subyacente (para el InventoryLock) */
    public function getClient() {
        return $this->client;
    }

    public function isUsingRestApi(): bool { return $this->useRestApi; }
    public function getRestUrl(): string    { return $this->restUrl; }
    public function getRestToken(): string  { return $this->restToken; }

    /** True si Redis está disponible */
    public function isAvailable(): bool {
        return $this->useRestApi || $this->client !== null;
    }
}
?>
