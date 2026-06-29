<?php

namespace App\Services\AiAdvisor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TicketRetriever
{
    private const EMBED_MODEL = 'text-embedding-3-small';
    private const EMBED_DIMS  = 1536;
    private const DEFAULT_LIMIT = 5;

    private const VALID_BRANDS = ['39dg', 'ocusafe', 'ocusleep', 'onlinecontacts'];

    public function search(string $query, string $brand, ?string $category = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $brand = strtolower(trim($brand));
        if (!in_array($brand, self::VALID_BRANDS, true)) {
            Log::warning('[TicketRetriever] Unknown brand, defaulting to 39dg.', ['brand' => $brand]);
            $brand = '39dg';
        }

        $embedding = $this->embedQuery($query);
        $vecLiteral = '[' . implode(',', array_map(fn ($x) => sprintf('%.6f', $x), $embedding)) . ']';

        $sql = "select category, resolution_type, content, 1 - (embedding <=> ?::vector) as similarity from ticket_chunks where brand = ?";
        $bindings = [$vecLiteral, $brand];

        if ($category !== null && $category !== '') {
            $sql .= " and category = ?";
            $bindings[] = $category;
        }

        $sql .= " order by embedding <=> ?::vector limit ?";
        $bindings[] = $vecLiteral;
        $bindings[] = $limit;

        try {
            $rows = DB::select($sql, $bindings);
        } catch (\Throwable $e) {
            Log::error('[TicketRetriever] Vector search failed.', ['error' => $e->getMessage()]);
            return [];
        }

        return array_map(fn ($r) => [
            'category'        => $r->category,
            'resolution_type' => $r->resolution_type,
            'content'         => $r->content,
            'similarity'      => round((float) $r->similarity, 3),
        ], $rows);
    }

    private function embedQuery(string $query): array
    {
        $apiKey = config('ai-advisor.openai.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY not configured for embeddings.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => self::EMBED_MODEL,
                'input' => $query,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding API error: HTTP ' . $response->status());
        }

        $vec = $response->json('data.0.embedding');
        if (!is_array($vec) || count($vec) !== self::EMBED_DIMS) {
            throw new RuntimeException('Unexpected embedding shape.');
        }

        return $vec;
    }
}
