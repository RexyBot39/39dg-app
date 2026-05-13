<?php

namespace App\Services\AiAdvisor;

use App\Models\AiImportLog;
use App\Models\AiPublicProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FeedImporter
{
    public function __construct(
        private readonly FeedFetcher      $fetcher,
        private readonly FeedParser       $parser,
        private readonly ProductSanitizer $sanitizer,
        private readonly ProductTagger    $tagger,
    ) {}

    public function run(): AiImportLog
    {
        $startedAt = now();
        $feedUrls  = config('ai-advisor.feed.urls', []);
        $format    = config('ai-advisor.feed.format', 'xml');
        $timeout   = config('ai-advisor.feed.timeout', 60);

        $log = AiImportLog::create([
            'feed_url' => implode(', ', $feedUrls),
            'status'   => 'running',
        ]);

        try {
            $allParsed   = [];
            $allItems    = [];
            $allSkipped  = 0;
            $allWarnings = [];

            foreach ($feedUrls as $feedUrl) {
                // 1. Fetch
                Log::info('[AiAdvisor] Fetching feed.', ['url' => $feedUrl]);
                $raw = $this->fetcher->fetch($feedUrl, $timeout);

                // 2. Parse
                $parsed = $this->parser->parse($raw, $format);
                $allParsed = array_merge($allParsed, $parsed);

                // 3. Sanitize
                ['items' => $items, 'skipped' => $skipped, 'warnings' => $warnings] =
                    $this->sanitizer->sanitizeBatch($parsed);

                $allSkipped  += $skipped;
                $allWarnings  = array_merge($allWarnings, $warnings);

                // 4. Tag
                $allItems = array_merge($allItems, array_map(
                    fn ($item) => $this->tagger->tag($item), $items
                ));
            }

            Log::info('[AiAdvisor] All feeds parsed.', ['total' => count($allParsed)]);

            // 5. Upsert
            Log::info('[AiAdvisor] Upserting to catalog.');
            [$inserted, $updated] = $this->upsertProducts($allItems);

            // 6. Deactivate products not seen in any feed this run
            $deactivated = $this->deactivateMissing($startedAt);

            $duration = (int) $startedAt->diffInSeconds(now());

            $log->update([
                'status'               => empty($allWarnings) ? 'success' : 'partial',
                'products_fetched'     => count($allParsed),
                'products_inserted'    => $inserted,
                'products_updated'     => $updated,
                'products_deactivated' => $deactivated,
                'products_skipped'     => $allSkipped,
                'warnings'             => !empty($allWarnings) ? $allWarnings : null,
                'duration_seconds'     => $duration,
            ]);

            Log::info('[AiAdvisor] Import complete.', [
                'inserted'    => $inserted,
                'updated'     => $updated,
                'deactivated' => $deactivated,
                'skipped'     => $allSkipped,
                'duration_s'  => $duration,
            ]);

        } catch (Throwable $e) {
            Log::error('[AiAdvisor] Import failed.', [
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            $log->update([
                'status'           => 'failed',
                'error_message'    => $e->getMessage(),
                'duration_seconds' => (int) $startedAt->diffInSeconds(now()),
            ]);
        }

        return $log->fresh();
    }

    // -------------------------------------------------------------------------

    private function upsertProducts(array $items): array
    {
        $inserted  = 0;
        $updated   = 0;
        $now       = now();

        foreach ($items as $item) {
            $existing = AiPublicProduct::where('feed_product_id', $item['feed_product_id'])->first();

            $data = array_merge($item, [
                'is_active'         => true,
                'last_seen_in_feed' => $now,
            ]);

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                AiPublicProduct::create($data);
                $inserted++;
            }
        }

        return [$inserted, $updated];
    }

    private function deactivateMissing(Carbon $importStartedAt): int
    {
        // Any product whose last_seen_in_feed predates this import run was
        // not present in the feed — mark it inactive and not recommendable.
        return AiPublicProduct::where('is_active', true)
            ->where(function ($q) use ($importStartedAt) {
                $q->whereNull('last_seen_in_feed')
                  ->orWhere('last_seen_in_feed', '<', $importStartedAt);
            })
            ->update([
                'is_active'        => false,
                'is_recommendable' => false,
            ]);
    }
}
