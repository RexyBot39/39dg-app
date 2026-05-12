<?php

namespace App\Services\AiAdvisor;

use App\Models\AiPublicProduct;
use Illuminate\Support\Facades\Log;

class AdvisorFunctionHandler
{
    // Products retrieved during this request — used to validate the final response
    private array $retrievedProductIds = [];

    // Analytics state collected during function calls
    public string  $specialtyBrandInterest = '';
    public string  $lensCategoryInterest   = '';

    public function __construct(
        private readonly KnowledgeBaseLoader $kb,
    ) {}

    /**
     * Dispatch a function call from OpenAI and return the result string.
     */
    public function handle(string $functionName, array $args): string
    {
        return match ($functionName) {
            'get_lens_info'          => $this->getLensInfo($args),
            'get_frame_guide'        => $this->getFrameGuide($args),
            'get_specialty_lens_info'=> $this->getSpecialtyLensInfo($args),
            'search_products'        => $this->searchProducts($args),
            'get_product_details'    => $this->getProductDetails($args),
            'get_support_handoff'    => $this->getSupportHandoff($args),
            default                  => "Unknown function: {$functionName}. Please use get_support_handoff instead.",
        };
    }

    public function getRetrievedProductIds(): array
    {
        return $this->retrievedProductIds;
    }

    // -------------------------------------------------------------------------

    private function getLensInfo(array $args): string
    {
        $topic = $args['topic'] ?? 'lens_types';

        $category = $this->kb->getTopicCategory($topic);
        if ($category === 'lens') {
            $this->lensCategoryInterest = $topic;
        }

        return $this->kb->getLensInfo($topic);
    }

    private function getFrameGuide(array $args): string
    {
        $topic = $args['topic'] ?? 'frame_styles';

        return $this->kb->getFrameGuide($topic);
    }

    private function getSpecialtyLensInfo(array $args): string
    {
        $brand = $args['brand'] ?? '';

        if (empty($brand)) {
            return 'Please specify a specialty brand: neurolux, lumeo, ocusleep, blue495, ultimateview_hd, or transitions.';
        }

        $this->specialtyBrandInterest = $brand;

        try {
            return $this->kb->getSpecialtyLensInfo($brand);
        } catch (\Throwable $e) {
            return "Brand information not found for '{$brand}'. Please use get_support_handoff.";
        }
    }

    private function searchProducts(array $args): string
    {
        $filters = $args['filters'] ?? [];

        $query = AiPublicProduct::recommendable();

        // Apply each filter if present and non-empty
        if (!empty($filters['frame_shape'])) {
            $query->where('frame_shape', $filters['frame_shape']);
        }

        if (!empty($filters['frame_material'])) {
            $query->where('frame_material', $filters['frame_material']);
        }

        if (!empty($filters['frame_size_category'])) {
            $query->where('frame_size_category', $filters['frame_size_category']);
        }

        if (!empty($filters['lightweight'])) {
            $query->where('lightweight', true);
        }

        if (!empty($filters['progressive_friendly'])) {
            $query->where('progressive_friendly', true);
        }

        if (!empty($filters['strong_rx_friendly'])) {
            $query->where('strong_rx_friendly', true);
        }

        if (!empty($filters['budget_tier'])) {
            $query->where('budget_tier', $filters['budget_tier']);
        }

        if (!empty($filters['gender'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('gender', $filters['gender'])
                  ->orWhere('gender', 'unisex')
                  ->orWhereNull('gender');
            });
        }

        $limit   = config('ai-advisor.recommendations.max_products', 5);
        $results = $query->orderBy('price')->limit($limit)->get();

        // If filters returned nothing, do a broader search without the more
        // restrictive shape/material/size filters
        if ($results->isEmpty() && (!empty($filters['frame_shape']) || !empty($filters['frame_material']))) {
            Log::info('[AiAdvisor] No products matched strict filters; relaxing shape/material filters.');

            $fallbackQuery = AiPublicProduct::recommendable();

            if (!empty($filters['progressive_friendly'])) {
                $fallbackQuery->where('progressive_friendly', true);
            }

            if (!empty($filters['strong_rx_friendly'])) {
                $fallbackQuery->where('strong_rx_friendly', true);
            }

            if (!empty($filters['lightweight'])) {
                $fallbackQuery->where('lightweight', true);
            }

            $results = $fallbackQuery->orderBy('price')->limit($limit)->get();
        }

        if ($results->isEmpty()) {
            return json_encode([
                'found' => 0,
                'message' => 'No products matched the current filters. Consider suggesting the customer browse the full catalog or contact support.',
                'products' => [],
            ]);
        }

        $products = $results->map(function (AiPublicProduct $product) {
            $this->retrievedProductIds[] = $product->id;

            return [
                'product_id'    => (string) $product->id,
                'title'         => $product->title,
                'price'         => $product->display_price,
                'image_url'     => $product->image_url,
                'public_url'    => $product->public_url,
                'color'         => $product->color,
                'size'          => $product->size,
                'frame_shape'   => $product->frame_shape,
                'frame_material'=> $product->frame_material,
                'style_tags'    => $product->style_tags ?? [],
                'lightweight'   => $product->lightweight,
                'progressive_friendly' => $product->progressive_friendly,
            ];
        })->toArray();

        return json_encode([
            'found'    => count($products),
            'products' => $products,
        ]);
    }

    private function getProductDetails(array $args): string
    {
        $productId = $args['product_id'] ?? null;

        if (empty($productId)) {
            return json_encode(['error' => 'product_id is required.']);
        }

        $product = AiPublicProduct::where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return json_encode(['error' => 'Product not found.']);
        }

        $this->retrievedProductIds[] = $product->id;

        return json_encode($product->toAdvisorArray());
    }

    private function getSupportHandoff(array $args): string
    {
        $topic = $args['topic'] ?? 'general';

        $handoffContent = $this->kb->getSupportHandoff();

        return "SUPPORT HANDOFF REQUIRED for topic: {$topic}\n\n" . $handoffContent;
    }
}
