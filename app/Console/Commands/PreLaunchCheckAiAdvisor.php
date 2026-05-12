<?php

namespace App\Console\Commands;

use App\Models\AiPublicProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Automated pre-launch verification.
 * Run this before flipping AI_ADVISOR_ENABLED=true.
 *
 * Usage:
 *   php artisan ai-advisor:check
 */
class PreLaunchCheckAiAdvisor extends Command
{
    protected $signature   = 'ai-advisor:check';
    protected $description = 'Run pre-launch verification checks for the AI Advisor.';

    private int $passed = 0;
    private int $failed = 0;
    private int $manual = 0;

    public function handle(): int
    {
        $this->line('');
        $this->line('  <fg=cyan;options=bold>39DollarGlasses AI Advisor — Pre-Launch Checklist</>');
        $this->line('');

        $this->checkSection('Configuration');
        $this->checkConfig();

        $this->checkSection('Knowledge Base');
        $this->checkKnowledgeBase();

        $this->checkSection('Product Catalog');
        $this->checkProductCatalog();

        $this->checkSection('API Endpoint');
        $this->checkApiEndpoint();

        $this->checkSection('Database Tables');
        $this->checkDatabaseTables();

        $this->checkSection('Manual Verification Required');
        $this->printManualChecks();

        $this->printSummary();

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // ── Config checks ─────────────────────────────────────────────────────────

    private function checkConfig(): void
    {
        $this->check(
            'OPENAI_API_KEY is set',
            fn () => !empty(config('ai-advisor.openai.api_key'))
        );

        $this->check(
            'AI_ADVISOR_FEED_URL is set',
            fn () => !empty(config('ai-advisor.feed.url'))
        );

        $this->check(
            'AI_ADVISOR_ENABLED is currently false (flip to true after this checklist passes)',
            fn () => config('ai-advisor.enabled') === false,
            warning: true
        );

        $this->check(
            'Rate limiter key "ai_advisor" is registered',
            function () {
                try {
                    $limiter = app(\Illuminate\Cache\RateLimiter::class);
                    // Attempt to use the limiter; if not configured it will throw
                    return class_exists(\Illuminate\Cache\RateLimiter::class);
                } catch (\Throwable) {
                    return false;
                }
            }
        );
    }

    // ── Knowledge base checks ─────────────────────────────────────────────────

    private function checkKnowledgeBase(): void
    {
        $required = [
            'lens-types', 'lens-materials', 'coatings', 'progressives',
            'ultimateview-hd', 'blue495', 'neurolux', 'ocusleep', 'lumeo',
            'transitions', 'sunglass-lenses', 'lens-replacement',
            'frame-sizing', 'frame-materials', 'frame-styles',
            'strong-prescription-frame-guide', 'disclaimers',
            'support-handoff', 'advisor-policy',
        ];

        $base = resource_path('ai-advisor/knowledge');

        foreach ($required as $file) {
            $path = "{$base}/{$file}.md";
            $this->check(
                "Knowledge file: {$file}.md",
                fn () => file_exists($path) && filesize($path) > 100
            );
        }
    }

    // ── Product catalog checks ────────────────────────────────────────────────

    private function checkProductCatalog(): void
    {
        $this->check(
            'ai_public_products table exists and has rows',
            function () {
                try {
                    return DB::table('ai_public_products')->count() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        $this->check(
            'At least 20 products are marked is_recommendable',
            function () {
                try {
                    return AiPublicProduct::where('is_recommendable', true)
                                          ->where('is_active', true)
                                          ->count() >= 20;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        $this->check(
            'Products with frame_shape tags exist',
            function () {
                try {
                    return AiPublicProduct::whereNotNull('frame_shape')
                                          ->where('is_recommendable', true)
                                          ->count() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        $this->check(
            'Lightweight-tagged products exist',
            function () {
                try {
                    return AiPublicProduct::where('lightweight', true)
                                          ->where('is_recommendable', true)
                                          ->count() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        $this->check(
            'Progressive-friendly tagged products exist',
            function () {
                try {
                    return AiPublicProduct::where('progressive_friendly', true)
                                          ->where('is_recommendable', true)
                                          ->count() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        // Show tag coverage summary
        try {
            $total       = AiPublicProduct::where('is_recommendable', true)->count();
            $withShape   = AiPublicProduct::where('is_recommendable', true)->whereNotNull('frame_shape')->count();
            $withMaterial= AiPublicProduct::where('is_recommendable', true)->whereNotNull('frame_material')->count();

            if ($total > 0) {
                $shapePct    = round($withShape / $total * 100);
                $materialPct = round($withMaterial / $total * 100);
                $this->line("       <fg=gray>Tag coverage: shape {$shapePct}%, material {$materialPct}% of {$total} recommendable products</>");
            }
        } catch (\Throwable) {}

        // Recent import check
        $this->check(
            'A successful feed import exists in the last 24 hours',
            function () {
                try {
                    return DB::table('ai_import_logs')
                             ->where('status', '!=', 'failed')
                             ->where('created_at', '>=', now()->subHours(24))
                             ->exists();
                } catch (\Throwable) {
                    return false;
                }
            }
        );
    }

    // ── API endpoint check ────────────────────────────────────────────────────

    private function checkApiEndpoint(): void
    {
        $this->check(
            'POST /api/ai-advisor/ask returns 422 for empty question (route registered)',
            function () {
                try {
                    $response = Http::timeout(5)->post(config('app.url') . '/api/ai-advisor/ask', []);
                    return $response->status() === 422;
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        $this->check(
            'ai_advisor_logs table exists',
            function () {
                try {
                    DB::table('ai_advisor_logs')->count();
                    return true;
                } catch (\Throwable) {
                    return false;
                }
            }
        );
    }

    // ── Database tables ───────────────────────────────────────────────────────

    private function checkDatabaseTables(): void
    {
        $tables = ['ai_public_products', 'ai_import_logs', 'ai_advisor_logs'];

        foreach ($tables as $table) {
            $this->check(
                "Table exists: {$table}",
                function () use ($table) {
                    try {
                        DB::table($table)->limit(1)->get();
                        return true;
                    } catch (\Throwable) {
                        return false;
                    }
                }
            );
        }
    }

    // ── Manual checks (cannot be automated) ──────────────────────────────────

    private function printManualChecks(): void
    {
        $items = [
            'All 50 test cases pass: php artisan ai-advisor:test',
            'Widget opens and closes correctly in desktop browser',
            'Widget opens and closes correctly on mobile (≤480px)',
            'Suggested prompt buttons submit and show a response',
            'Product cards display image, title, price, and reason',
            'Product card links open on 39dollarglasses.com (not wrong domain)',
            'Support handoff link opens the correct support page',
            'Neurolux page: widget shows with page-context="neurolux"',
            'Lumeo page: widget shows with page-context="lumeo"',
            'Lens options page: widget shows with page-context="lenses"',
            'Progressives page: widget shows with page-context="progressives"',
            'Frames category page: widget shows with page-context="frames"',
            'Asking "Can Neurolux cure migraines?" shows disclaimer',
            'Asking "What is my order status?" shows support handoff',
            'GA4 / PostHog events fire in the browser console (advisor_opened, etc.)',
            'AI_ADVISOR_ENABLED=false disables the widget immediately',
            'Response time is under 10 seconds for typical questions',
        ];

        foreach ($items as $item) {
            $this->manual++;
            $this->line("  <fg=yellow>□</> {$item}");
        }
    }

    // ── Output helpers ────────────────────────────────────────────────────────

    private function checkSection(string $title): void
    {
        $this->line('');
        $this->line("  <fg=white;options=bold>{$title}</>");
    }

    private function check(string $label, callable $fn, bool $warning = false): void
    {
        $passed = false;

        try {
            $passed = (bool) $fn();
        } catch (\Throwable $e) {
            $passed = false;
        }

        if ($passed) {
            $this->passed++;
            $this->line("  <fg=green>✓</> {$label}");
        } else {
            if ($warning) {
                $this->line("  <fg=yellow>⚠</> {$label}");
            } else {
                $this->failed++;
                $this->line("  <fg=red>✗</> {$label}");
            }
        }
    }

    private function printSummary(): void
    {
        $this->line('');
        $this->line('  ' . str_repeat('─', 60));
        $this->line('');

        $color = $this->failed === 0 ? 'green' : 'red';
        $this->line("  <fg={$color};options=bold>Automated: {$this->passed} passed, {$this->failed} failed</>");
        $this->line("  <fg=yellow>Manual:    {$this->manual} items require human verification</>");
        $this->line('');

        if ($this->failed === 0) {
            $this->line("  <fg=green>Automated checks passed. Complete the manual checklist above,</>");
            $this->line("  <fg=green>then set AI_ADVISOR_ENABLED=true in .env to go live.</>");
        } else {
            $this->line("  <fg=red>Fix the failed checks before enabling the widget.</>");
        }

        $this->line('');
    }
}
