<?php

namespace App\Services\AiAdvisor;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FeedFetcher
{
    public function fetch(string $url, int $timeoutSeconds = 60): string
    {
        if (empty($url)) {
            throw new RuntimeException('AI_ADVISOR_FEED_URL is not configured.');
        }

        $response = Http::timeout($timeoutSeconds)
            ->withHeaders(['Accept' => 'application/xml, text/xml, text/tab-separated-values, */*'])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException(
                "Feed fetch failed: HTTP {$response->status()} from {$url}"
            );
        }

        $body = $response->body();

        if (empty(trim($body))) {
            throw new RuntimeException('Feed response was empty.');
        }

        return $body;
    }
}
