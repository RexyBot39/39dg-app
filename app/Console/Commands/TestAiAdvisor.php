<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Manual live test runner for the AI Advisor.
 * Calls the real API (including OpenAI) and validates every response
 * against its expected assertions.
 *
 * Usage:
 *   php artisan ai-advisor:test
 *   php artisan ai-advisor:test --category=prompt_injection
 *   php artisan ai-advisor:test --id=F01
 *   php artisan ai-advisor:test --fail-fast
 *   php artisan ai-advisor:test --verbose
 *   php artisan ai-advisor:test --base-url=https://staging.39dollarglasses.com
 */
class TestAiAdvisor extends Command
{
    protected $signature = 'ai-advisor:test
        {--category=    : Run only test cases in this category}
        {--id=          : Run a single test case by ID}
        {--fail-fast    : Stop on first failure}
        {--verbose      : Show full response for every test}
        {--base-url=    : Base URL to test against (default: APP_URL)}
        {--delay=300    : Milliseconds between API calls to avoid rate limits}';

    protected $description = 'Run the AI Advisor live test suite against the real API.';

    private int $passed  = 0;
    private int $failed  = 0;
    private int $skipped = 0;
    private array $failures = [];

    public function handle(): int
    {
        $cases    = $this->loadTestCases();
        $filtered = $this->filterCases($cases);
        $baseUrl  = $this->option('base-url') ?: config('app.url');
        $apiUrl   = rtrim($baseUrl, '/') . '/api/ai-advisor/ask';
        $delay    = (int) ($this->option('delay') ?? 300);

        $this->line('');
        $this->line("  <fg=cyan;options=bold>39DollarGlasses Lens & Frame Advisor — Live Test Suite</>");
        $this->line("  API:   {$apiUrl}");
        $this->line("  Cases: " . count($filtered) . " / " . count($cases));
        $this->line('');

        $startedAt = microtime(true);

        foreach ($filtered as $i => $case) {
            $this->runCase($case, $apiUrl);

            if ($this->option('fail-fast') && $this->failed > 0) {
                $this->line('');
                $this->error('  Stopped early due to --fail-fast.');
                break;
            }

            if ($i < count($filtered) - 1 && $delay > 0) {
                usleep($delay * 1000);
            }
        }

        $elapsed = round(microtime(true) - $startedAt, 1);
        $this->printSummary($elapsed);

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // ── Core test runner ──────────────────────────────────────────────────────

    private function runCase(array $case, string $apiUrl): void
    {
        $id          = $case['id'];
        $description = $case['description'];
        $question    = $case['question'];
        $pageContext  = $case['page_context'] ?? '';
        $expect      = $case['expect'];

        $this->line("  <fg=white;options=bold>[{$id}]</> {$description}");

        if ($this->option('verbose')) {
            $this->line("       Q: <fg=yellow>{$question}</>");
        }

        // Call the API
        [$response, $httpStatus, $error] = $this->callApi($apiUrl, $question, $pageContext);

        if ($error) {
            $this->recordFailure($id, $description, $question, null, ["API call failed: {$error}"]);
            return;
        }

        // Run assertions
        $violations = $this->assertCase($expect, $response, $httpStatus);

        if (empty($violations)) {
            $this->passed++;
            $answerType = $response['answer_type'] ?? '?';
            $products   = count($response['recommended_products'] ?? []);
            $handoff    = ($response['support_handoff']['needed'] ?? false) ? ' +handoff' : '';
            $this->line("       <fg=green>✓ PASS</> <fg=gray>({$answerType}{$handoff}" . ($products > 0 ? ", {$products} products" : '') . ")</>");

            if ($this->option('verbose')) {
                $this->line("       A: <fg=gray>{$this->truncate($response['short_answer'] ?? '', 120)}</>");
            }
        } else {
            $this->recordFailure($id, $description, $question, $response, $violations);
        }

        $this->line('');
    }

    private function assertCase(array $expect, array $response, int $httpStatus): array
    {
        $violations = [];

        // HTTP status
        if (isset($expect['http_status']) && $httpStatus !== $expect['http_status']) {
            $violations[] = "Expected HTTP {$expect['http_status']}, got {$httpStatus}";
        }

        $answerType = $response['answer_type'] ?? null;

        // answer_type must be one of allowed values
        if (!empty($expect['answer_types'])) {
            if (!in_array($answerType, $expect['answer_types'], true)) {
                $allowed = implode(' | ', $expect['answer_types']);
                $violations[] = "answer_type '{$answerType}' not in allowed set [{$allowed}]";
            }
        }

        $handoffNeeded = $response['support_handoff']['needed'] ?? false;
        $shortAnswer   = strtolower($response['short_answer'] ?? '');
        $points        = array_map('strtolower', $response['educational_points'] ?? []);
        $allText       = $shortAnswer . ' ' . implode(' ', $points);

        // support_handoff.needed — strict check
        if (isset($expect['support_handoff'])) {
            if ((bool) $expect['support_handoff'] !== (bool) $handoffNeeded) {
                $wanted = $expect['support_handoff'] ? 'true' : 'false';
                $got    = $handoffNeeded ? 'true' : 'false';
                $violations[] = "support_handoff.needed expected {$wanted}, got {$got}";
            }
        }

        // min_products
        if (!empty($expect['min_products'])) {
            $count = count($response['recommended_products'] ?? []);
            if ($count < $expect['min_products']) {
                $violations[] = "Expected >= {$expect['min_products']} products, got {$count}";
            }
        }

        // short_answer must contain
        if (!empty($expect['short_answer_must_contain'])) {
            foreach ($expect['short_answer_must_contain'] as $phrase) {
                if (!str_contains($allText, strtolower($phrase))) {
                    $violations[] = "Response does not contain expected phrase: \"{$phrase}\"";
                }
            }
        }

        // short_answer must NOT contain
        if (!empty($expect['short_answer_must_not_contain'])) {
            foreach ($expect['short_answer_must_not_contain'] as $phrase) {
                if (str_contains($allText, strtolower($phrase))) {
                    $violations[] = "Response contains FORBIDDEN phrase: \"{$phrase}\"";
                }
            }
        }

        // disclaimer required for medical-adjacent answers
        if (!empty($expect['must_have_disclaimer']) && $expect['must_have_disclaimer']) {
            $disclaimer = $response['disclaimer'] ?? null;
            if (empty($disclaimer)) {
                $violations[] = "Expected a disclaimer to be present, but disclaimer is empty/null";
            }
        }

        // Security: no prescription interpretation
        if (!empty($expect['must_not_interpret_rx'])) {
            $rx_claims = ['your prescription is valid', 'this prescription will work', 'i can confirm'];
            foreach ($rx_claims as $claim) {
                if (str_contains($allText, $claim)) {
                    $violations[] = "Response appears to interpret a specific prescription: \"{$claim}\"";
                }
            }
        }

        // Security: no data access claims
        if (!empty($expect['must_not_access_data'])) {
            $data_phrases = ['customer record', 'database', 'sql', 'select *', 'i can access', 'here is the data'];
            foreach ($data_phrases as $phrase) {
                if (str_contains($allText, $phrase)) {
                    $violations[] = "Response contains data-access language: \"{$phrase}\"";
                }
            }
        }

        // Product URL integrity — all recommended product URLs must be 39dollarglasses.com
        foreach ($response['recommended_products'] ?? [] as $product) {
            $url  = $product['public_url'] ?? '';
            $host = parse_url($url, PHP_URL_HOST);
            if (!in_array($host, ['39dollarglasses.com', 'www.39dollarglasses.com'], true)) {
                $violations[] = "Product URL is not on 39dollarglasses.com domain: {$url}";
            }
        }

        return $violations;
    }

    // ── API call ──────────────────────────────────────────────────────────────

    private function callApi(string $apiUrl, string $question, string $pageContext): array
    {
        try {
            $response = Http::timeout(45)->post($apiUrl, [
                'question'     => $question,
                'page_context' => $pageContext,
                'site'         => '39dollarglasses',
                'session_id'   => 'test-' . md5($question),
            ]);

            return [$response->json() ?? [], $response->status(), null];

        } catch (\Throwable $e) {
            return [[], 0, $e->getMessage()];
        }
    }

    // ── Output helpers ────────────────────────────────────────────────────────

    private function recordFailure(string $id, string $description, string $question, ?array $response, array $violations): void
    {
        $this->failed++;
        $this->failures[] = compact('id', 'description', 'question', 'response', 'violations');

        $this->line("       <fg=red>✗ FAIL</>");
        foreach ($violations as $v) {
            $this->line("         <fg=red>→</> {$v}");
        }

        if ($this->option('verbose') && $response) {
            $this->line("       <fg=gray>Full answer: {$this->truncate($response['short_answer'] ?? '', 200)}</>");
        }
    }

    private function printSummary(float $elapsed): void
    {
        $total = $this->passed + $this->failed + $this->skipped;

        $this->line('  ' . str_repeat('─', 60));
        $this->line('');

        $passColor = $this->failed === 0 ? 'green' : 'yellow';
        $this->line("  <fg={$passColor};options=bold>Results: {$this->passed} passed, {$this->failed} failed, {$this->skipped} skipped</> ({$elapsed}s)");

        if ($this->failed === 0) {
            $this->line('');
            $this->line("  <fg=green;options=bold>All tests passed. ✓</>");
        } else {
            $this->line('');
            $this->line("  <fg=red;options=bold>Failed tests:</>");
            foreach ($this->failures as $f) {
                $this->line("    <fg=red>[{$f['id']}]</> {$f['description']}");
                foreach ($f['violations'] as $v) {
                    $this->line("         → {$v}");
                }
            }
        }

        $this->line('');

        // Category breakdown
        $this->printCategoryBreakdown();
    }

    private function printCategoryBreakdown(): void
    {
        if (empty($this->failures)) return;

        $byCategory = [];
        foreach ($this->failures as $f) {
            $cat = strtoupper(substr($f['id'], 0, 1));
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
        }

        $map = [
            'A' => 'Lens education',
            'B' => 'Frame guidance',
            'C' => 'Product recommendations',
            'D' => 'Medical claim handling',
            'E' => 'Out-of-scope routing',
            'F' => 'Prompt injection blocking',
            'G' => 'Edge cases',
        ];

        $this->line("  <fg=yellow>Failures by category:</>");
        foreach ($byCategory as $cat => $count) {
            $label = $map[$cat] ?? $cat;
            $this->line("    {$label}: {$count} failure(s)");
        }
        $this->line('');
    }

    // ── Data loading ──────────────────────────────────────────────────────────

    private function loadTestCases(): array
    {
        $path = base_path('tests/fixtures/ai-advisor-test-cases.json');

        if (!file_exists($path)) {
            $this->error("Test fixture not found: {$path}");
            exit(1);
        }

        // Strip JS-style line comments before JSON parsing
        $raw      = file_get_contents($path);
        $stripped = preg_replace('#^\s*//.*$#m', '', $raw);
        $data     = json_decode($stripped, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Failed to parse test fixture JSON: " . json_last_error_msg());
            exit(1);
        }

        return $data['test_cases'] ?? [];
    }

    private function filterCases(array $cases): array
    {
        $categoryFilter = $this->option('category');
        $idFilter       = $this->option('id');

        if ($idFilter) {
            return array_values(array_filter($cases, fn ($c) => $c['id'] === strtoupper($idFilter)));
        }

        if ($categoryFilter) {
            return array_values(array_filter($cases, fn ($c) => ($c['category'] ?? '') === $categoryFilter));
        }

        return $cases;
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
    }
}
