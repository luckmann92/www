<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Models\Delivery;
use App\Models\Order;
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

        // Find the non-blurred result image (with blur_level = 0)
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->where('blur_level', 0)
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

        // Find the non-blurred result image (with blur_level = 0)
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->where('blur_level', 0)
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

        // Dispatch job to send via email
        SendEmailJob::dispatch($delivery->id);

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

        // Find the non-blurred result image (with blur_level = 0)
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->where('blur_level', 0)
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

    /**
     * Send the final photo via email using order code.
     *
     * Expected payload:
     *   - code (string) – Order code in format XXX-XXX
     *   - email (string) – Recipient's email address
     *
     * Returns:
     *   - message (string) – Success or error message
     */
    public function emailByCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|regex:/^\d{3}-\d{3}$/',
            'email' => 'required|email',
        ]);

        // Find order by code
        $order = Order::where('code', $request->input('code'))->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found with the provided code'], 404);
        }

        if ($order->status !== 'paid' && $order->status !== 'ready_blurred') {
            return response()->json(['error' => 'Order is not ready'], 400);
        }

        // Find the non-blurred result image (with blur_level = 0)
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->where('blur_level', 0)
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

        // Dispatch job to send via email
        SendEmailJob::dispatch($delivery->id);

        return response()->json(['message' => 'Email delivery initiated']);
    }
}
