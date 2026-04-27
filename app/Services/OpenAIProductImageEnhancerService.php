<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAIProductImageEnhancerService
{
    public function enhance(UploadedFile $image, array $options = []): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $background = $options['background'] ?? 'white';
        $size = $options['size'] ?? config('services.openai.image_size', '1024x1024');
        $quality = $options['quality'] ?? config('services.openai.image_quality', 'high');
        $outputFormat = $options['output_format'] ?? 'png';

        $prompt = $this->buildPrompt($background, $options['extra_prompt'] ?? null);

        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 180,
        ]);

        $multipart = [
            [
                'name' => 'model',
                'contents' => config('services.openai.image_model', 'gpt-image-1.5'),
            ],
            [
                'name' => 'image[]',
                'contents' => fopen($image->getRealPath(), 'r'),
                'filename' => $image->getClientOriginalName(),
                'headers' => [
                    'Content-Type' => $image->getMimeType() ?: 'image/png',
                ],
            ],
            [
                'name' => 'prompt',
                'contents' => $prompt,
            ],
            [
                'name' => 'size',
                'contents' => $size,
            ],
            [
                'name' => 'quality',
                'contents' => $quality,
            ],
            [
                'name' => 'output_format',
                'contents' => $outputFormat,
            ],
        ];

        if ($background === 'transparent') {
            $multipart[] = [
                'name' => 'background',
                'contents' => 'transparent',
            ];
        }

        try {
            $response = $client->post('images/edits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
                'multipart' => $multipart,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('OpenAI image enhancement request failed: ' . $exception->getMessage());
        }

        $payload = json_decode((string) $response->getBody(), true);
        $base64Image = $payload['data'][0]['b64_json'] ?? null;

        if (!$base64Image) {
            throw new RuntimeException('OpenAI did not return an enhanced image.');
        }

        $binary = base64_decode($base64Image, true);

        if ($binary === false) {
            throw new RuntimeException('Unable to decode enhanced image.');
        }

        $extension = $outputFormat === 'jpeg' ? 'jpg' : $outputFormat;
        $path = 'ai-product-images/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'prompt' => $prompt,
        ];
    }

    private function buildPrompt(string $background, ?string $extraPrompt = null): string
    {
        $backgroundInstruction = match ($background) {
            'transparent' => 'Remove the background and return a clean transparent PNG background.',
            'studio' => 'Place the product on a clean, soft studio background with realistic commercial lighting.',
            default => 'Place the product on a pure white e-commerce background.',
        };

        $prompt = "Enhance this product photo for an e-commerce store. Improve lighting, exposure, contrast, sharpness, color balance, and overall professional product presentation. {$backgroundInstruction} Keep the exact product shape, branding, labels, materials, dimensions, and true colors. Remove dust, small blemishes, distracting shadows, and background clutter. Do not add new product features, text, logos, accessories, or misleading details.";

        if (!empty($extraPrompt)) {
            $prompt .= ' Additional instruction: ' . trim($extraPrompt);
        }

        return $prompt;
    }
}
