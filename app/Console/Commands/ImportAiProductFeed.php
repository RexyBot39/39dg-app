<?php

namespace App\Console\Commands;

use App\Services\AiAdvisor\FeedImporter;
use Illuminate\Console\Command;

class ImportAiProductFeed extends Command
{
    protected $signature   = 'ai-advisor:import-feed {--dry-run : Parse and count without writing to the database}';
    protected $description = 'Import the Google Shopping product feed into the AI advisor public catalog.';

    public function handle(FeedImporter $importer): int
    {
        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Parsing feed without writing to database...');
            $this->dryRun();
            return Command::SUCCESS;
        }

        $this->info('Starting AI Advisor feed import...');

        $log = $importer->run();

        $status = $log->status;

        $this->table(
            ['Metric', 'Count'],
            [
                ['Fetched from feed',  $log->products_fetched],
                ['Inserted (new)',      $log->products_inserted],
                ['Updated (existing)', $log->products_updated],
                ['Deactivated',        $log->products_deactivated],
                ['Skipped (invalid)',  $log->products_skipped],
                ['Duration (seconds)', $log->duration_seconds],
            ]
        );

        if ($status === 'failed') {
            $this->error("Import FAILED: {$log->error_message}");
            return Command::FAILURE;
        }

        if (!empty($log->warnings)) {
            $this->warn('Import completed with warnings:');
            foreach ($log->warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }

        $this->info("Import {$status}. Log ID: {$log->id}");

        return Command::SUCCESS;
    }

    private function dryRun(): void
    {
        $feedUrl = config('ai-advisor.feed.url');
        $format  = config('ai-advisor.feed.format', 'xml');

        if (empty($feedUrl)) {
            $this->error('AI_ADVISOR_FEED_URL is not set.');
            return;
        }

        $this->line("Feed URL: {$feedUrl}");
        $this->line("Format:   {$format}");

        try {
            $fetcher    = app(\App\Services\AiAdvisor\FeedFetcher::class);
            $parser     = app(\App\Services\AiAdvisor\FeedParser::class);
            $sanitizer  = app(\App\Services\AiAdvisor\ProductSanitizer::class);
            $tagger     = app(\App\Services\AiAdvisor\ProductTagger::class);

            $raw    = $fetcher->fetch($feedUrl, config('ai-advisor.feed.timeout', 60));
            $parsed = $parser->parse($raw, $format);

            $this->line("Parsed:    " . count($parsed) . " items");

            ['items' => $items, 'skipped' => $skipped, 'warnings' => $warnings] =
                $sanitizer->sanitizeBatch($parsed);

            $this->line("Sanitized: " . count($items) . " items ({$skipped} skipped)");

            if (!empty($warnings)) {
                $this->warn("Warnings:");
                foreach (array_slice($warnings, 0, 10) as $w) {
                    $this->line("  - {$w}");
                }
                if (count($warnings) > 10) {
                    $this->line('  ... and ' . (count($warnings) - 10) . ' more.');
                }
            }

            $tagged = array_map(fn ($item) => $tagger->tag($item), $items);

            $shapes    = array_count_values(array_filter(array_column($tagged, 'frame_shape')));
            $materials = array_count_values(array_filter(array_column($tagged, 'frame_material')));
            $tiers     = array_count_values(array_filter(array_column($tagged, 'budget_tier')));
            $recCount  = count(array_filter($tagged, fn ($i) => $i['is_recommendable']));

            $this->line("Recommendable: {$recCount} / " . count($tagged));
            $this->line("Shapes:    " . json_encode($shapes));
            $this->line("Materials: " . json_encode($materials));
            $this->line("Tiers:     " . json_encode($tiers));

            $this->info('[DRY RUN] Complete. No data written.');

        } catch (\Throwable $e) {
            $this->error("Dry run failed: {$e->getMessage()}");
        }
    }
}
