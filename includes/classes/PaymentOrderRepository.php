<?php

class PaymentOrderRepository
{
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) (defined('D1_API_URL') ? D1_API_URL : ''), '/');
        $this->apiToken = (string) (defined('D1_API_TOKEN') ? D1_API_TOKEN : '');
    }

    private function call($sql, array $params = [], $method = 'all')
    {
        if ($this->apiUrl === '' || $this->apiToken === '') {
            throw new RuntimeException('D1 no está configurado.');
        }

        $payload = json_encode(['sql' => $sql, 'params' => array_values($params), 'method' => $method]);
        if ($payload === false) throw new RuntimeException('No se pudo serializar la consulta.');

        $ch = curl_init($this->apiUrl . '/api/query');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiToken,
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException('Error de conexión con D1: ' . $error);
        $data = json_decode($raw, true);
        if ($status !== 200 || !is_array($data) || empty($data['success'])) {
            throw new RuntimeException(is_array($data) ? ($data['message'] ?? 'Error D1') : 'Respuesta D1 inválida');
        }

        $result = $data['data'] ?? [];
        if ($method === 'first') return $result['results'][0] ?? $result;
        if ($method === 'all') return $result['results'] ?? [];
        return $result;
    }

    public function createOrder($token, $eventId, $adminId, $name, $email, $phone, $amountCents, $currency, $stripeAccountId)
    {
        $result = $this->call(
            'INSERT INTO payment_orders (public_token,event_id,admin_id,attendee_name,attendee_email,attendee_phone,amount_cents,currency,stripe_account_id) VALUES (?,?,?,?,?,?,?,?,?)',
            [$token, (int)$eventId, (int)$adminId, trim($name), mb_strtolower(trim($email)), trim($phone), (int)$amountCents, strtolower($currency), $stripeAccountId],
            'run'
        );
        return (int)($result['meta']['last_row_id'] ?? 0);
    }

    public function addItem($orderId, $ticketTypeId, $quantity, $unitAmountCents)
    {
        $this->call(
            'INSERT INTO payment_order_items (order_id,ticket_type_id,quantity,unit_amount_cents) VALUES (?,?,?,?)',
            [(int)$orderId, $ticketTypeId ? (int)$ticketTypeId : null, (int)$quantity, (int)$unitAmountCents],
            'run'
        );
    }

    public function getOrderById($orderId)
    {
        return $this->call('SELECT * FROM payment_orders WHERE id=?', [(int)$orderId], 'first');
    }

    public function getOrderByToken($token)
    {
        return $this->call('SELECT * FROM payment_orders WHERE public_token=?', [(string)$token], 'first');
    }

    public function getOrderBySession($sessionId)
    {
        return $this->call('SELECT * FROM payment_orders WHERE stripe_checkout_session_id=?', [(string)$sessionId], 'first');
    }

    public function getItems($orderId)
    {
        return $this->call('SELECT * FROM payment_order_items WHERE order_id=? ORDER BY id ASC', [(int)$orderId]);
    }

    public function markCheckoutCreated($orderId, $sessionId, $paymentIntentId = null)
    {
        return $this->call('UPDATE payment_orders SET stripe_checkout_session_id=?, stripe_payment_intent_id=COALESCE(?,stripe_payment_intent_id), status=\'checkout_created\' WHERE id=? AND status=\'pending\'', [$sessionId, $paymentIntentId, (int)$orderId], 'run');
    }

    public function markPaid($orderId, $sessionId = null, $paymentIntentId = null)
    {
        return $this->call("UPDATE payment_orders SET status='paid', stripe_checkout_session_id=COALESCE(?,stripe_checkout_session_id), stripe_payment_intent_id=COALESCE(?,stripe_payment_intent_id), paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP) WHERE id=? AND status IN ('pending','checkout_created','paid')", [$sessionId, $paymentIntentId, (int)$orderId], 'run');
    }

    public function markFulfilled($orderId)
    {
        return $this->call("UPDATE payment_orders SET status='fulfilled', fulfillment_error=NULL WHERE id=? AND status IN ('paid','fulfilling','fulfilled')", [(int)$orderId], 'run');
    }

    public function markFulfilling($orderId)
    {
        return $this->call("UPDATE payment_orders SET status='fulfilling' WHERE id=? AND status='paid'", [(int)$orderId], 'run');
    }

    public function markFailed($orderId, $message)
    {
        return $this->call("UPDATE payment_orders SET status='failed', fulfillment_error=? WHERE id=? AND status NOT IN ('fulfilled')", [mb_substr((string)$message, 0, 1000), (int)$orderId], 'run');
    }
}
