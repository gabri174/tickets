<?php

abstract class PaymentGateway {
    protected $config;

    public function __construct($config = []) {
        $this->config = $config;
    }

    abstract public function createPaymentRequest($amount, $description, $cancelUrl, $successUrl, $metadata = []);
    abstract public function handleCallback($data);
}
