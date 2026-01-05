<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Collage;
use App\Models\Photo;
use App\Jobs\GeneratePhotoJob;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Create a new order and generate photo synchronously.
     *
     * Expected payload:
     *   - session_token (int) – ID of the session
     *   - collage_id (int) – ID of the selected collage
     *
     * Returns:
     *   - order_id
     *   - status
     */
    public function store(Request $request)
    {
        $request->validate([
            'session_token' => 'required|exists:sessions,id',
            'collage_id' => 'required|exists:collages,id',
        ]);

        $sessionId = $request->input('session_token');
        $collageId = $request->input('collage_id');

        // Retrieve collage to get price and prompt
        $collage = Collage::findOrFail($collageId);

        // Create order
        $order = Order::create([
            'session_id' => $sessionId,
            'collage_id' => $collageId,
            'price' => $collage->price,
            'status' => 'pending',
        ]);

        // Process photo generation synchronously
     //   try {
            // Dispatch job synchronously instead of queuing
            $job = new GeneratePhotoJob($order->id);
            $job->handle();

            // Update order status after successful generation
            $order->refresh(); // Reload the order to get updated status

            return response()->json([
                'order_id' => $order->id,
                'status' => $order->status,
            ], 201);
      /*  } catch (\Exception $e) {
            // In case of error, update order status and return error
            $order->status = 'failed';
            $order->save();

            return response()->json([
                'error' => 'Failed to generate photo: ' . $e->getMessage(),
                'order_id' => $order->id,
                'status' => $order->status,
            ], 500);
        }*/
    }

    /**
     * Display the specified resource.
     */
    /**
     * Show order details, including blurred preview if available.
     *
     * Returns:
     *   - order data (id, status, price, etc.)
     *   - blurred_image_url (if generated)
     */
    public function show(string $id)
    {
        $order = Order::with(['collage', 'session'])->findOrFail($id);

        // Attempt to locate a blurred photo for this order (with highest blur level)
        $blurred = Photo::where('session_id', $order->session_id)
            ->where('type', 'result')
            ->whereNotNull('blur_level')
            ->orderBy('blur_level', 'desc')
            ->first();

        $response = [
            'order' => $order,
        ];

        if ($blurred) {
            // Проверяем, содержит ли путь уже префикс /storage/
            if (str_starts_with($blurred->path, '/storage/') || str_starts_with($blurred->path, 'storage/')) {
                $response['blurred_image_url'] = ltrim($blurred->path, '/');
            } else {
                $response['blurred_image_url'] = Storage::url($blurred->path);
            }
        }

        return response()->json($response);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
