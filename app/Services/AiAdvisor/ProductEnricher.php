<?php

namespace App\Services\AiAdvisor;

use App\Models\AiPublicProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProductEnricher
{
    private const VALID_SHAPES    = ['round', 'oval', 'rectangular', 'cat-eye', 'aviator', 'wayfarer', 'rimless', 'semi-rimless'];
    private const VALID_MATERIALS = ['titanium', 'acetate', 'metal', 'tr90', 'plastic', 'mixed'];
    private const VALID_STYLE_TAGS = ['kids', 'sport', 'computer', 'minimalist', 'retro', 'fashion', 'professional', 'reading', 'outdoor'];

    // Structured output schema — nullable fields use anyOf to satisfy strict mode
    private const RESPONSE_SCHEMA = [
        'type'        => 'json_schema',
        'json_schema' => [
            'name'   => 'product_enrichment',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['frame_shape', 'frame_material', 'style_tags', 'progressive_friendly', 'strong_rx_friendly'],
                'properties'           => [
                    'frame_shape' => [
                        'anyOf' => [
                            ['type' => 'string', 'enum' => ['round', 'oval', 'rectangular', 'cat-eye', 'aviator', 'wayfarer', 'rimless', 'semi-rimless']],
                            ['type' => 'null'],
                        ],
                    ],
                    'frame_material' => [
                        'anyOf' => [
                            ['type' => 'string', 'enum' => ['titanium', 'acetate', 'metal', 'tr90', 'plastic', 'mixed']],
                            ['type' => 'null'],
                        ],
                    ],
                    'style_tags' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string', 'enum' => ['kids', 'sport', 'computer', 'minimalist', 'retro', 'fashion', 'professional', 'reading', 'outdoor']],
                    ],
                    'progressive_friendly' => ['type' => ['boolean', 'null']],
                    'strong_rx_friendly'   => ['type' => ['boolean', 'null']],
                ],
            ],
        ],
    ];

    /**
     * Enrich a batch of unenriched products using OpenAI vision.
     *
     * @param  int $limit  Max products to process per call (keep low to avoid Railway timeouts)
     * @return array       Summary stats
     */
    public function enrichBatch(int $limit = 40): array
    {
        $apiKey = config('ai-advisor.openai.api_key');
        $model  = config('ai-advisor.openai.model', 'gpt-4o');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $products = AiPublicProduct::whereNull('ai_enriched_at')
            ->where('is_active', true)
            ->limit($limit)
            ->get();

        $enriched = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($products as $product) {
            try {
                $data = $this->callOpenAi($product, $apiKey, $model);
                $this->applyEnrichment($product, $data);
                $enriched++;

                Log::debug('[AiAdvisor] Enriched product.', [
                    'id'           => $product->id,
                    'style_tags'   => $data['style_tags'],
                    'frame_shape'  => $data['frame_shape'],
                    'frame_material' => $data['frame_material'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[AiAdvisor] Enrichment failed for product.', [
                    'product_id' => $product->id,
                    'title'      => $product->title,
                    'error'      => $e->getMessage(),
                ]);

                // Stamp ai_enriched_at so we don't retry a broken product on every batch
                $product->update(['ai_enriched_at' => now()]);
                $failed++;
                $errors[] = "Product {$product->id} ({$product->title}): {$e->getMessage()}";
            }
        }

        $remaining = AiPublicProduct::whereNull('ai_enriched_at')->where('is_active', true)->count();

        return [
            'processed' => $products->count(),
            'enriched'  => $enriched,
            'failed'    => $failed,
            'remaining' => $remaining,
            'errors'    => $errors,
        ];
    }

    // -------------------------------------------------------------------------

    private function callOpenAi(AiPublicProduct $product, string $apiKey, string $model): array
    {
        $content = [];

        // Include image if available — "low" detail is enough for shape/style detection and is cheaper
        if (!empty($product->image_url)) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $product->image_url, 'detail' => 'low'],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $this->buildPrompt($product)];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => $model,
                'messages'        => [['role' => 'user', 'content' => $content]],
                'response_format' => self::RESPONSE_SCHEMA,
                'max_tokens'      => 150,
                'temperature'     => 0.1,
            ]);

        if ($response->failed()) {
            $body  = $response->json();
            $error = $body['error']['message'] ?? "HTTP {$response->status()}";
            throw new RuntimeException("OpenAI API error: {$error}");
        }

        $body    = $response->json();
        $raw     = $body['choices'][0]['message']['content'] ?? '';
        $parsed  = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            throw new RuntimeException('Invalid JSON returned by OpenAI.');
        }

        return $parsed;
    }

    private function buildPrompt(AiPublicProduct $product): string
    {
        $known = [];
        if ($product->frame_shape !== null)         $known[] = "frame_shape is already \"{$product->frame_shape}\"";
        if ($product->frame_material !== null)       $known[] = "frame_material is already \"{$product->frame_material}\"";
        if ($product->progressive_friendly !== null) $known[] = "progressive_friendly is already set";
        if ($product->strong_rx_friendly !== null)   $known[] = "strong_rx_friendly is already set";

        $lines = [
            "You are a catalog specialist for an online eyeglass retailer.",
            "Analyze this eyeglass frame (image + text) and return the requested fields.",
            "",
            "Product: {$product->title}",
        ];

        if ($product->color)       $lines[] = "Color: {$product->color}";
        if ($product->material)    $lines[] = "Material description: {$product->material}";
        if ($product->gender)      $lines[] = "Gender target: {$product->gender}";
        if ($product->description) $lines[] = "Description: " . mb_substr($product->description, 0, 300);

        if (!empty($known)) {
            $lines[] = "";
            $lines[] = "Already known — return these as-is: " . implode('; ', $known);
        }

        $lines[] = "";
        $lines[] = "Determine:";
        $lines[] = "- frame_shape: one of " . implode(', ', self::VALID_SHAPES) . " (null if genuinely unclear)";
        $lines[] = "- frame_material: one of " . implode(', ', self::VALID_MATERIALS) . " (null if genuinely unclear)";
        $lines[] = "- style_tags: zero or more of " . implode(', ', self::VALID_STYLE_TAGS) . " that genuinely fit this frame";
        $lines[] = "- progressive_friendly: true if frame is full-rim and lens height appears adequate (≥30mm), false if too shallow or rimless-only, null if image doesn't show enough detail";
        $lines[] = "- strong_rx_friendly: true if small/medium full-rim frame (good edge control), false if large/rimless, null if unclear";

        return implode("\n", $lines);
    }

    private function applyEnrichment(AiPublicProduct $product, array $data): void
    {
        $updates = ['ai_enriched_at' => now()];

        // Only fill null fields — never overwrite values explicitly set in the JSON feed
        if ($product->frame_shape === null && !empty($data['frame_shape'])) {
            $shape = strtolower($data['frame_shape']);
            if (in_array($shape, self::VALID_SHAPES, true)) {
                $updates['frame_shape'] = $shape;
            }
        }

        if ($product->frame_material === null && !empty($data['frame_material'])) {
            $material = strtolower($data['frame_material']);
            if (in_array($material, self::VALID_MATERIALS, true)) {
                $updates['frame_material'] = $material;
            }
        }

        // Merge AI style_tags with any already present (e.g. from keyword detection)
        if (!empty($data['style_tags']) && is_array($data['style_tags'])) {
            $existing = $product->style_tags ?? [];
            $aiTags   = array_intersect($data['style_tags'], self::VALID_STYLE_TAGS); // sanitize
            $updates['style_tags'] = array_values(array_unique(array_merge($existing, $aiTags)));
        }

        if ($product->progressive_friendly === null && isset($data['progressive_friendly'])) {
            $updates['progressive_friendly'] = (bool) $data['progressive_friendly'];
        }

        if ($product->strong_rx_friendly === null && isset($data['strong_rx_friendly'])) {
            $updates['strong_rx_friendly'] = (bool) $data['strong_rx_friendly'];
        }

        // Re-derive lightweight from updated material/shape
        $shape    = $updates['frame_shape']    ?? $product->frame_shape    ?? '';
        $material = $updates['frame_material'] ?? $product->frame_material ?? '';
        $updates['lightweight'] = in_array($material, ['titanium', 'tr90'], true)
            || in_array($shape, ['rimless', 'semi-rimless'], true);

        $product->update($updates);
    }
}
