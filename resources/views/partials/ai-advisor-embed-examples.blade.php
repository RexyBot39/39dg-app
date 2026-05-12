{{--
  HOW TO EMBED THE ADVISOR WIDGET

  1. Ensure your layout has @stack('styles') in <head> and @stack('scripts')
     before </body>.

  2. Drop one line on any page where you want the widget.
     The widget appears as a floating button in the bottom-right corner.

  ─────────────────────────────────────────────────────────────────────────────
  Neurolux page:
--}}
<x-ai-advisor-widget page-context="neurolux" />

{{-- Lumeo page: --}}
<x-ai-advisor-widget page-context="lumeo" />

{{-- Blue495 page: --}}
<x-ai-advisor-widget page-context="blue495" />

{{-- OcuSleep page: --}}
<x-ai-advisor-widget page-context="ocusleep" />

{{-- Lens options / How it works page: --}}
<x-ai-advisor-widget page-context="lenses" />

{{-- Progressive lenses page: --}}
<x-ai-advisor-widget page-context="progressives" />

{{-- Eyeglass frames category page: --}}
<x-ai-advisor-widget page-context="frames" />

{{--
  ─────────────────────────────────────────────────────────────────────────────
  LAYOUT REQUIREMENTS

  Your master layout (e.g. resources/views/layouts/app.blade.php) must include:

    <head>
        ...
        @stack('styles')
    </head>
    <body>
        ...
        @stack('scripts')
    </body>

  ─────────────────────────────────────────────────────────────────────────────
  OPTIONAL: CUSTOM ANALYTICS CALLBACK

  To receive advisor events in your own analytics system alongside GA4/PostHog:

    <script>
      window.advisorAnalytics = function(event, data) {
        console.log('[Advisor]', event, data);
        // send to your own endpoint, GTM dataLayer, etc.
      };
    </script>

  Events fired:
    advisor_opened              — widget opened
    advisor_question_asked      — question submitted (question_length, page_context)
    advisor_response_received   — answer returned (answer_type, has_products, has_handoff)
    advisor_product_shown       — product card displayed (product_id, product_title)
    advisor_product_clicked     — product card clicked (product_id, product_title)
    advisor_handoff_shown       — support handoff displayed (reason)
    advisor_handoff_clicked     — support link clicked
    advisor_support_footer_clicked — footer support link clicked
    advisor_error               — API call failed (error)

  ─────────────────────────────────────────────────────────────────────────────
  OPTIONAL: CSS CUSTOMIZATION

  Override CSS variables in your site stylesheet to match your brand:

    :root {
      --advisor-primary:       #1a56a0;   /* primary button/accent color */
      --advisor-primary-dark:  #134082;   /* hover state */
      --advisor-primary-light: #e8f0fb;   /* prompt button background */
      --advisor-accent:        #f59e0b;   /* focus ring */
    }
--}}
