<?php

namespace App\Services\AiAdvisor;

/**
 * Fast PHP-level safety check before a question reaches OpenAI.
 * Catches obvious out-of-scope requests and prompt injection attempts
 * without burning an API call.
 */
class QuestionPreFilter
{
    // Questions that require human support — never answer with AI
    // Account-specific requests that genuinely need human/account access.
    // General policy questions (returns, refunds, remakes, insurance) are now
    // handled by the advisor via search_support_tickets, so they are NOT here.
    private const OUT_OF_SCOPE = [
        'my order', 'order status', 'order number', 'track my', 'tracking number',
        'where is my', 'shipping status', 'when will my',
        'my prescription', 'my account', 'my password', 'log in', 'login', 'sign in',
        'charged me', 'redo my',
        'billing', 'payment method', 'credit card', 'invoice',
        'my ticket', 'my case', 'my complaint', 'spoke with',
        'discount code', 'promo code', 'coupon',
        'cancel my order', 'cancel order',
    ];

    // Prompt injection and jailbreak patterns
    private const INJECTION_PATTERNS = [
        'ignore previous instructions',
        'ignore your instructions',
        'disregard your',
        'forget your instructions',
        'override your',
        'you are now',
        'act as if',
        'pretend you are',
        'reveal your system prompt',
        'show me your prompt',
        'what is your system prompt',
        'print your instructions',
        'repeat your instructions',
        'jailbreak',
        'dan mode',
        'developer mode',
        'ignore the above',
        'new instructions:',
        'ignore all previous',
    ];

    public function check(string $question): FilterResult
    {
        $lower = strtolower(trim($question));

        // Check length
        if (strlen($question) > 1000) {
            return FilterResult::blocked('Question too long.');
        }

        // Prompt injection check
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return FilterResult::blocked('prompt_injection');
            }
        }

        // Out-of-scope check
        foreach (self::OUT_OF_SCOPE as $phrase) {
            if (str_contains($lower, $phrase)) {
                return FilterResult::outOfScope($phrase);
            }
        }

        return FilterResult::allowed();
    }
}

class FilterResult
{
    private function __construct(
        public readonly string  $status,   // allowed | blocked | out_of_scope
        public readonly ?string $reason = null,
    ) {}

    public static function allowed(): self
    {
        return new self('allowed');
    }

    public static function blocked(string $reason): self
    {
        return new self('blocked', $reason);
    }

    public static function outOfScope(string $phrase): self
    {
        return new self('out_of_scope', $phrase);
    }

    public function isAllowed(): bool   { return $this->status === 'allowed'; }
    public function isBlocked(): bool   { return $this->status === 'blocked'; }
    public function isOutOfScope(): bool{ return $this->status === 'out_of_scope'; }
}
