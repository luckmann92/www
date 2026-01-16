<?php

namespace App\Services;

use GenAPI\Exceptions\BadRequestException;
use GenAPI\Exceptions\BaseException;
use GenAPI\Exceptions\InternalServerError;
use GenAPI\Exceptions\NotFoundException;
use GenAPI\Exceptions\TooManyRequestsException;
use GenAPI\Exceptions\UnauthorizedException;
use GuzzleHttp\Client;
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

        // Получаем настройки из БД
        $settingsService = new \App\Services\SettingsService();
        $apiKey = $settingsService->get('genapi_api_key', getenv('GENAPI_API_KEY'));
        $endpoint = $settingsService->get('genapi_endpoint', env('GENAPI_ENDPOINT'));

        $client = new Client([
            'timeout' => 30000,
            'verify' => false // Отключаем проверку SSL-сертификата
        ]);

        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => "Bearer {$apiKey}"
        ];

        $data = [
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

        try {
            $httpResponse = $client->post($endpoint, [
                'headers' => $headers,
                'json' => $data
            ]);

            $response = json_decode($httpResponse->getBody()->getContents(), true);

            Log::info('GenAPI Response', [
                'request_id' => $response['request_id'] ?? null,
                'cost' => $response['cost'] ?? null,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Client error: {$e->getMessage()}. Response: {$responseBody}";
            Log::error('GenAPI Client Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorMessage = "Server error: {$e->getMessage()}. Response: {$responseBody}";
            Log::error('GenAPI Server Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            $errorMessage = "Error communicating with GenAPI service: " . $e->getMessage();
            Log::error('GenAPI Error', ['error' => $errorMessage]);
            throw new \Exception($errorMessage);
        }

        Storage::disk('local')->delete($tempPath);
        Storage::disk('public')->delete($publicTempPath);

        if (!isset($response['request_id'])) {
            throw new \Exception('Invalid response from GenAPI service: ' . json_encode($response));
        }

        if (!isset($response['images']) || !is_array($response['images']) || count($response['images']) === 0) {
            throw new \Exception('GenAPI service did not return images in sync mode. Response: ' . json_encode($response));
        }

        $imageUrl = $response['images'][0];

        // 10. Validate image URL
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid image URL in GenAPI response: ' . $imageUrl);
        }

        $content = file_get_contents($imageUrl);

        // 12. Store the generated image
        $generatedFilename = Str::uuid() . '.jpg';
        $generatedPath = "photos/results/{$generatedFilename}";
        Storage::disk('public')->put($generatedPath, $content);

        // Создаем временный файл для обработки blur
        $tempPath = storage_path('app/temp/' . Str::uuid() . '.jpg');
        // Создаем директорию, если она не существует
        $tempDir = dirname($tempPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        file_put_contents($tempPath, $content);

        // Загружаем изображение из временного файла для создания blur-версии
        $image = $this->imageManager->read($tempPath);
        $image = $image->blur(80); // Apply blur effect
        $blurredData = $image->toJpeg()->toString();

        // Удаляем временный файл
        unlink($tempPath);

        $blurredFilename = Str::uuid() . '-bl.jpg';
        $blurredPath = "photos/results/{$blurredFilename}";
        Storage::disk('public')->put($blurredPath, $blurredData);

        // Возвращаем путь для доступа через веб
        $webBlurredPath = 'storage/' . $blurredPath;

        // Возвращаем относительные пути без префикса /storage/, чтобы Storage::url добавил его корректно
        return [
            'image_path' => $generatedPath,
            'blurred_path' => $blurredPath,
        ];
    }
}
