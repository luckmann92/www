<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
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
        $this->apiKey = $settingsService->get('genapi_api_key', 'sk-7vmDaKgIA908LsDOWbU6hoFf1BfTYyF5ORguCOcNSqnrBLqL4IS0dcj4MOxd');

        // Initialize ImageManager with GD driver
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Generate an AI image based on original photos and a prompt using GenAPI.
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

        // 5. Build request payload (matching Postman structure)
        $payload = [
            'callback_url' => 'https://vm-8d3a6ab3.na4u.ru/',
            'prompt' => '"Сделайте кинематографическую фотографию человека, стоящего на красной плозади, неоновые огни которой отражаются в лужах',
            'is_sync' => true, // Use sync mode for immediate response
            'image_urls' => $allImageUrls,
            'translate_input' => true
        ];

         $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.env('GENAPI_API_KEY'),
            'Accept' => 'application/json'
        ];

        $curl = new CurlHttpClient();
        $response = $curl->post(
            env('GENAPI_ENDPOINT'),
            $headers,
            $payload
        );

        dd($response);
        // 6. Send request to GenAPI

        $client = new Client([
             'http_errors' => false,
        ]);




   /*     $response = $client->post(env('GENAPI_ENDPOINT'), [
                'headers' => $headers,
                'json' => $payload,
            ]);
dd($response->getBody()->getContents());*/
        $request = new Request(
            'POST',
            env('GENAPI_ENDPOINT'),
            $headers,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
       // $response = $client->sendAsync($request)->wait();
dd($client->send($request));
       // $body = json_decode($response->getBody()->getContents(), true);

        // 7. Clean up temporary file
        Storage::disk('local')->delete($tempPath);
        Storage::disk('public')->delete($publicTempPath);

        // 8. Handle response (matching actual GenAPI response structure)
        if (!isset($body['request_id'])) {
            throw new \Exception('Invalid response from GenAPI service: ' . json_encode($body));
        }

        $requestId = $body['request_id'];

        // In sync mode, the image should be in the 'images' array
        if (isset($body['images']) && is_array($body['images']) && count($body['images']) > 0) {
            $imageUrl = $body['images'][0];

            // Fetch the image from URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid image URL in GenAPI response: ' . $imageUrl);
            }

            $imageResponse = $client->get($imageUrl);
            $imageData = $imageResponse->getBody()->getContents();

            // 9. Store the generated image
            $generatedFilename = Str::uuid() . '.jpg';
            $generatedPath = "photos/results/{$generatedFilename}";
            Storage::disk('local')->put($generatedPath, $imageData);

            // 10. Create blurred version
            $image = $this->imageManager->read($imageData);
            $image = $image->blur(80); // Apply blur effect
            $blurredData = $image->toJpeg()->toString();

            $blurredFilename = Str::uuid() . '.jpg';
            $blurredPath = "photos/results/{$blurredFilename}";
            Storage::disk('local')->put($blurredPath, $blurredData);

            // 11. Return storage paths
            return [
                'image_path' => $generatedPath,
                'blurred_path' => $blurredPath,
            ];
        } else {
            // If sync mode doesn't return images, throw an exception
            throw new \Exception('GenAPI service did not return images in sync mode. Response: ' . json_encode($body));
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
