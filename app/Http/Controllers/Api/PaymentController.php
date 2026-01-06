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
            'method'   => 'required|in:sbp,mir,alfapay',
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

        $response = [
            'payment_id'  => $payment->id,
            'payment_url' => $result['redirect_url'] ?? $result['payment_url'] ?? null,
        ];

        // Если это оплата через Альфа-банк QR-код, добавляем QR-код в ответ
        if ($request->input('method') === 'alfapay' && isset($result['qr_code'])) {
            $response['qr_code'] = $result['qr_code'];
            $response['qr_url'] = $result['qr_url'] ?? null;
        }

        return response()->json($response);
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

    /**
     * Check payment status.
     *
     * @param int $id Payment ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        $payment = Payment::findOrFail($id);

        // Get the status from payment provider
        $result = $this->paymentService->getPaymentStatus($payment->payment_provider_id);

        // Update payment status in database if it has changed
        if ($payment->status !== $result['status']) {
            $payment->status = $result['status'];
            $payment->save();

            // If payment is now paid, update order status
            if ($result['status'] === 'paid' && $payment->order) {
                $payment->order->status = 'paid';
                $payment->order->save();

                // Fire paid event
                event(new \App\Events\OrderPaid($payment->order));
            }
        }

        return response()->json([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $payment->status,
        ]);
    }
    /**
     * Manual payment confirmation endpoint for local development.
     * Allows to manually confirm a payment via Postman or similar tools.
     *
     * @param Request $request
     * @param int $id Payment ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request, $id)
    {
        try {
            // Only allow this in local environment
            if (!app()->environment('local')) {
                return response()->json(['error' => 'Manual payment confirmation is only available in local environment'], 403);
            }

            // Find payment by order_id instead of payment ID
            $payment = Payment::where('order_id', $id)->first();

            if (!$payment) {
                return response()->json(['error' => 'Payment not found for order ID: ' . $id], 404);
            }

            // Update payment status to paid
            $payment->status = 'paid';
            $payment->save();

            // Update order status to paid
            if ($payment->order) {
                $payment->order->status = 'paid';
                $payment->order->save();

                // Fire paid event
                event(new \App\Events\OrderPaid($payment->order));
            }

            return response()->json([
                'message' => 'Payment manually confirmed',
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status' => $payment->status,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in confirmPayment: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }
}
