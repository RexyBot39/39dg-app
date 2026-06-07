{{--
  Lens & Frame Advisor Widget — Blade Component

  Usage:
    <x-ai-advisor-widget page-context="neurolux" />
    <x-ai-advisor-widget page-context="progressives" />
    <x-ai-advisor-widget page-context="frames" />

  Props:
    page-context  string   Identifies the page for analytics and question routing.
    support-url   string   Override the support link URL.
    api-url       string   Override the API endpoint (rarely needed).
    enabled       boolean  Set to false to suppress the widget on a specific page.
                           The master switch is AI_ADVISOR_ENABLED in .env.
--}}

@props([
    'pageContext' => '',
    'supportUrl'  => 'https://www.39dollarglasses.com/contact',
    'apiUrl'      => '/advisor/ask',
    'enabled'     => true,
])

{{-- Respect master feature flag and per-instance override --}}
@if(config('ai-advisor.enabled', false) && $enabled)

    {{-- Output CSS inline so it loads regardless of @stack('styles') placement --}}
    @once
        <link rel="stylesheet" href="{{ asset('ai-advisor/advisor-widget.css') }}?v={{ config('app.version', '1') }}">
    @endonce

    <script>
        window.advisorConfig = {
            apiUrl:      @js($apiUrl),
            pageContext: @js($pageContext),
            supportUrl:  @js($supportUrl),
        };
    </script>
    <script src="{{ asset('ai-advisor/advisor-widget.js') }}?v={{ config('app.version', '1') }}" defer></script>

@endif
