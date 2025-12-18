<?php

namespace App\Services;

use GenAPI\Client;
use GenAPI\Exceptions\BadRequestException;
use GenAPI\Exceptions\BaseException;
use GenAPI\Exceptions\InternalServerError;
use GenAPI\Exceptions\NotFoundException;
use GenAPI\Exceptions\TooManyRequestsException;
use GenAPI\Exceptions\UnauthorizedException;
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
     * @var string GenAPI API key
     */
    protected string $apiKey;

    /**
     * @var Client GenAPI SDK client
     */
    protected Client $client;

    /**
     * @var ImageManager
     */
    protected ImageManager $imageManager;

    /**
     * GenApiService constructor.
     *
     * Reads configuration from settings service and initializes GenAPI SDK client.
     */
    public function __construct()
    {
        $settingsService = new \App\Services\SettingsService();
        $this->apiKey = $settingsService->get('genapi_api_key', env('GENAPI_API_KEY', ''));

        // Initialize GenAPI SDK client
        $this->client = new Client();
        $this->client->setAuthToken('sk-7vmDaKgIA908LsDOWbU6hoFf1BfTYyF5ORguCOcNSqnrBLqL4IS0dcj4MOxd');

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

      //  try {
            // 5. Prepare parameters for GenAPI
            $parameters = [
                'callback_url' => env('APP_URL'),
                'prompt' => $prompt,
                'is_sync' => true,
                'image_urls' => $allImageUrls,
                'translate_input' => true,
            ];

            Log::info('GenAPI Request', [
                'network_id' => 'gemini-flash-image',
                'prompt' => $prompt,
                'image_urls_count' => count($allImageUrls),
            ]);

            // 6. Generate image using GenAPI SDK
            $response = $this->client->createNetworkTask('gemini-flash-image', $parameters);

            Log::info('GenAPI Response', [
                'request_id' => $response['request_id'] ?? null,
                'cost' => $response['cost'] ?? null,
            ]);

            // 7. Clean up temporary files
            Storage::disk('local')->delete($tempPath);
            Storage::disk('public')->delete($publicTempPath);

            // 8. Validate response
            if (!isset($response['request_id'])) {
                throw new \Exception('Invalid response from GenAPI service: ' . json_encode($response));
            }

            // 9. In sync mode, the image should be in the 'images' array
            if (!isset($response['images']) || !is_array($response['images']) || count($response['images']) === 0) {
                throw new \Exception('GenAPI service did not return images in sync mode. Response: ' . json_encode($response));
            }

            $imageUrl = $response['images'][0];

            // 10. Validate image URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid image URL in GenAPI response: ' . $imageUrl);
            }

            // 11. Fetch the generated image
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                throw new \Exception('Failed to download generated image from: ' . $imageUrl);
            }

            // 12. Store the generated image
            $generatedFilename = Str::uuid() . '.jpg';
            $generatedPath = "photos/results/{$generatedFilename}";
            Storage::disk('local')->put($generatedPath, $imageData);

            // 13. Create blurred version
            $image = $this->imageManager->read($imageData);
            $image = $image->blur(80); // Apply blur effect
            $blurredData = $image->toJpeg()->toString();

            $blurredFilename = Str::uuid() . '.jpg';
            $blurredPath = "photos/results/{$blurredFilename}";
            Storage::disk('local')->put($blurredPath, $blurredData);

            // 14. Return storage paths
            return [
                'image_path' => $generatedPath,
                'blurred_path' => $blurredPath,
            ];
        /*} catch (BadRequestException | UnauthorizedException | NotFoundException | TooManyRequestsException | InternalServerError | BaseException $e) {
            // Clean up temporary files on GenAPI SDK error
            Storage::disk('local')->delete($tempPath);
            Storage::disk('public')->delete($publicTempPath);

            Log::error('GenAPI SDK Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response_body' => method_exists($e, 'getResponseBody') ? $e->getResponseBody() : null,
            ]);

            throw new \Exception('GenAPI request failed: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            // Clean up temporary files on general error
            Storage::disk('local')->delete($tempPath);
            Storage::disk('public')->delete($publicTempPath);

            Log::error('GenAPI Service Error', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }*/
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
        $this->client->setAuthToken($apiKey);
    }

    /**
     * Set the endpoint for the service (not used with SDK, kept for compatibility)
     *
     * @param string $endpoint
     * @return void
     */
    public function setEndpoint(string $endpoint): void
    {
        // SDK handles endpoint internally, this method is kept for interface compatibility
        Log::warning('setEndpoint called but GenAPI SDK manages endpoints internally');
    }
}
