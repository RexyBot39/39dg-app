<?php

namespace App\Services\AiAdvisor;

class ProductTagger
{
    // -------------------------------------------------------------------------
    // Frame shape keyword maps
    // -------------------------------------------------------------------------
    private const SHAPE_PATTERNS = [
        'round'       => ['round', 'circular', 'circle'],
        'oval'        => ['oval', 'elliptical'],
        'rectangular' => ['rectangle', 'rectangular', 'square', 'angular', 'geometric'],
        'cat-eye'     => ['cat eye', 'cat-eye', 'cateye', 'butterfly'],
        'aviator'     => ['aviator', 'teardrop', 'pilot'],
        'wayfarer'    => ['wayfarer', 'wayfare'],
        'rimless'     => ['rimless', 'rim less', 'frameless'],
        'semi-rimless'=> ['semi-rimless', 'semi rimless', 'half rim', 'halfrim'],
    ];

    // -------------------------------------------------------------------------
    // Frame material keyword maps
    // -------------------------------------------------------------------------
    private const MATERIAL_PATTERNS = [
        'titanium'  => ['titanium', 'titan'],
        'acetate'   => ['acetate', 'zyl', 'zylonite', 'cellulose'],
        'metal'     => ['metal', 'stainless', 'stainless steel', 'monel', 'nickel', 'alloy', 'steel'],
        'tr90'      => ['tr-90', 'tr90', 'memory', 'flexible plastic', 'ultem', 'grilamid'],
        'plastic'   => ['plastic', 'acrylic', 'polycarbonate', 'nylon'],
        'mixed'     => ['mixed', 'combination', 'combo', 'hybrid'],
    ];

    // -------------------------------------------------------------------------
    // Style tag keyword maps
    // -------------------------------------------------------------------------
    private const STYLE_PATTERNS = [
        'kids'        => ['kids', 'children', 'child', 'youth', 'junior', 'boy', 'girl'],
        'sport'       => ['sport', 'athletic', 'active', 'running', 'cycling', 'outdoor'],
        'computer'    => ['computer', 'screen', 'gaming', 'office'],
        'minimalist'  => ['minimalist', 'minimal', 'simple', 'sleek', 'slim'],
        'retro'       => ['retro', 'vintage', 'classic', 'old school'],
        'fashion'     => ['fashion', 'trendy', 'bold', 'statement'],
        'professional'=> ['professional', 'business', 'executive', 'work'],
    ];

    // -------------------------------------------------------------------------
    // Smart glasses brand indicators
    // -------------------------------------------------------------------------
    private const SMART_GLASSES_PATTERNS = [
        'ray-ban meta', 'ray ban meta', 'meta glasses', 'smart glasses',
        'lumeo', 'frame.ai', 'bose frames', 'echelon', 'tcl nxtwear',
        'vuzix', 'xreal', 'brilliant labs',
    ];

    public function tag(array $item): array
    {
        $searchText = $this->buildSearchText($item);

        // Use explicit CSV values when present; fall back to keyword detection for XML/TSV feeds
        $item['frame_shape']    = $item['frame_shape']    ?? $this->detectShape($searchText, $item);
        $item['frame_material'] = $item['frame_material'] ?? $this->detectMaterial($searchText, $item);

        // Prefer optical lens_width_mm (CSV) over the raw size string for category accuracy
        $item['frame_size_category'] = isset($item['lens_width_mm']) && $item['lens_width_mm'] > 0
            ? $this->sizeFromMm((int) $item['lens_width_mm'])
            : $this->detectSizeCategory($item['size'] ?? '');

        // Style tags: use pre-populated array from CSV, else detect
        $item['style_tags'] = $item['style_tags'] ?? $this->detectStyleTags($searchText);

        $item['lightweight'] = $this->detectLightweight($item['frame_material'] ?? '', $item['frame_shape'] ?? '');

        // Explicit CSV booleans take precedence over heuristic detection
        $item['progressive_friendly'] = $item['progressive_friendly'] ?? $this->detectProgressiveFriendly($item);
        $item['strong_rx_friendly']   = $item['strong_rx_friendly']   ?? $this->detectStrongRxFriendly($item);

        $item['smart_glasses_relevant'] = $this->detectSmartGlasses($searchText);
        $item['blue_light_relevant']    = $this->detectBlueLightRelevant($searchText);
        $item['budget_tier']            = $this->detectBudgetTier($item['sale_price'] ?? $item['price'] ?? null);
        $item['is_recommendable']       = $this->isRecommendable($item);

        return $item;
    }

    // -------------------------------------------------------------------------

    private function buildSearchText(array $item): string
    {
        return strtolower(implode(' ', array_filter([
            $item['title']        ?? '',
            $item['description']  ?? '',
            $item['material']     ?? '',
            $item['product_type'] ?? '',
            $item['color']        ?? '',
        ])));
    }

    private function detectShape(string $text, array $item): ?string
    {
        // Check the dedicated size/material field first, then full text
        $materialField = strtolower($item['material'] ?? '');

        foreach (self::SHAPE_PATTERNS as $shape => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword) || str_contains($materialField, $keyword)) {
                    return $shape;
                }
            }
        }

        return null;
    }

    private function detectMaterial(string $text, array $item): ?string
    {
        $materialField = strtolower(trim($item['material'] ?? ''));

        // Prefer the dedicated material field from the feed
        if (!empty($materialField)) {
            foreach (self::MATERIAL_PATTERNS as $mat => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($materialField, $keyword)) {
                        return $mat;
                    }
                }
            }
        }

        // Fall back to full text search
        foreach (self::MATERIAL_PATTERNS as $mat => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $mat;
                }
            }
        }

        return null;
    }

    private function sizeFromMm(int $lensWidthMm): string
    {
        return match (true) {
            $lensWidthMm <= 48 => 'small',
            $lensWidthMm <= 53 => 'medium',
            $lensWidthMm <= 57 => 'large',
            default            => 'x-large',
        };
    }

    private function detectSizeCategory(string $sizeString): ?string
    {
        if (empty($sizeString)) {
            return null;
        }

        // Size string format: "52-18-140" — first number is lens width
        $parts = preg_split('/[-x×\s]+/', $sizeString);
        $lensWidth = isset($parts[0]) ? (int) $parts[0] : 0;

        if ($lensWidth <= 0) {
            return null;
        }

        return match (true) {
            $lensWidth <= 48 => 'small',
            $lensWidth <= 53 => 'medium',
            $lensWidth <= 57 => 'large',
            default          => 'x-large',
        };
    }

    private function detectStyleTags(string $text): array
    {
        $tags = [];

        foreach (self::STYLE_PATTERNS as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }

    private function detectLightweight(string $frameMaterial, string $frameShape): bool
    {
        return in_array($frameMaterial, ['titanium', 'tr90'], true)
            || in_array($frameShape, ['rimless', 'semi-rimless'], true);
    }

    private function detectProgressiveFriendly(array $item): bool
    {
        $sizeCategory = $item['frame_size_category'] ?? null;
        $shape        = $item['frame_shape'] ?? null;

        // Very small frames are typically not progressive-friendly
        if ($sizeCategory === 'small') {
            return false;
        }

        // Rimless frames can work but need adequate height — allow with caution
        // Cat-eye shapes with shallow lens areas are risky; exclude
        if ($shape === 'cat-eye') {
            return false;
        }

        // Medium and larger sizes in most shapes are generally progressive-friendly
        if (in_array($sizeCategory, ['medium', 'large', 'x-large'], true)) {
            return true;
        }

        // Check for explicit mention in text
        $text = $this->buildSearchText($item);
        return str_contains($text, 'progressive');
    }

    private function detectStrongRxFriendly(array $item): bool
    {
        $sizeCategory  = $item['frame_size_category'] ?? null;
        $shape         = $item['frame_shape'] ?? null;
        $frameShape    = $item['frame_shape'] ?? null;

        // Small or medium size + non-rimless full-rim frame
        $goodSize  = in_array($sizeCategory, ['small', 'medium'], true);
        $goodShape = in_array($shape, ['round', 'oval', 'rectangular'], true);
        $fullRim   = !in_array($frameShape, ['rimless', 'semi-rimless'], true);

        return $goodSize && $goodShape && $fullRim;
    }

    private function detectSmartGlasses(string $text): bool
    {
        foreach (self::SMART_GLASSES_PATTERNS as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function detectBlueLightRelevant(string $text): bool
    {
        $keywords = ['blue light', 'blue-light', 'blue495', 'computer', 'screen', 'digital', 'gaming'];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function detectBudgetTier(float|int|null $price): ?string
    {
        if ($price === null) {
            return null;
        }

        $tiers = config('ai-advisor.budget_tiers');

        foreach ($tiers as $tier => $range) {
            if ($price >= $range['min'] && $price < $range['max']) {
                return $tier;
            }
        }

        return 'premium';
    }

    private function isRecommendable(array $item): bool
    {
        // Must be in stock
        if (($item['availability'] ?? '') !== 'in stock') {
            return false;
        }

        // Must have a title
        if (empty($item['title'])) {
            return false;
        }

        // Must have a product URL
        if (empty($item['public_url'])) {
            return false;
        }

        // Must have a price
        if (empty($item['price']) && empty($item['sale_price'])) {
            return false;
        }

        return true;
    }
}
