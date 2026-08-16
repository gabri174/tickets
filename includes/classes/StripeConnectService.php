<?php

class StripeConnectService
{
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
        if ($this->secretKey === '') {
            throw new RuntimeException('Stripe no está configurado.');
        }
    }

    private function request($method, $path, array $params = [])
    {
        $ch = curl_init('https://api.stripe.com/v1' . $path);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('No se pudo conectar con Stripe: ' . $error);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Respuesta inválida de Stripe.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $data['error']['message'] ?? 'Error desconocido de Stripe.';
            throw new RuntimeException($message);
        }

        return $data;
    }

    public function createExpressAccount($email, $country = 'ES')
    {
        return $this->request('POST', '/accounts', [
            'type' => 'express',
            'country' => strtoupper($country),
            'email' => (string) $email,
            'metadata[platform]' => SITE_NAME,
        ]);
    }

    public function createOnboardingLink($accountId)
    {
        $base = rtrim(SITE_URL, '/');
        return $this->request('POST', '/account_links', [
            'account' => (string) $accountId,
            'refresh_url' => $base . '/admin/stripe-connect.php?action=onboarding',
            'return_url' => $base . '/admin/stripe-connect.php?action=return',
            'type' => 'account_onboarding',
        ]);
    }

    public function retrieveAccount($accountId)
    {
        return $this->request('GET', '/accounts/' . rawurlencode((string) $accountId));
    }

    public function createExpressLoginLink($accountId)
    {
        return $this->request('POST', '/accounts/' . rawurlencode((string) $accountId) . '/login_links');
    }
}
