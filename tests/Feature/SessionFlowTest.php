<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Session;
use App\Models\Photo;
use App\Models\Collage;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_session_flow()
    {
        // 1. Create a device
        $device = Device::factory()->create();

        // 2. Start a session
        $response = $this->postJson('/api/session/start', [
            'device_id' => $device->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sessions', [
            'device_id' => $device->id,
            'status' => 'active',
        ]);

        $sessionToken = $response->json('session_token');
        $this->assertIsString($sessionToken);

        // 3. Upload a photo (using a base64 encoded dummy image)
        $base64Image = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $response = $this->postJson('/api/photo/upload', [
            'session_token' => $sessionToken,
            'photo' => $base64Image,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('photos', [
            'session_id' => $sessionToken,
            'type' => 'original',
        ]);

        $photoId = $response->json('photo_id');
        $previewUrl = $response->json('preview_url');
        $this->assertIsInt($photoId);
        $this->assertIsString($previewUrl);

        // 4. Get available collages
        $collage = Collage::factory()->create(['is_active' => true]);
        $response = $this->getJson('/api/collages');

        $response->assertStatus(200);
        $collages = $response->json();
        $this->assertIsArray($collages);

        // 5. Create an order
        $response = $this->postJson('/api/order', [
            'session_token' => $sessionToken,
            'collage_id' => $collage->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'session_id' => $sessionToken,
            'collage_id' => $collage->id,
            'status' => 'pending',
        ]);

        $orderId = $response->json('order_id');
        $this->assertIsInt($orderId);

        // 6. Initiate payment
        $response = $this->postJson('/api/payment/init', [
            'order_id' => $orderId,
            'method' => 'sbp',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'method' => 'sbp',
            'status' => 'pending',
        ]);

        $paymentId = $response->json('payment_id');
        $paymentUrl = $response->json('payment_url');
        $this->assertIsString($paymentId);
        $this->assertIsString($paymentUrl);

        // 7. Simulate payment webhook (mark as paid)
        $response = $this->postJson('/api/payment/webhook', [
            'payment_id' => $paymentId,
            'status' => 'paid',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
        ]);

        // 8. Request delivery (e.g., via email)
        $response = $this->postJson("/api/order/{$orderId}/delivery/email", [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $orderId,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        // 9. End the session
        $response = $this->postJson('/api/session/end', [
            'session_id' => $sessionToken,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sessions', [
            'id' => $sessionToken,
            'status' => 'finished',
        ]);
    }
}
