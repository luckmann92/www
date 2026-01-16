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
        // 1. Prepare the original image as base64
        $originalData = Storage::disk('local')->get($originalPath);

        // Determine MIME type from file contents
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($originalData);
        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            $mimeType = 'image/jpeg'; // fallback to jpeg
        }

        // Convert original image to base64 data URL
        $base64Original = base64_encode($originalData);
        $dataUrl = "data:{$mimeType};base64,{$base64Original}";

        // 2. Build content array with images FIRST, then text (as per OpenRouter API)
        $content = [];

        // Add original person photo first (as base64)
        $content[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => $dataUrl
            ]
        ];

        // Add additional images from collage (images_for_generation) as URLs
        foreach ($imageUrls as $imgUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imgUrl
                ]
            ];
        }

        // Add prompt text LAST
        $content[] = [
            'type' => 'text',
            'text' => $prompt
        ];

        // Build request payload
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

        // Send request to OpenRouter
        $client = new Client([
            'timeout' => 120,
            'verify' => false // Отключаем проверку SSL-сертификата
        ]);

        Log::info('OpenRouter Request', [
            'model' => 'google/gemini-2.5-flash-image-preview',
            'prompt' => $prompt,
            'original_image' => 'base64 encoded',
            'additional_images_count' => count($imageUrls),
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

        // 3. Handle response
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

        // 4. Store the generated image in public storage
        $generatedFilename = Str::uuid() . '.jpg';
        $generatedPath = "photos/results/{$generatedFilename}";
        Storage::disk('public')->put($generatedPath, $imageData);

        // 5. Create blurred version using temporary file
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

        // 6. Return storage paths
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
