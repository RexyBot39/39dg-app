<?php

namespace App\Services\AiAdvisor;

use RuntimeException;

class FeedParser
{
    // Google Shopping XML namespace
    private const G_NS = 'http://base.google.com/ns/1.0';

    // Valid enum values for frame_shape and frame_material (mirrors TOOL_DEFINITIONS in AdvisorService)
    private const VALID_SHAPES    = ['round', 'oval', 'rectangular', 'cat-eye', 'aviator', 'wayfarer', 'rimless', 'semi-rimless'];
    private const VALID_MATERIALS = ['titanium', 'acetate', 'metal', 'tr90', 'plastic', 'mixed'];

    public function parse(string $content, string $format): array
    {
        return match ($format) {
            'xml'  => $this->parseXml($content),
            'tsv'  => $this->parseTsv($content),
            'csv'  => $this->parseCsv($content),
            default => throw new RuntimeException("Unsupported feed format: {$format}"),
        };
    }

    private function parseXml(string $content): array
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = array_map(fn ($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException('XML parse error: ' . implode('; ', $errors));
        }

        libxml_clear_errors();

        $items = [];

        // Standard RSS/Atom Google Shopping feed: <rss><channel><item>...</item></channel></rss>
        $channel = $xml->channel ?? $xml;
        foreach ($channel->item as $item) {
            $items[] = $this->normalizeXmlItem($item);
        }

        return $items;
    }

    private function normalizeXmlItem(\SimpleXMLElement $item): array
    {
        $g = $item->children(self::G_NS);

        // title is a standard RSS element; description may be in both namespaces
        $title       = (string) ($item->title ?? $g->title ?? '');
        $description = (string) ($item->description ?? $g->description ?? '');
        $link        = (string) ($item->link ?? $g->link ?? '');

        // Parse price strings like "39.00 USD"
        $price     = $this->parsePriceString((string) ($g->price ?? ''));
        $salePrice = $this->parsePriceString((string) ($g->sale_price ?? ''));

        return [
            'feed_product_id'           => (string) ($g->id ?? ''),
            'title'                     => $this->cleanString($title),
            'description'               => $this->cleanString($description),
            'public_url'                => $this->cleanUrl($link),
            'image_url'                 => $this->cleanUrl((string) ($g->image_link ?? '')),
            'price'                     => $price,
            'sale_price'                => $salePrice,
            'availability'              => strtolower(trim((string) ($g->availability ?? 'out of stock'))),
            'brand'                     => $this->cleanString((string) ($g->brand ?? '')),
            'product_type'              => $this->cleanString((string) ($g->product_type ?? '')),
            'google_product_category'   => $this->cleanString((string) ($g->google_product_category ?? '')),
            'color'                     => $this->cleanString((string) ($g->color ?? '')),
            'size'                      => $this->cleanString((string) ($g->size ?? '')),
            'gender'                    => strtolower(trim((string) ($g->gender ?? ''))),
            'material'                  => $this->cleanString((string) ($g->material ?? '')),
            'condition'                 => strtolower(trim((string) ($g->condition ?? 'new'))),
        ];
    }

    private function parseTsv(string $content): array
    {
        $lines = explode("\n", trim($content));

        if (count($lines) < 2) {
            throw new RuntimeException('TSV feed has no data rows.');
        }

        $headers = str_getcsv(array_shift($lines), "\t");
        $headers = array_map('trim', $headers);

        $items = [];

        foreach ($lines as $lineNumber => $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line, "\t");

            if (count($values) !== count($headers)) {
                continue; // skip malformed rows
            }

            $row = array_combine($headers, $values);
            $items[] = $this->normalizeTsvRow($row);
        }

        return $items;
    }

    private function normalizeTsvRow(array $row): array
    {
        $price     = $this->parsePriceString($row['price'] ?? '');
        $salePrice = $this->parsePriceString($row['sale_price'] ?? '');

        return [
            'feed_product_id'           => trim($row['id'] ?? ''),
            'title'                     => $this->cleanString($row['title'] ?? ''),
            'description'               => $this->cleanString($row['description'] ?? ''),
            'public_url'                => $this->cleanUrl($row['link'] ?? ''),
            'image_url'                 => $this->cleanUrl($row['image_link'] ?? ''),
            'price'                     => $price,
            'sale_price'                => $salePrice,
            'availability'              => strtolower(trim($row['availability'] ?? 'out of stock')),
            'brand'                     => $this->cleanString($row['brand'] ?? ''),
            'product_type'              => $this->cleanString($row['product_type'] ?? ''),
            'google_product_category'   => $this->cleanString($row['google_product_category'] ?? ''),
            'color'                     => $this->cleanString($row['color'] ?? ''),
            'size'                      => $this->cleanString($row['size'] ?? ''),
            'gender'                    => strtolower(trim($row['gender'] ?? '')),
            'material'                  => $this->cleanString($row['material'] ?? ''),
            'condition'                 => strtolower(trim($row['condition'] ?? 'new')),
        ];
    }

    private function parseCsv(string $content): array
    {
        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = explode("\n", trim($content));

        if (count($lines) < 2) {
            throw new RuntimeException('CSV feed has no data rows.');
        }

        $headers = str_getcsv(array_shift($lines), ',');
        $headers = array_map('trim', $headers);

        $items = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line, ',');

            // Pad short rows with nulls rather than skipping
            while (count($values) < count($headers)) {
                $values[] = '';
            }

            $row     = array_combine($headers, array_slice($values, 0, count($headers)));
            $items[] = $this->normalizeCsvRow($row);
        }

        return $items;
    }

    private function normalizeCsvRow(array $row): array
    {
        $price     = $this->parsePriceString($row['price'] ?? '');
        $salePrice = $this->parsePriceString($row['sale_price'] ?? '');

        // Frame shape — only trust values that match the allowed enum
        $shapeRaw = strtolower(trim($row['frame_shape'] ?? ''));
        $shape    = in_array($shapeRaw, self::VALID_SHAPES, true) ? $shapeRaw : null;

        // Frame material — same validation
        $materialRaw = strtolower(trim($row['frame_material'] ?? ''));
        $material    = in_array($materialRaw, self::VALID_MATERIALS, true) ? $materialRaw : null;

        // Optical dimensions — integers in mm
        $lensWidth   = $this->parsePositiveInt($row['lens_width_mm'] ?? '');
        $bridge      = $this->parsePositiveInt($row['bridge_mm'] ?? '');
        $temple      = $this->parsePositiveInt($row['temple_mm'] ?? '');
        $frameHeight = $this->parsePositiveInt($row['frame_height_mm'] ?? '');

        // Boolean fields — null means "not specified, let tagger decide"
        $progressiveFriendly = $this->parseBool($row['progressive_friendly'] ?? '');
        $strongRxFriendly    = $this->parseBool($row['strong_rx_friendly'] ?? '');

        // Style tags — pipe-separated list in a single CSV cell
        $styleTagsRaw = trim($row['style_tags'] ?? '');
        $styleTags    = !empty($styleTagsRaw)
            ? array_values(array_filter(array_map('trim', explode('|', $styleTagsRaw))))
            : null;

        return [
            'feed_product_id'     => trim($row['id'] ?? ''),
            'title'               => $this->cleanString($row['title'] ?? ''),
            'description'         => $this->cleanString($row['description'] ?? ''),
            'public_url'          => $this->cleanUrl($row['url'] ?? ''),
            'image_url'           => $this->cleanUrl($row['image_url'] ?? ''),
            'price'               => $price,
            'sale_price'          => $salePrice,
            'availability'        => strtolower(trim($row['availability'] ?? 'out of stock')),
            'brand'               => $this->cleanString($row['brand'] ?? ''),
            'color'               => $this->cleanString($row['color'] ?? ''),
            'gender'              => strtolower(trim($row['gender'] ?? '')),
            'material'            => $this->cleanString($row['material'] ?? ''),
            // Explicit enrichment — tagger will use these directly when non-null
            'frame_shape'         => $shape,
            'frame_material'      => $material,
            'lens_width_mm'       => $lensWidth,
            'bridge_mm'           => $bridge,
            'temple_mm'           => $temple,
            'frame_height_mm'     => $frameHeight,
            'progressive_friendly'=> $progressiveFriendly,
            'strong_rx_friendly'  => $strongRxFriendly,
            'style_tags'          => $styleTags,
        ];
    }

    private function parsePositiveInt(string $value): ?int
    {
        $int = (int) trim($value);

        return $int > 0 ? $int : null;
    }

    private function parseBool(string $value): ?bool
    {
        $v = strtolower(trim($value));

        if (in_array($v, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return null; // blank / unspecified — tagger will decide
    }

    private function parsePriceString(string $priceStr): ?float
    {
        if (empty(trim($priceStr))) {
            return null;
        }

        // Strip currency codes like "39.00 USD" or "$39.00"
        $cleaned = preg_replace('/[^0-9.]/', '', $priceStr);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function cleanString(string $value): string
    {
        return trim(strip_tags($value));
    }

    private function cleanUrl(string $url): string
    {
        $url = trim($url);

        if (empty($url)) {
            return '';
        }

        // Only allow http/https URLs
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }
}
