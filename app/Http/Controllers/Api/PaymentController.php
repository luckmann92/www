<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Initialize a payment (SBP or MIR).
     *
     * Expected payload:
     *   - order_id (int) – ID of the order to pay
     *   - method (string) – 'sbp' or 'mir'
     *
     * Returns:
     *   - payment_id
     *   - payment_url (QR code URL for SBP or redirect URL for MIR)
     */
    public function init(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'method'   => 'required|in:sbp,mir',
        ]);

        $order = Order::findOrFail($request->input('order_id'));

        // Create payment via payment service
        $result = $this->paymentService->initPayment(
            $order->id,
            $order->price,
            $request->input('method')
        );

        // Create payment record in database
        $payment = Payment::create([
            'order_id' => $order->id,
            'method'   => $request->input('method'),
            'amount'   => $order->price,
            'payment_provider_id' => $result['payment_id'], // Store the provider's payment ID
            'status'   => $result['status'] ?? 'pending',
        ]);

        return response()->json([
            'payment_id'  => $payment->id,
            'payment_url' => $result['redirect_url'] ?? $result['payment_url'] ?? null,
        ]);
    }

    /**
     * Webhook endpoint called by the payment provider.
     *
     * Expected payload (example):
     *   - payment_id (int)
     *   - status (string) – 'paid' or other statuses
     *
     * Updates payment and order status, fires OrderPaid event.
     */
    public function webhook(Request $request)
    {
        // Process webhook through payment service
        $processed = $this->paymentService->handleWebhook($request->all());

        if ($processed) {
            return response()->json(['message' => 'Webhook processed']);
        }

        return response()->json(['message' => 'Webhook not processed'], 400);
    }
}
