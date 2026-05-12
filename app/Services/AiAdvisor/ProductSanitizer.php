<?php

namespace App\Services\AiAdvisor;

class ProductSanitizer
{
    private array $blockedFields;
    private array $allowedAvailability;

    public function __construct()
    {
        $this->blockedFields        = config('ai-advisor.sanitize.blocked_fields', []);
        $this->allowedAvailability  = config('ai-advisor.sanitize.allowed_availability', ['in stock', 'preorder']);
    }

    /**
     * Sanitize a batch of parsed feed rows.
     * Returns only the rows that pass all safety checks, with blocked fields removed.
     *
     * @param  array[] $items   Raw parsed feed rows
     * @return array{items: array[], skipped: int, warnings: string[]}
     */
    public function sanitizeBatch(array $items): array
    {
        $clean    = [];
        $skipped  = 0;
        $warnings = [];

        foreach ($items as $item) {
            $result = $this->sanitizeOne($item);

            if ($result === null) {
                $skipped++;
                continue;
            }

            ['item' => $sanitized, 'warnings' => $itemWarnings] = $result;

            if (!empty($itemWarnings)) {
                foreach ($itemWarnings as $warning) {
                    $warnings[] = "[{$sanitized['feed_product_id']}] {$warning}";
                }
            }

            $clean[] = $sanitized;
        }

        return [
            'items'    => $clean,
            'skipped'  => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{item: array, warnings: string[]}|null  null = drop this product
     */
    private function sanitizeOne(array $item): ?array
    {
        $warnings = [];

        // Must have a product ID
        if (empty($item['feed_product_id'])) {
            return null;
        }

        // Must have a title
        if (empty($item['title'])) {
            return null;
        }

        // Must have a valid public URL
        if (empty($item['public_url'])) {
            return null;
        }

        // Remove any blocked fields the feed might have included
        foreach ($this->blockedFields as $field) {
            unset($item[$field]);
        }

        // Enforce allowed availability values; default to out of stock
        if (!in_array($item['availability'] ?? '', $this->allowedAvailability, true)) {
            $item['availability'] = 'out of stock';
        }

        // Enforce public URL is on the 39DollarGlasses domain
        if (!$this->isAllowedDomain($item['public_url'])) {
            $warnings[] = "Skipped non-39DG URL: {$item['public_url']}";
            return null;
        }

        // Enforce image URL domain if present
        if (!empty($item['image_url']) && !$this->isAllowedImageDomain($item['image_url'])) {
            $warnings[] = "Cleared non-approved image URL: {$item['image_url']}";
            $item['image_url'] = null;
        }

        // Truncate fields to safe lengths
        $item['title']       = mb_substr($item['title'], 0, 255);
        $item['description'] = !empty($item['description']) ? mb_substr($item['description'], 0, 2000) : null;
        $item['color']       = !empty($item['color'])       ? mb_substr($item['color'], 0, 100)        : null;
        $item['size']        = !empty($item['size'])        ? mb_substr($item['size'], 0, 50)           : null;
        $item['brand']       = !empty($item['brand'])       ? mb_substr($item['brand'], 0, 100)         : null;

        // Sanitize price to non-negative
        if (isset($item['price']) && (float) $item['price'] < 0) {
            $item['price'] = null;
            $warnings[] = 'Cleared negative price.';
        }

        if (isset($item['sale_price']) && (float) $item['sale_price'] < 0) {
            $item['sale_price'] = null;
        }

        // Validate gender to known values
        if (!empty($item['gender'])) {
            $item['gender'] = in_array($item['gender'], ['male', 'female', 'unisex'], true)
                ? $item['gender']
                : null;
        }

        return ['item' => $item, 'warnings' => $warnings];
    }

    private function isAllowedDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === false || $host === null) {
            return false;
        }

        $allowed = config('ai-advisor.sanitize.allowed_domains', [
            '39dollarglasses.com',
            'www.39dollarglasses.com',
        ]);

        return in_array(strtolower($host), $allowed, true);
    }

    private function isAllowedImageDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === false || $host === null) {
            return false;
        }

        $allowed = config('ai-advisor.sanitize.allowed_image_domains', [
            '39dollarglasses.com',
            'www.39dollarglasses.com',
            'cdn.39dollarglasses.com',
            'images.39dollarglasses.com',
        ]);

        return in_array(strtolower($host), $allowed, true);
    }
}
