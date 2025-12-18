<?php

namespace App\Services;

use GuzzleHttp\Client;
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
     * Generate an AI image based on an original photo and a prompt using OpenRouter.
     *
     * @param string $originalPath Path to the original uploaded photo (relative to storage/app)
     * @param string $prompt       Prompt describing the desired image (from Collage model)
     *
     * @return array [
     *   'image_path'   => string, // storage path of the generated image
     *   'blurred_path' => string, // storage path of the blurred version
     * ]
     *
     * @throws \Exception on HTTP or processing errors
     */
    public function generate(string $originalPath, string $prompt): array
    {
        // 1. Prepare the original image for sending
        $originalData = Storage::disk('local')->get($originalPath);

        // 2. Create temporary file for upload
        $tempPath = "temp/" . Str::uuid() . '.jpg';
        Storage::disk('local')->put($tempPath, $originalData);

        // 3. Get the public URL for the temporary file
        $publicTempPath = 'temp/' . basename($tempPath);
        Storage::disk('public')->put($publicTempPath, $originalData);
        $imageUrl = url('storage/' . $publicTempPath);

        // 4. Build request payload
        $payload = [
            'model' => 'google/gemini-2.5-flash-image-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl
                            ]
                        ]
                    ]
                ]
            ],
            'modalities' => ['image', 'text']
        ];

        // 5. Send request to OpenRouter
        $client = new Client([
            'timeout' => 120,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ]
        ]);

        try {
            $response = $client->post($this->endpoint, [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Обработка клиентских ошибок (4xx)
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Client error: {$e->getMessage()}. Response: {$responseBody}";
            throw new \Exception($errorMessage);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            // Обработка серверных ошибок (5xx)
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Server error: {$e->getMessage()}. Response: {$responseBody}";
            throw new \Exception($errorMessage);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Обработка других ошибок запроса
            $errorMessage = "Request error: {$e->getMessage()}";
            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            // Обработка прочих ошибок
            throw new \Exception("Error communicating with OpenRouter service: " . $e->getMessage());
        }

        // 6. Clean up temporary file
        Storage::disk('local')->delete($tempPath);
        Storage::disk('public')->delete($publicTempPath);

        // 7. Handle response
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

        // 8. Store the generated image
        $generatedFilename = Str::uuid() . '.jpg';
        $generatedPath = "photos/results/{$generatedFilename}";
        Storage::disk('local')->put($generatedPath, $imageData);

        // 9. Create blurred version
        $image = $this->imageManager->read($imageData);
        $image = $image->blur(80); // Apply blur effect
        $blurredData = $image->toJpeg()->toString();

        $blurredFilename = Str::uuid() . '.jpg';
        $blurredPath = "photos/results/{$blurredFilename}";
        Storage::disk('local')->put($blurredPath, $blurredData);

        // 10. Return storage paths
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
