<?php
/**
 * InventoryLock - Capa 3: Semáforo de Inventario Atómico
 *
 * Usa scripts Lua en Redis para decrementar el stock de forma atómica.
 * Esto evita la sobreventa cuando múltiples usuarios compran al mismo tiempo.
 *
 * Compatibilidad:
 * - Upstash Redis REST API (sin extensión PHP)
 * - Predis local
 */
class InventoryLock {
    private $cache; // RedisCache instance

    // Script Lua: verifica que hay stock y lo resta atómicamente en una sola operación
    const LUA_DECREMENT = <<<'LUA'
local key = KEYS[1]
local qty = tonumber(ARGV[1])
local current = tonumber(redis.call('GET', key))
if current == nil then return -1 end
if current < qty then return -2 end
return redis.call('DECRBY', key, qty)
LUA;

    public function __construct(RedisCache $cache) {
        $this->cache = $cache;
    }

    /**
     * Decrementa el stock de forma atómica.
     *
     * @return int El stock restante, -1 si la clave no existe, -2 si no hay suficiente stock
     */
    public function decrementStock(string $key, int $quantity): int {
        try {
            if ($this->cache->isUsingRestApi()) {
                return $this->decrementViaRest($key, $quantity);
            }
            $client = $this->cache->getClient();
            if (!$client) return 0; // Fallback: dejar pasar, MySQL lo validará
            // Ejecutar Lua via Predis
            $result = $client->eval(self::LUA_DECREMENT, 1, $key, $quantity);
            return (int)$result;
        } catch (Throwable $e) {
            error_log("InventoryLock::decrementStock error: " . $e->getMessage());
            return 0; // Fallback: dejar pasar
        }
    }

    /**
     * Decremento atómico vía Upstash REST API (ejecuta script Lua via /eval)
     */
    private function decrementViaRest(string $key, int $quantity): int {
        $url     = $this->cache->getRestUrl();
        $token   = $this->cache->getRestToken();

        // Upstash soporta EVALSHA y EVAL vía REST
        $payload = json_encode(["EVAL", self::LUA_DECREMENT, "1", $key, (string)$quantity]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT => 2,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($raw, true);
        return (int)($data['result'] ?? 0);
    }

    /**
     * Restaura el stock si el pago fue cancelado o falló (compensación)
     */
    public function restoreStock(string $key, int $quantity): void {
        try {
            if ($this->cache->isUsingRestApi()) {
                $url   = $this->cache->getRestUrl();
                $token = $this->cache->getRestToken();
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode(["INCRBY", $key, (string)$quantity]),
                    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "Content-Type: application/json"],
                    CURLOPT_TIMEOUT        => 2,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } else {
                $client = $this->cache->getClient();
                if ($client) $client->incrby($key, $quantity);
            }
        } catch (Throwable $e) {
            error_log("InventoryLock::restoreStock error: " . $e->getMessage());
        }
    }

    /**
     * Helpers para construir las claves de stock
     */
    public static function eventKey(int $eventId): string      { return "stock:event:{$eventId}"; }
    public static function typeKey(int $ticketTypeId): string  { return "stock:type:{$ticketTypeId}"; }
}
?>
