<?php

namespace App\Http\Controllers\AiAdvisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAdvisor\AdvisorAskRequest;
use App\Models\AiAdvisorLog;
use App\Services\AiAdvisor\AdvisorFunctionHandler;
use App\Services\AiAdvisor\AdvisorService;
use App\Services\AiAdvisor\KnowledgeBaseLoader;
use App\Services\AiAdvisor\QuestionPreFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdvisorController extends Controller
{
    public function __construct(
        private readonly QuestionPreFilter $preFilter,
        private readonly AdvisorService   $advisor,
    ) {}

    public function ask(AdvisorAskRequest $request): JsonResponse
    {
        $startedAt      = microtime(true);
        $question       = $request->input('question');
        $pageContext    = $request->input('page_context', '');
        $selectedFilters= $request->input('selected_filters', []);
        $sessionId      = $request->input('session_id');
        $brand          = $request->input('brand', '39dg');
        $history        = $request->input('history', []);
        $ipHash         = hash('sha256', $request->ip() . config('app.key'));

        // --- 1. Pre-filter (fast PHP check, no OpenAI call) ------------------

        $filterResult = $this->preFilter->check($question);

        if ($filterResult->isBlocked()) {
            $response = $this->advisor->fallbackResponse();
            $this->logEvent([
                'session_id'   => $sessionId,
                'ip_hash'      => $ipHash,
                'page_context' => $pageContext,
                'question_text'=> $question,
                'pre_filtered' => true,
                'answer_type'  => 'fallback',
                'response_time_ms' => $this->elapsedMs($startedAt),
            ]);

            return response()->json($this->stripInternalFields($response));
        }

        if ($filterResult->isOutOfScope()) {
            $response = $this->advisor->supportHandoffResponse($filterResult->reason ?? '');
            $this->logEvent([
                'session_id'              => $sessionId,
                'ip_hash'                 => $ipHash,
                'page_context'            => $pageContext,
                'question_text'           => $question,
                'pre_filtered'            => true,
                'answer_type'             => 'support_handoff',
                'support_handoff_triggered' => true,
                'response_time_ms'        => $this->elapsedMs($startedAt),
            ]);

            return response()->json($this->stripInternalFields($response));
        }

        // --- 2. OpenAI call ---------------------------------------------------

        try {
            // Fresh handler per request so retrieved product IDs don't bleed across
            $handler = app(AdvisorFunctionHandler::class);
            $handler->brand = $brand;

            // Re-bind the advisor service with this request's fresh handler
            $advisorService = new AdvisorService($handler);

            $aiResponse = $advisorService->ask($question, $pageContext, $selectedFilters, $history);

            $tokens      = $aiResponse['_tokens_used'] ?? null;
            $productIds  = $handler->getRetrievedProductIds();
            $productCount= count($aiResponse['recommended_products'] ?? []);

            $this->logEvent([
                'session_id'                 => $sessionId,
                'ip_hash'                    => $ipHash,
                'page_context'               => $pageContext,
                'question_text'              => $question,
                'pre_filtered'               => false,
                'answer_type'                => $aiResponse['answer_type'] ?? 'fallback',
                'support_handoff_triggered'  => ($aiResponse['support_handoff']['needed'] ?? false),
                'products_recommended_count' => $productCount,
                'products_recommended_ids'   => !empty($productIds) ? $productIds : null,
                'specialty_brand_interest'   => $handler->specialtyBrandInterest ?: null,
                'lens_category_interest'     => $handler->lensCategoryInterest ?: null,
                'tokens_used'                => $tokens,
                'response_time_ms'           => $this->elapsedMs($startedAt),
            ]);

            return response()->json($this->stripInternalFields($aiResponse));

        } catch (Throwable $e) {
            Log::error('[AiAdvisor] Request failed.', [
                'error'    => $e->getMessage(),
                'question' => substr($question, 0, 100),
            ]);

            $this->logEvent([
                'session_id'      => $sessionId,
                'ip_hash'         => $ipHash,
                'page_context'    => $pageContext,
                'question_text'   => $question,
                'pre_filtered'    => false,
                'answer_type'     => 'fallback',
                'response_time_ms'=> $this->elapsedMs($startedAt),
            ]);

            return response()->json(
                $this->stripInternalFields($this->advisor->fallbackResponse()),
                200  // Return 200 with fallback — never expose 500 to the widget
            );
        }
    }

    // -------------------------------------------------------------------------

    private function logEvent(array $data): void
    {
        try {
            AiAdvisorLog::create(array_filter($data, fn ($v) => $v !== null));
        } catch (Throwable $e) {
            Log::error('[AiAdvisor] Failed to write analytics log.', ['error' => $e->getMessage()]);
        }
    }

    private function stripInternalFields(array $response): array
    {
        unset($response['_tokens_used']);
        return $response;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) ((microtime(true) - $startedAt) * 1000);
    }
}
