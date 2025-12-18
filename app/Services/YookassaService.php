<?php

namespace App\Services;

use App\Services\SettingsService;

class YookassaService implements PaymentInterface
{
    protected \YooKassa\Client $client;
    protected string $shopId;
    protected string $secretKey;

    public function __construct()
    {
        $settingsService = new SettingsService();
        $this->shopId = $settingsService->get('yookassa_shop_id', '');
        $this->secretKey = $settingsService->get('yookassa_secret_key', '');

        $this->client = new \YooKassa\Client();
        $this->client->setAuth($this->shopId, $this->secretKey);
    }

    /**
     * Create a payment via Yookassa
     *
     * @param float $amount
     * @param string $description
     * @param array $options
     * @return array
     */
    public function createPayment(float $amount, string $description, array $options = []): array
    {
        $returnUrl = $options['return_url'] ?? config('app.url');
        $paymentMethod = $options['payment_method'] ?? 'bank_card';

        $request = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'description' => $description,
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'metadata' => $options['metadata'] ?? [],
        ];

        $idempotenceKey = uniqid('', true);
        $response = $this->client->createPayment($request, $idempotenceKey);

        return [
            'payment_id' => $response->getId(),
            'status' => $response->getStatus(),
            'confirmation_url' => $response->getConfirmation()->getConfirmationUrl() ?? null,
            'redirect_url' => $response->getConfirmation()->getConfirmationUrl() ?? null,
        ];
    }

    /**
     * Get payment status from Yookassa
     *
     * @param string $paymentId
     * @return string
     */
    public function getPaymentStatus(string $paymentId): string
    {
        $response = $this->client->getPaymentInfo($paymentId);
        return $response->getStatus();
    }

    /**
     * Handle webhook from Yookassa
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool
    {
        // Process the webhook notification
        $event = $payload['event'] ?? '';

        switch ($event) {
            case 'payment.succeeded':
                // Payment succeeded
                $paymentId = $payload['object']['id'] ?? null;
                $status = $payload['object']['status'] ?? null;

                if ($paymentId && $status === 'succeeded') {
                    // Update payment status in database
                    return true;
                }
                break;

            case 'payment.waiting_for_capture':
                // Payment waiting for capture
                // Handle waiting for capture logic
                break;

            case 'payment.canceled':
                // Payment canceled
                // Handle cancellation logic
                break;
        }

        return false;
    }
}
