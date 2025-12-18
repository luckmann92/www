<?php

namespace App\Services;

interface PaymentInterface
{
    /**
     * Create a payment
     *
     * @param float $amount
     * @param string $description
     * @param array $options
     * @return array
     */
    public function createPayment(float $amount, string $description, array $options = []): array;

    /**
     * Get payment status
     *
     * @param string $paymentId
     * @return string
     */
    public function getPaymentStatus(string $paymentId): string;

    /**
     * Handle webhook from payment provider
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool;
}
