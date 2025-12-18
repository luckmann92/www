<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

/**
 * Service responsible for communicating with the GenAPI service
 * to generate AI images based on original photos and prompts.
 */
class GenApiService implements \App\Services\PhotoComposeInterface
{
    /**
     * @var string GenAPI endpoint
     */
    protected string $endpoint;

    /**
     * @var string GenAPI API key
     */
    protected string $apiKey;

    /**
     * @var ImageManager
     */
    protected ImageManager $imageManager;

    /**
     * GenApiService constructor.
     *
     * Reads configuration from services config:
     *   services.genapi.endpoint – base URL of the API
     *   services.genapi.api_key   – secret key
     */
    public function __construct()
    {
        $settingsService = new \App\Services\SettingsService();
        $this->endpoint = rtrim($settingsService->get('genapi_endpoint', 'https://api.gen-api.ru/api/v1/networks/gemini-flash-image'), '/');
        $this->apiKey = $settingsService->get('genapi_api_key', '');

        // Initialize ImageManager with GD driver
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Generate an AI image based on an original photo and a prompt using GenAPI.
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
            'prompt' => $prompt,
            'image_urls' => [$imageUrl],
            'is_sync' => true, // Use sync mode for immediate response
            'translate_input' => true,
            'num_images' => 1,
            'output_format' => 'jpeg',
            'aspect_ratio' => '1:1' // Default aspect ratio, can be customized
        ];

        // 5. Send request to GenAPI
        $client = new Client([
            'timeout' => 120,
        ]);

        $response = $client->post($this->endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        // 6. Clean up temporary file
        Storage::disk('local')->delete($tempPath);
        Storage::disk('public')->delete($publicTempPath);

        // 7. Handle response
        if (!isset($body['request_id']) || !isset($body['status'])) {
            throw new \Exception('Invalid response from GenAPI service.');
        }

        $requestId = $body['request_id'];
        $status = $body['status'];

        // In sync mode, the image should be in the response
        if ($status === 'success' && isset($body['output'])) {
            $imageData = null;

            // Handle different output formats
            if (is_string($body['output'])) {
                // If output is a URL, fetch the image
                if (filter_var($body['output'], FILTER_VALIDATE_URL)) {
                    $imageResponse = $client->get($body['output']);
                    $imageData = $imageResponse->getBody()->getContents();
                }
                // If output is a data URI, decode it
                elseif (Str::contains($body['output'], ';base64,')) {
                    $parts = explode(';base64,', $body['output'], 2);
                    $imageData = base64_decode($parts[1]);
                }
            } elseif (is_array($body['output']) && isset($body['output'][0])) {
                // If output is an array of images, take the first one
                $outputItem = $body['output'][0];
                if (is_string($outputItem)) {
                    if (filter_var($outputItem, FILTER_VALIDATE_URL)) {
                        $imageResponse = $client->get($outputItem);
                        $imageData = $imageResponse->getBody()->getContents();
                    } elseif (Str::contains($outputItem, ';base64,')) {
                        $parts = explode(';base64,', $outputItem, 2);
                        $imageData = base64_decode($parts[1]);
                    }
                }
            }

            if ($imageData === null) {
                throw new \Exception('Failed to retrieve generated image from GenAPI response.');
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
        } else {
            // If sync mode doesn't return immediately, we can try to poll for the result
            // But for now, throw an exception if sync mode doesn't return immediately
            throw new \Exception('GenAPI service did not return a result in sync mode.');
        }
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
