<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Advisor (Brand Switcher) — Test</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; }
        .page-header { background: #f6971f; color: white; padding: 20px 32px; }
        .page-header h1 { font-size: 22px; font-weight: 700; }
        .page-header p { font-size: 13px; opacity: .9; margin-top: 4px; }
        .content { max-width: 800px; margin: 40px auto; padding: 0 24px; }
        .card { background: white; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .card h2 { font-size: 18px; margin-bottom: 12px; color: #f6971f; }
        .card p { font-size: 14px; line-height: 1.6; color: #4b5563; margin-bottom: 12px; }
        .card ul { margin-left: 20px; font-size: 14px; color: #4b5563; line-height: 2; }
    </style>
    @stack('styles')
</head>
<body>

<div class="page-header">
    <h1>Internal Advisor — Brand Switcher Test</h1>
    <p>Customer-service tool · defaults to 39DG · switch between brands in the widget header</p>
</div>

<div class="content">
    <div class="card">
        <h2>Brand-scoped ticket retrieval</h2>
        <p>The widget (bottom-right) opens defaulted to <strong>39DollarGlasses</strong>. Use the <strong>switch</strong> link in the header to change brand.</p>
        <p><strong>Test the brand isolation:</strong></p>
        <ul>
            <li>Open the widget — header shows "39DollarGlasses" in orange</li>
            <li>Ask: "Can I return glasses if I lost the original box?" → should give the 39DG packaging policy</li>
            <li>Click "switch" → pick <strong>Ocusafe</strong> → header updates, conversation resets</li>
            <li>Ask: "What's your return policy?" → should give Ocusafe's answer (different from 39DG)</li>
            <li>Switch to <strong>Onlinecontacts</strong> → ask about returns → contacts-specific answer</li>
            <li>Confirm answers never mix brands</li>
        </ul>
    </div>
</div>

<x-ai-advisor-widget-internal />

@stack('scripts')
</body>
</html>
