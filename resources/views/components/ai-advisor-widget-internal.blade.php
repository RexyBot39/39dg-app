{{--
  INTERNAL Lens & Frame Advisor Widget (for customer-service agents).
  Defaults to 39DG, exposes the brand switcher (39DollarGlasses / Ocusafe /
  Onlinecontacts). Mount only on internal/agent pages behind auth.

  Usage:
    <x-ai-advisor-widget-internal />
--}}

@props([
    'pageContext' => 'internal',
    'supportUrl'  => 'https://www.39dollarglasses.com/contact',
    'apiUrl'      => '/advisor/ask',
    'enabled'     => true,
])

@if(config('ai-advisor.enabled', false) && $enabled)

    @once
        <link rel="stylesheet" href="/ai-advisor/advisor-widget.css?v={{ filemtime(public_path('ai-advisor/advisor-widget.css')) }}">
    @endonce

    <script>
        window.advisorConfig = {
            apiUrl:      @js($apiUrl),
            pageContext: @js($pageContext),
            brand:       '39dg',
            supportUrl:  @js($supportUrl),
            allowBrandSwitch: true,
        };
    </script>
    <script src="/ai-advisor/advisor-widget.js?v={{ filemtime(public_path('ai-advisor/advisor-widget.js')) }}" defer></script>

@endif
