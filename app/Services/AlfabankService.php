<?php

namespace App\Services;

use App\Services\SettingsService;
use GuzzleHttp\Client;

class AlfabankService implements PaymentInterface
{
    protected string $userName;
    protected string $password;
    protected string $baseUrl;
    protected Client $httpClient;

    public function __construct()
    {
        $settingsService = new SettingsService();
        $this->userName = $settingsService->get('alfabank_username', '');
        $this->password = $settingsService->get('alfabank_password', '');
        $this->baseUrl = $settingsService->get('alfabank_base_url', 'https://alfa.rbsuat.com/payment/rest');

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
    }

    /**
     * Create a payment via Alfabank
     *
     * @param float $amount
     * @param string $description
     * @param array $options
     * @return array
     */
    public function createPayment(float $amount, string $description, array $options = []): array
    {
        $returnUrl = $options['return_url'] ?? config('app.url');
        $orderId = $options['order_id'] ?? uniqid('order_', true);
        $currency = $options['currency'] ?? 'RUB';
        $customerKey = $options['customer_key'] ?? null;
        $merchantLogin = $options['merchant_login'] ?? $this->userName;

        $params = [
            'userName' => $this->userName,
            'password' => $this->password,
            'orderNumber' => $orderId,
            'amount' => $amount * 100, // Convert to kopecks
            'currencyCode' => $currency,
            'returnUrl' => $returnUrl,
        ];

        if ($customerKey) {
            $params['customerKey'] = $customerKey;
        }

        if ($merchantLogin) {
            $params['merchantLogin'] = $merchantLogin;
        }

        if (isset($options['description'])) {
            $params['orderDescription'] = $options['description'];
        }

        $response = $this->httpClient->post('/register.do', [
            'form_params' => $params,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['errorCode']) && $result['errorCode'] != 0) {
            throw new \Exception("Alfabank API error: " . ($result['errorMessage'] ?? 'Unknown error'));
        }

        return [
            'payment_id' => $result['orderId'] ?? $orderId,
            'status' => 'pending',
            'confirmation_url' => $result['formUrl'] ?? null,
            'redirect_url' => $result['formUrl'] ?? null,
        ];
    }

    /**
     * Get payment status from Alfabank
     *
     * @param string $paymentId
     * @return string
     */
    public function getPaymentStatus(string $paymentId): string
    {
        $params = [
            'userName' => $this->userName,
            'password' => $this->password,
            'orderId' => $paymentId,
        ];

        $response = $this->httpClient->post('/getOrderStatus.do', [
            'form_params' => $params,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['errorCode']) && $result['errorCode'] != 0) {
            throw new \Exception("Alfabank API error: " . ($result['errorMessage'] ?? 'Unknown error'));
        }

        // Convert Alfabank status to standard status
        $statusMap = [
            '0' => 'created',      // Заказ зарегистрирован, но не оплачен
            '1' => 'pending',      // Предавторизованная сумма захолдирована (для двухстадийных платежей)
            '2' => 'paid',         // Проведена полная авторизация суммы заказа
            '3' => 'cancelled',    // Авторизация отменена
            '4' => 'refunded',     // По транзакции была проведена операция возврата
            '5' => 'timeout',      // Истекло время ожидания оплаты
            '6' => 'declined',     // Отклонено
        ];

        $statusCode = $result['OrderStatus'] ?? '0';
        return $statusMap[$statusCode] ?? 'unknown';
    }

    /**
     * Handle webhook from Alfabank
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool
    {
        // Process the webhook notification
        $action = $payload['action'] ?? '';
        $data = $payload['data'] ?? [];

        switch ($action) {
            case 'ORDER_STATUS_CHANGED':
                $orderId = $data['orderId'] ?? null;
                $status = $data['status'] ?? null;

                if ($orderId && $status) {
                    // Update payment status in database
                    // For example: Payment::where('payment_id', $orderId)->update(['status' => $status]);
                    return true;
                }
                break;

            case 'REFUND':
                $orderId = $data['orderId'] ?? null;
                $refundAmount = $data['refundAmount'] ?? null;

                if ($orderId && $refundAmount) {
                    // Handle refund logic
                    return true;
                }
                break;
        }

        return false;
    }
}
