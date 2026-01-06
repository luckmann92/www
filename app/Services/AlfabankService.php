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
        $this->userName = env('ALFABANK_USERNAME', '');
        $this->password = env('ALFABANK_PASSWORD', '');
        $this->baseUrl = env('ALFABANK_BASE_URL', 'https://alfa.rbsuat.com/payment/rest');

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
        // Проверяем, если это локальная разработка, используем тестовый режим
        if (app()->environment('local')) {
            // Возвращаем статичный QR-код для локальной разработки
            $qrCodePath = resource_path('img/qr-code.gif');
            if (file_exists($qrCodePath)) {
                $qrCodeContent = file_get_contents($qrCodePath);
                $qrCodeBase64 = base64_encode($qrCodeContent);

                $orderId = $options['order_id'] ?? uniqid('order_', true);

                return [
                    'payment_id' => $orderId,
                    'status' => 'pending',
                    'confirmation_url' => null,
                    'qr_code' => $qrCodeBase64,
                    'qr_url' => null,
                ];
            } else {
                // Если файл не найден, выбрасываем исключение
                throw new \Exception("QR code image not found at: " . $qrCodePath);
            }
        }

        $returnUrl = $options['return_url'] ?? env('ALFABANK_RETURN_URL', config('app.url'));
        $failUrl = $options['fail_url'] ?? env('ALFABANK_FAIL_URL', config('app.url'));
        $orderId = $options['order_id'] ?? uniqid('order_', true);
        $currency = $options['currency'] ?? '643'; // 643 is code for RUB
        $customerKey = $options['customer_key'] ?? null;
        $merchantLogin = $options['merchant_login'] ?? $this->userName;
        $isMobile = $options['is_mobile'] ?? false;
        $jsonParams = $options['json_params'] ?? null;

        $params = [
            'userName' => $this->userName,
            'password' => $this->password,
            'orderNumber' => $orderId,
            'amount' => (int)($amount * 100), // Convert to kopecks
            'currency' => $currency,
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
            'description' => $description ?? 'Оплата заказа #' . $orderId,
        ];

        if ($customerKey) {
            $params['customerKey'] = $customerKey;
        }

        if ($merchantLogin) {
            $params['merchantLogin'] = $merchantLogin;
        }

        // Если пользователь на мобильном
        if ($isMobile) {
            $params['pageView'] = 'MOBILE';
        }

        // Добавляем jsonParams если они есть
        if ($jsonParams) {
            $params['jsonParams'] = json_encode($jsonParams);
        }

        $response = $this->httpClient->post('/register.do', [
            'form_params' => $params,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['errorCode']) && $result['errorCode'] != 0) {
            throw new \Exception("Alfabank API error: " . ($result['errorMessage'] ?? 'Unknown error'));
        }

        // Сохраняем orderId из ответа Альфа-банка
        $alfabankOrderId = $result['orderId'] ?? null;

        // Если успешно зарегистрировали заказ, генерируем QR-код
        if ($alfabankOrderId) {
            $qrCodeData = $this->generateQrCode($alfabankOrderId, $options);

            return [
                'payment_id' => $alfabankOrderId,
                'status' => 'pending',
                'confirmation_url' => $result['formUrl'] ?? null,
                'qr_code' => $qrCodeData['qr_code'] ?? null,
                'qr_url' => $qrCodeData['qr_url'] ?? null,
            ];
        }

        return [
            'payment_id' => $alfabankOrderId ?? $orderId,
            'status' => 'pending',
            'confirmation_url' => $result['formUrl'] ?? null,
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

    /**
     * Generate QR code for payment via Alfabank
     *
     * @param string $orderId Order ID in Alfabank system
     * @param array $options Additional options
     * @return array
     */
    public function generateQrCode(string $orderId, array $options = []): array
    {
        $redirectUrl = $options['redirect_url'] ?? env('ALFABANK_RETURN_URL', config('app.url') . '/payment/callback');
        $qrWidth = $options['qr_width'] ?? 300;
        $qrHeight = $options['qr_height'] ?? 300;

        $params = [
            'userName' => $this->userName,
            'password' => $this->password,
            'mdOrder' => $orderId,
            'redirectUrl' => $redirectUrl, // Обязательный URL возврата после оплаты в приложении банка
            'qrFormat' => 'image', // Получаем QR в Base64
            'qrWidth' => $qrWidth,
            'qrHeight' => $qrHeight,
        ];

        $response = $this->httpClient->post('/qr/register.do', [
            'form_params' => $params,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['errorCode']) && $result['errorCode'] != 0) {
            throw new \Exception("Alfabank QR API error: " . ($result['errorMessage'] ?? 'Unknown error'));
        }

        return [
            'qr_code' => $result['renderedQr'] ?? null, // QR в формате PNG, закодированный в Base64
            'qr_url' => $result['payload'] ?? null,     // URL QR-кода (можно отобразить как ссылку)
        ];
    }
}
