<?php

namespace App\Services\AiAdvisor;

use RuntimeException;

class FeedParser
{
    // Google Shopping XML namespace
    private const G_NS = 'http://base.google.com/ns/1.0';

    public function parse(string $content, string $format): array
    {
        return match ($format) {
            'xml'  => $this->parseXml($content),
            'tsv'  => $this->parseTsv($content),
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
