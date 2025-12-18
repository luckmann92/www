<?php

namespace Tests\Unit;

use App\Services\PhotoComposeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoComposeServiceTest extends TestCase
{
    public function test_generate_method_calls_openrouter()
    {
        // Mock the HTTP client
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'data:image/jpeg;base64,dGVzdA==', // Base64 for 'test'
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Create a dummy image file
        Storage::fake('local');
        $originalPath = 'photos/originals/test.jpg';
        Storage::put($originalPath, 'dummy_image_content');

        $service = new PhotoComposeService();

        $result = $service->generate($originalPath, 'Test prompt');

        // Assert the result contains the expected keys
        $this->assertArrayHasKey('image_path', $result);
        $this->assertArrayHasKey('blurred_path', $result);

        // Assert the files were stored
        $this->assertTrue(Storage::exists($result['image_path']));
        $this->assertTrue(Storage::exists($result['blurred_path']));

        // Assert the HTTP client was called
        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer ' . config('services.openrouter.api_key'));
        });
    }
}
