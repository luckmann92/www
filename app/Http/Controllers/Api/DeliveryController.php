<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    /**
     * Send the final photo via Telegram.
     *
     * Expected payload:
     *   - order_id (int) – ID of the paid order
     *
     * Returns:
     *   - message (string) – Success or error message
     */
    public function telegram(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->input('order_id'));

        if ($order->status !== 'paid') {
            return response()->json(['error' => 'Order is not paid'], 400);
        }

        // Find the non-blurred result image
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->whereNull('blur_level')
            ->first();

        if (!$resultPhoto) {
            return response()->json(['error' => 'Result photo not found'], 404);
        }

        // Create a delivery record
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'channel'  => 'telegram',
            'meta'     => [
                'status' => 'pending',
                'file_url' => Storage::url($resultPhoto->path),
            ],
            'status'   => 'pending',
        ]);

        // Dispatch job to send via Telegram (placeholder)
        // SendTelegramJob::dispatch($delivery->id);

        return response()->json(['message' => 'Telegram delivery initiated']);
    }

    /**
     * Send the final photo via email.
     *
     * Expected payload:
     *   - order_id (int) – ID of the paid order
     *   - email (string) – Recipient's email address
     *
     * Returns:
     *   - message (string) – Success or error message
     */
    public function email(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'email'    => 'required|email',
        ]);

        $order = Order::findOrFail($request->input('order_id'));

        if ($order->status !== 'paid') {
            return response()->json(['error' => 'Order is not paid'], 400);
        }

        // Find the non-blurred result image
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->whereNull('blur_level')
            ->first();

        if (!$resultPhoto) {
            return response()->json(['error' => 'Result photo not found'], 404);
        }

        // Create a delivery record
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'channel'  => 'email',
            'meta'     => [
                'status' => 'pending',
                'to'     => $request->input('email'),
                'file_path' => $resultPhoto->path,
            ],
            'status'   => 'pending',
        ]);

        // Dispatch job to send via email (placeholder)
        // SendEmailJob::dispatch($delivery->id);

        return response()->json(['message' => 'Email delivery initiated']);
    }

    /**
     * Send the final photo to a local printer.
     *
     * Expected payload:
     *   - order_id (int) – ID of the paid order
     *
     * Returns:
     *   - message (string) – Success or error message
     */
    public function print(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->input('order_id'));

        if ($order->status !== 'paid') {
            return response()->json(['error' => 'Order is not paid'], 400);
        }

        // Find the non-blurred result image
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->whereNull('blur_level')
            ->first();

        if (!$resultPhoto) {
            return response()->json(['error' => 'Result photo not found'], 404);
        }

        // Create a delivery record
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'channel'  => 'print',
            'meta'     => [
                'status' => 'pending',
                'file_path' => $resultPhoto->path,
            ],
            'status'   => 'pending',
        ]);

        // Dispatch job to send to printer (placeholder)
        // PrintJob::dispatch($delivery->id);

        return response()->json(['message' => 'Print delivery initiated']);
    }
}
