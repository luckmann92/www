<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for interacting with the payment provider (e.g., Sberbank, YooKassa).
 * This service now supports multiple payment providers based on settings.
 */
class PaymentService
{
    protected PaymentInterface $paymentProvider;

    /**
     * PaymentService constructor.
     *
     * Initializes the payment provider based on settings.
     */
    public function __construct()
    {
        $settingsService = new SettingsService();
        $paymentSystem = $settingsService->get('payment_system', 'custom');

        switch ($paymentSystem) {
            case 'yookassa':
                $this->paymentProvider = new YookassaService();
                break;
            case 'alfabank':
                $this->paymentProvider = new AlfabankService();
                break;
            default:
                // For backward compatibility, use the original implementation
                $this->paymentProvider = $this->createDefaultProvider();
                break;
        }
    }

    /**
     * Initialize a payment for an order.
     *
     * @param int $orderId The order ID
     * @param float $amount The amount to pay
     * @param string $method The payment method ('sbp' or 'mir')
     *
     * @return array [
     *   'payment_id' => string,
     *   'payment_url' => string, // QR code URL for SBP or redirect URL for MIR
     *   'status' => string,      // 'pending', 'paid', etc.
     * ]
     */
    public function initPayment(int $orderId, float $amount, string $method): array
    {
        // For backward compatibility, map the method to the new interface
        $description = "Order #{$orderId}";
        $options = [
            'payment_method' => $this->mapPaymentMethod($method),
            'metadata' => [
                'order_id' => $orderId,
                'method' => $method,
            ],
        ];

        return $this->paymentProvider->createPayment($amount, $description, $options);
    }

    /**
     * Verify a payment status with the provider.
     *
     * @param string $paymentId The payment ID from the provider
     *
     * @return array [
     *   'status' => string, // 'pending', 'paid', 'cancelled', etc.
     *   'txn_id' => string, // Transaction ID from provider
     * ]
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $status = $this->paymentProvider->getPaymentStatus($paymentId);

        // Map the status to the expected format
        return [
            'status' => $status,
            'txn_id' => $paymentId,
        ];
    }

    /**
     * Handle a webhook from the payment provider.
     *
     * @param array $payload The raw payload from the provider
     *
     * @return bool True if the webhook was processed successfully
     */
    public function handleWebhook(array $payload): bool
    {
        return $this->paymentProvider->handleWebhook($payload);
    }

    /**
     * Create a default payment provider for backward compatibility
     *
     * @return PaymentInterface
     */
    private function createDefaultProvider(): PaymentInterface
    {
        return new class implements PaymentInterface {
            public function createPayment(float $amount, string $description, array $options = []): array
            {
                // Placeholder implementation for backward compatibility
                $paymentId = uniqid('pay_', true);
                $method = $options['metadata']['method'] ?? 'bank_card';
                $paymentUrl = $method === 'sbp'
                    ? "https://qr.nspk.ru/qr/{$paymentId}.png"
                    : "https://payment.example.com/pay/{$paymentId}";

                return [
                    'payment_id' => $paymentId,
                    'status' => 'pending',
                    'redirect_url' => $paymentUrl,
                ];
            }

            public function getPaymentStatus(string $paymentId): string
            {
                // Simulate a paid status
                return 'paid';
            }

            public function handleWebhook(array $payload): bool
            {
                $paymentId = $payload['payment_id'] ?? null;
                $status = $payload['status'] ?? null;

                if (!$paymentId || !$status) {
                    Log::error('Invalid webhook payload', $payload);
                    return false;
                }

                Log::info('Webhook processed for payment', ['payment_id' => $paymentId, 'status' => $status]);
                return true;
            }
        };
    }

    /**
     * Map the old payment method to the new one
     *
     * @param string $method
     * @return string
     */
    private function mapPaymentMethod(string $method): string
    {
        switch ($method) {
            case 'sbp':
                return 'sbp';
            case 'mir':
                return 'bank_card';
            default:
                return 'bank_card';
        }
    }
}
