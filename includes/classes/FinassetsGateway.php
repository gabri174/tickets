<?php
require_once 'PaymentGateway.php';

class FinassetsGateway extends PaymentGateway {
    private $apiUrl;
    private $apiKey;

    public function __construct($config = []) {
        parent::__construct($config);
        $this->apiUrl = $config['url'] ?? 'https://demopay.finassets.io';
        $this->apiKey = $config['key'] ?? '';
    }

    public function createPaymentRequest($amount, $description, $cancelUrl, $successUrl, $metadata = []) {
        // En una integración real de Finassets, haríamos una petición POST a su API
        // para obtener una URL de pago o un ID de transacción.
        // Dado el demo URL proporcionado, prepararemos la redirección.

        $params = [
            'api_key' => $this->apiKey,
            'amount' => $amount,
            'description' => $description,
            'cancel_url' => $cancelUrl,
            'success_url' => $successUrl,
            'metadata' => json_encode($metadata)
        ];

        return $this->apiUrl . '?' . http_build_query($params);
    }

    public function handleCallback($data) {
        // Lógica para verificar el webhook de Finassets
        // Aquí se validaría la firma y el estado del pago
        if (isset($data['status']) && $data['status'] === 'success') {
            return true;
        }
        return false;
    }
}
