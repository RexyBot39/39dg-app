<?php

namespace App\Providers;

use App\Services\AiAdvisor\AdvisorFunctionHandler;
use App\Services\AiAdvisor\AdvisorService;
use App\Services\AiAdvisor\FeedFetcher;
use App\Services\AiAdvisor\FeedImporter;
use App\Services\AiAdvisor\FeedParser;
use App\Services\AiAdvisor\KnowledgeBaseLoader;
use App\Services\AiAdvisor\ProductSanitizer;
use App\Services\AiAdvisor\ProductTagger;
use App\Services\AiAdvisor\QuestionPreFilter;
use Illuminate\Support\ServiceProvider;

class AiAdvisorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 2 — Feed importer (singletons; shared across the request lifecycle)
        $this->app->singleton(FeedFetcher::class);
        $this->app->singleton(FeedParser::class);
        $this->app->singleton(ProductSanitizer::class);
        $this->app->singleton(ProductTagger::class);

        $this->app->singleton(FeedImporter::class, function ($app) {
            return new FeedImporter(
                $app->make(FeedFetcher::class),
                $app->make(FeedParser::class),
                $app->make(ProductSanitizer::class),
                $app->make(ProductTagger::class),
            );
        });

        // Phase 3 — Advisor API
        // KnowledgeBaseLoader and QuestionPreFilter are stateless — singletons are fine.
        $this->app->singleton(KnowledgeBaseLoader::class);
        $this->app->singleton(QuestionPreFilter::class);

        // AdvisorFunctionHandler is stateful per request (tracks retrieved product IDs
        // and analytics metadata). Bind as transient so each resolution is a new instance.
        $this->app->bind(AdvisorFunctionHandler::class, function ($app) {
            return new AdvisorFunctionHandler($app->make(KnowledgeBaseLoader::class));
        });

        // AdvisorService is also bound as transient; the controller instantiates
        // a fresh one per request with its own AdvisorFunctionHandler.
        $this->app->bind(AdvisorService::class, function ($app) {
            return new AdvisorService($app->make(AdvisorFunctionHandler::class));
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ai-advisor.php',
            'ai-advisor'
        );
    }
}
