<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

use App\Services\PhotoComposeInterface;

/**
 * Service responsible for communicating with the OpenRouter API
 * to generate AI collages and handling the resulting images.
 */
class PhotoComposeService implements PhotoComposeInterface
{
    /**
     * @var string OpenRouter API endpoint (e.g., https://openrouter.ai/api/v1)
     */
    protected string $endpoint;

    /**
     * @var string OpenRouter API key
     */
    protected string $apiKey;

    /**
     * @var string Model to use for image generation
     */
    protected string $model;

    /**
     * PhotoComposeService constructor.
     *
     * Reads configuration from settings:
     *   openrouter.endpoint – base URL of the API
     *   openrouter.api_key   – secret key
     *   openrouter.model     – model name (defaults to gpt-image-1)
     */
    public function __construct()
    {
        $settingsService = new \App\Services\SettingsService();
        $this->endpoint = rtrim($settingsService->get('openrouter_endpoint', 'https://openrouter.ai/api/v1'), '/');
        $this->apiKey   = $settingsService->get('openrouter_api_key', '');
        $this->model    = $settingsService->get('openrouter_model', 'gpt-image-1');
    }

    /**
     * Generate an AI collage based on an original photo and a prompt.
     *
     * @param string $originalPath Path to the original uploaded photo (relative to storage/app)
     * @param string $prompt       Prompt describing the desired collage (from Collage model)
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
        // 1. Load original image and encode as Base64
        $originalData = Storage::disk('local')->get($originalPath);
        $base64Image  = base64_encode($originalData);
        $mimeType     = 'image/jpeg';
        $dataUri      = "data:$mimeType;base64,$base64Image";

        // 2. Build request payload
        $payload = [
            'model'    => $this->model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'Ты создаёшь фотореалистичные ИИ‑коллажи.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Создай коллаж: $prompt",
                ],
                [
                    'role'    => 'user',
                    'content' => $dataUri,
                ],
            ],
        ];

        // 3. Send request to OpenRouter
        $client = new Client([
            'base_uri' => $this->endpoint,
            'timeout'  => 120,
        ]);

        $response = $client->post('/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        // 4. Extract image data (handle both URL and Base64)
        $imageData = null;

        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];

            // If content is a URL, fetch it
            if (filter_var($content, FILTER_VALIDATE_URL)) {
                $imageResponse = $client->get($content);
                $imageData = $imageResponse->getBody()->getContents();
            }
            // If content is a data URI, decode it
            elseif (Str::contains($content, ';base64,')) {
                $parts = explode(';base64,', $content, 2);
                $imageData = base64_decode($parts[1]);
            }
        }

        if ($imageData === null) {
            throw new \Exception('Failed to retrieve generated image from OpenRouter response.');
        }

        // 5. Store the generated image
        $generatedFilename = Str::uuid() . '.jpg';
        $generatedPath     = "photos/results/{$generatedFilename}";
        Storage::disk('local')->put($generatedPath, $imageData);

        // 6. Store a blurred placeholder (same image for now)
        $blurredFilename = Str::uuid() . '.jpg';
        $blurredPath = "photos/results/{$blurredFilename}";
        Storage::disk('local')->put($blurredPath, $imageData);

        // 7. Return storage paths
        return [
            'image_path'   => $generatedPath,
            'blurred_path' => $blurredPath,
        ];
    }
}
