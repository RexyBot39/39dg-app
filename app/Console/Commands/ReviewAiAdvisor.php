<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Weekly analytics review for the AI Advisor soft launch.
 * Reads only from ai_advisor_logs — no customer data, no orders.
 *
 * Usage:
 *   php artisan ai-advisor:review               # last 7 days
 *   php artisan ai-advisor:review --days=30     # last 30 days
 *   php artisan ai-advisor:review --since=2026-05-01
 *   php artisan ai-advisor:review --unanswered  # focus on bad answers
 */
class ReviewAiAdvisor extends Command
{
    protected $signature = 'ai-advisor:review
        {--days=7        : Number of days to include in the report}
        {--since=        : Start date (YYYY-MM-DD). Overrides --days.}
        {--unanswered    : Show only the unanswered / fallback question list}';

    protected $description = 'Generate a weekly analytics review for the AI Advisor.';

    public function handle(): int
    {
        $since = $this->getSinceDate();
        $label = $this->option('since')
            ? "since {$since->toDateString()}"
            : "last {$this->option('days')} days";

        if ($this->option('unanswered')) {
            $this->showUnansweredQuestions($since);
            return Command::SUCCESS;
        }

        $this->line('');
        $this->line("  <fg=cyan;options=bold>39DollarGlasses AI Advisor — Review Report</>");
        $this->line("  Period: {$label} (through " . now()->toDateString() . ')');
        $this->line('');

        $this->showVolume($since);
        $this->showAnswerTypes($since);
        $this->showPageBreakdown($since);
        $this->showSpecialtyBrandInterest($since);
        $this->showLensCategoryInterest($since);
        $this->showHandoffBreakdown($since);
        $this->showResponseTime($since);
        $this->showDailyTrend($since);
        $this->showUnansweredQuestions($since, limit: 15);
        $this->showActionItems($since);

        return Command::SUCCESS;
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    private function showVolume(\Carbon\Carbon $since): void
    {
        $total       = $this->q($since)->count();
        $days        = max(1, $since->diffInDays(now()));
        $perDay      = round($total / $days, 1);
        $preFiltered = $this->q($since)->where('pre_filtered', true)->count();
        $live        = $total - $preFiltered;

        $this->section('Volume');
        $this->row('Total requests',          $total);
        $this->row('Reached OpenAI',           $live . ' (' . $this->pct($live, $total) . ')');
        $this->row('Pre-filtered (OOS/inject)', $preFiltered . ' (' . $this->pct($preFiltered, $total) . ')');
        $this->row('Requests / day avg',       $perDay);
    }

    private function showAnswerTypes(\Carbon\Carbon $since): void
    {
        $rows = $this->q($since)
            ->select('answer_type', DB::raw('COUNT(*) as count'))
            ->groupBy('answer_type')
            ->orderByDesc('count')
            ->get();

        $total = $rows->sum('count');

        $this->section('Answer Types');
        foreach ($rows as $row) {
            $type = $row->answer_type ?? '(null)';
            $this->row($type, $row->count . ' (' . $this->pct($row->count, $total) . ')');
        }

        if ($rows->isEmpty()) {
            $this->line("  <fg=gray>  No data for this period.</>");
        }
    }

    private function showPageBreakdown(\Carbon\Carbon $since): void
    {
        $rows = $this->q($since)
            ->select('page_context', DB::raw('COUNT(*) as count'))
            ->groupBy('page_context')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $total = $this->q($since)->count();

        $this->section('Page Context (where widget was used)');
        foreach ($rows as $row) {
            $page = $row->page_context ?: '(none)';
            $this->row($page, $row->count . ' (' . $this->pct($row->count, $total) . ')');
        }
    }

    private function showSpecialtyBrandInterest(\Carbon\Carbon $since): void
    {
        $rows = $this->q($since)
            ->whereNotNull('specialty_brand_interest')
            ->where('specialty_brand_interest', '!=', '')
            ->select('specialty_brand_interest', DB::raw('COUNT(*) as count'))
            ->groupBy('specialty_brand_interest')
            ->orderByDesc('count')
            ->get();

        $this->section('Specialty Brand Interest');

        if ($rows->isEmpty()) {
            $this->line("  <fg=gray>  No specialty brand questions in this period.</>");
            return;
        }

        foreach ($rows as $row) {
            $this->row($row->specialty_brand_interest, $row->count);
        }
    }

    private function showLensCategoryInterest(\Carbon\Carbon $since): void
    {
        $rows = $this->q($since)
            ->whereNotNull('lens_category_interest')
            ->where('lens_category_interest', '!=', '')
            ->select('lens_category_interest', DB::raw('COUNT(*) as count'))
            ->groupBy('lens_category_interest')
            ->orderByDesc('count')
            ->get();

        $this->section('Lens Category Interest');

        if ($rows->isEmpty()) {
            $this->line("  <fg=gray>  No lens category questions in this period.</>");
            return;
        }

        foreach ($rows as $row) {
            $this->row($row->lens_category_interest, $row->count);
        }
    }

    private function showHandoffBreakdown(\Carbon\Carbon $since): void
    {
        $total   = $this->q($since)->count();
        $handoff = $this->q($since)->where('support_handoff_triggered', true)->count();
        $rate    = $this->pct($handoff, $total);

        $this->section('Support Handoff');
        $this->row('Total handoffs', $handoff . ' (' . $rate . ')');

        // Flag if handoff rate is unusually high (>20% is worth investigating)
        if ($total > 0 && $handoff / $total > 0.20) {
            $this->line("  <fg=yellow>  ⚠ Handoff rate >20%. Review unanswered questions: --unanswered</>");
        }

        // Products recommended
        $withProducts = $this->q($since)
            ->where('products_recommended_count', '>', 0)
            ->count();

        $this->row('Requests with product recommendations', $withProducts . ' (' . $this->pct($withProducts, $total) . ')');
    }

    private function showResponseTime(\Carbon\Carbon $since): void
    {
        $stats = $this->q($since)
            ->where('pre_filtered', false)
            ->whereNotNull('response_time_ms')
            ->select(
                DB::raw('AVG(response_time_ms) as avg_ms'),
                DB::raw('MAX(response_time_ms) as max_ms'),
                DB::raw('MIN(response_time_ms) as min_ms'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        $this->section('Response Time (OpenAI calls only)');

        if (!$stats || $stats->count === 0) {
            $this->line("  <fg=gray>  No response time data.</>");
            return;
        }

        $this->row('Average', $this->ms($stats->avg_ms));
        $this->row('Min',     $this->ms($stats->min_ms));
        $this->row('Max',     $this->ms($stats->max_ms));

        if ($stats->avg_ms > 8000) {
            $this->line("  <fg=yellow>  ⚠ Average response time >8s. Consider model or prompt optimization.</>");
        }

        if ($stats->max_ms > 20000) {
            $this->line("  <fg=red>  ✗ Max response time >20s. Check for OpenAI timeout issues.</>");
        }
    }

    private function showDailyTrend(\Carbon\Carbon $since): void
    {
        $rows = $this->q($since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $this->section('Daily Volume');

        if ($rows->isEmpty()) {
            $this->line("  <fg=gray>  No data.</>");
            return;
        }

        $max = $rows->max('count');

        foreach ($rows as $row) {
            $bar   = str_repeat('█', (int) min(30, ($row->count / max(1, $max)) * 30));
            $count = str_pad($row->count, 4, ' ', STR_PAD_LEFT);
            $this->line("  <fg=gray>{$row->date}</>  <fg=cyan>{$bar}</> {$count}");
        }
    }

    private function showUnansweredQuestions(\Carbon\Carbon $since, int $limit = 25): void
    {
        // Fallback answers that were NOT pre-filtered are the ones worth reviewing.
        // These represent questions the model couldn't handle well.
        $rows = $this->q($since)
            ->where('answer_type', 'fallback')
            ->where('pre_filtered', false)
            ->whereNotNull('question_text')
            ->select('question_text', DB::raw('COUNT(*) as count'))
            ->groupBy('question_text')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        $this->section('Needs Review: Fallback Answers (not pre-filtered)');
        $this->line("  <fg=gray>These questions reached OpenAI but got a fallback response.</>");
        $this->line("  <fg=gray>Use these to improve knowledge files or system prompt.</>");
        $this->line('');

        if ($rows->isEmpty()) {
            $this->line("  <fg=green>  None — no uninstructed fallbacks in this period. ✓</>");
            return;
        }

        foreach ($rows as $i => $row) {
            $n = str_pad($i + 1, 2, ' ', STR_PAD_LEFT);
            $q = $this->truncate($row->question_text, 90);
            $c = $row->count > 1 ? " <fg=yellow>({$row->count}×)</>" : '';
            $this->line("  {$n}. {$q}{$c}");
        }
    }

    private function showActionItems(\Carbon\Carbon $since): void
    {
        $this->section('Action Items for This Week');

        $total       = $this->q($since)->count();
        $fallbacks   = $this->q($since)->where('answer_type', 'fallback')->where('pre_filtered', false)->count();
        $handoffs    = $this->q($since)->where('support_handoff_triggered', true)->count();
        $withProducts= $this->q($since)->where('products_recommended_count', '>', 0)->count();

        $items = [];

        if ($total === 0) {
            $items[] = ['yellow', 'No requests yet — confirm widget is deployed on the 5 soft-launch pages'];
        }

        if ($fallbacks > 0) {
            $items[] = ['red', "Review {$fallbacks} uninstructed fallback(s) above — update knowledge files or system prompt for unanswered topics"];
        }

        if ($total > 0 && $handoffs / $total > 0.20) {
            $items[] = ['yellow', 'High handoff rate — check if widget is appearing on pages where order/account questions are common'];
        }

        if ($total > 0 && $withProducts / $total < 0.15) {
            $items[] = ['yellow', 'Low product recommendation rate — check product tagging quality in ai_public_products'];
        }

        if (empty($items)) {
            $this->line("  <fg=green>  Looking good. No action items flagged for this period. ✓</>");
        } else {
            foreach ($items as [$color, $msg]) {
                $this->line("  <fg={$color}>→</> {$msg}");
            }
        }

        $this->line('');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function q(\Carbon\Carbon $since)
    {
        return DB::table('ai_advisor_logs')->where('created_at', '>=', $since);
    }

    private function getSinceDate(): \Carbon\Carbon
    {
        $since = $this->option('since');

        if ($since) {
            return \Carbon\Carbon::parse($since)->startOfDay();
        }

        return now()->subDays((int) $this->option('days'))->startOfDay();
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("  <fg=white;options=bold>{$title}</>");
        $this->line('  ' . str_repeat('─', 45));
    }

    private function row(string $label, int|string $value): void
    {
        $label = str_pad($label, 38, ' ');
        $this->line("  {$label} {$value}");
    }

    private function pct(int $n, int $total): string
    {
        if ($total === 0) return '0%';
        return round($n / $total * 100) . '%';
    }

    private function ms(float|null $ms): string
    {
        if ($ms === null) return 'n/a';
        return number_format((int) $ms) . 'ms';
    }

    private function truncate(string $text, int $len): string
    {
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }
}
