<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

/**
 * Service responsible for communicating with the OpenRouter service
 * to generate AI images based on original photos and prompts.
 */
class OpenRouterService implements \App\Services\PhotoComposeInterface
{
    /**
     * @var string OpenRouter endpoint
     */
    protected string $endpoint;

    /**
     * @var string OpenRouter API key
     */
    protected string $apiKey;

    /**
     * @var ImageManager
     */
    protected ImageManager $imageManager;

    /**
     * OpenRouterService constructor.
     *
     * Reads configuration from services config:
     *   services.openrouter.endpoint – base URL of the API
     *   services.openrouter.api_key   – secret key
     */
    public function __construct()
    {
        $settingsService = new \App\Services\SettingsService();
        $this->endpoint = rtrim($settingsService->get('openrouter_endpoint', 'https://openrouter.ai/api/v1/chat/completions'), '/');
        $this->apiKey = $settingsService->get('openrouter_api_key', '');

        // Initialize ImageManager with GD driver
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Generate an AI image based on original photos and a prompt using OpenRouter.
     *
     * @param string $originalPath Path to the original uploaded photo (relative to storage/app)
     * @param string $prompt       Prompt describing the desired image (from Collage model)
     * @param array|null $imageUrls Additional image URLs to include in the request (from Collage model)
     *
     * @return array [
     *   'image_path'   => string, // storage path of the generated image
     *   'blurred_path' => string, // storage path of the blurred version
     * ]
     *
     * @throws \Exception on HTTP or processing errors
     */
    public function generate(string $originalPath, string $prompt, array $imageUrls = []): array
    {
        // 1. Prepare the original image for sending
        $originalData = Storage::disk('local')->get($originalPath);

        // 2. Create temporary file for upload
        $tempPath = "temp/" . Str::uuid() . '.jpg';
        Storage::disk('local')->put($tempPath, $originalData);

        // 3. Get the public URL for the temporary file
        $publicTempPath = 'temp/' . basename($tempPath);
        Storage::disk('public')->put($publicTempPath, $originalData);
        $originalImageUrl = url('storage/' . $publicTempPath);

        // 4. Combine original image URL with additional image URLs from collage
        $allImageUrls = array_merge([$originalImageUrl], $imageUrls);

        // 5. Build content array with all images
        $content = [
            [
                'type' => 'text',
                'text' => $prompt
            ]
        ];

        // Add all images to content
        foreach ($allImageUrls as $imgUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imgUrl
                ]
            ];
        }

        // 6. Build request payload
        $payload = [
            'model' => 'google/gemini-2.5-flash-image-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ],
            'modalities' => ['image', 'text']
        ];

        // 7. Send request to OpenRouter
        $client = new Client([
            'timeout' => 120,
            'verify' => false // Отключаем проверку SSL-сертификата
        ]);

        Log::info('OpenRouter Request', [
            'model' => 'google/gemini-2.5-flash-image-preview',
            'prompt' => $prompt,
            'image_urls_count' => count($allImageUrls),
        ]);

        try {
            $response = $client->post($this->endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('OpenRouter Response', [
                'status' => 'success',
                'choices_count' => count($body['choices'] ?? []),
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Обработка клиентских ошибок (4xx)
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Client error: {$e->getMessage()}. Response: {$responseBody}";
            Log::error('OpenRouter Client Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            // Обработка серверных ошибок (5xx)
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Server error: {$e->getMessage()}. Response: {$responseBody}";
            Log::error('OpenRouter Server Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Обработка других ошибок запроса
            $errorMessage = "Request error: {$e->getMessage()}";
            Log::error('OpenRouter Request Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            // Обработка прочих ошибок
            $errorMessage = "Error communicating with OpenRouter service: " . $e->getMessage();
            Log::error('OpenRouter Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        }

        // 8. Clean up temporary file
        Storage::disk('local')->delete($tempPath);
        Storage::disk('public')->delete($publicTempPath);

        // 9. Handle response
        if (!isset($body['choices']) || !is_array($body['choices']) || empty($body['choices'])) {
            throw new \Exception('Invalid response from OpenRouter service.');
        }

        $choice = $body['choices'][0];
        if (!isset($choice['message']) || !isset($choice['message']['images'])) {
            throw new \Exception('No images returned from OpenRouter service.');
        }

        $images = $choice['message']['images'];
        if (empty($images)) {
            throw new \Exception('No images returned from OpenRouter service.');
        }

        // Get the first image
        $imageData = null;
        $imageInfo = $images[0];
        if (isset($imageInfo['image_url']['url'])) {
            $imageSrc = $imageInfo['image_url']['url'];

            // Check if it's a data URL
            if (Str::startsWith($imageSrc, 'data:')) {
                // Decode data URL
                $parts = explode(';base64,', $imageSrc, 2);
                $imageData = base64_decode($parts[1]);
            } else {
                // Fetch image from URL
                $imageResponse = $client->get($imageSrc);
                $imageData = $imageResponse->getBody()->getContents();
            }
        }

        if ($imageData === null) {
            throw new \Exception('Failed to retrieve generated image from OpenRouter response.');
        }

        // 10. Store the generated image in public storage
        $generatedFilename = Str::uuid() . '.jpg';
        $generatedPath = "photos/results/{$generatedFilename}";
        Storage::disk('public')->put($generatedPath, $imageData);

        // 11. Create blurred version using temporary file (like GenApiService)
        $tempPath = storage_path('app/temp/' . Str::uuid() . '.jpg');
        // Создаем директорию, если она не существует
        $tempDir = dirname($tempPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        file_put_contents($tempPath, $imageData);

        // Загружаем изображение из временного файла для создания blur-версии
        $image = $this->imageManager->read($tempPath);
        $image = $image->blur(80); // Apply blur effect
        $blurredData = $image->toJpeg()->toString();

        // Удаляем временный файл
        unlink($tempPath);

        $blurredFilename = Str::uuid() . '-bl.jpg';
        $blurredPath = "photos/results/{$blurredFilename}";
        Storage::disk('public')->put($blurredPath, $blurredData);

        // 12. Return storage paths
        return [
            'image_path' => $generatedPath,
            'blurred_path' => $blurredPath,
        ];
    }

    /**
     * Set the API key for the service
     *
     * @param string $apiKey
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Set the endpoint for the service
     *
     * @param string $endpoint
     * @return void
     */
    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }
}
