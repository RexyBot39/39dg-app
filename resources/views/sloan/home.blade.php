<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sloan AI — Your AI Support Assistant</title>
    <meta name="description" content="Sloan AI helps you navigate lens, frame, and prescription questions with expert-backed guidance.">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", system-ui, sans-serif;
            background: #ffffff;
            color: #1c1c1e;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 24px 120px;
        }
        .hero-logo { width: 220px; max-width: 70vw; height: auto; margin-bottom: 32px; }
        .hero h1 {
            font-size: 34px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
            max-width: 640px;
            line-height: 1.15;
        }
        .hero p {
            font-size: 17px;
            line-height: 1.6;
            color: #52525b;
            max-width: 520px;
            margin-bottom: 36px;
        }
        .ask-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #1c1c1e;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: -0.01em;
            transition: transform .15s, box-shadow .15s, background .15s;
            box-shadow: 0 4px 14px rgba(28,28,30,.16);
        }
        .ask-btn:hover { background: #000; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(28,28,30,.22); }
        .ask-btn:active { transform: none; }
        .ask-btn svg { width: 20px; height: 20px; }
        .hero-note { margin-top: 22px; font-size: 13px; color: #9ca3af; }
        .site-footer {
            text-align: center;
            padding: 22px;
            font-size: 12px;
            color: #b4b4ba;
            border-top: 1px solid #ededf0;
        }
        @media (max-width: 480px) {
            .hero h1 { font-size: 27px; }
            .hero p { font-size: 15px; }
        }
    </style>
    @stack('styles')
</head>
<body>

    <main class="hero">
        <img class="hero-logo" src="{{ asset('ai-advisor/sloan-ai-logo.svg') }}" alt="Sloan AI" />
        <h1>Meet Sloan, your AI support assistant</h1>
        <p>Get expert-backed help with lenses, frames, prescriptions, and orders — across 39DollarGlasses, OcuSafe, and OnlineContacts.</p>
        <button class="ask-btn" type="button" onclick="window.__sloanOpen && window.__sloanOpen()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Ask Sloan
        </button>
        <div class="hero-note">Prescription reading · lens &amp; frame guidance · order support</div>
    </main>

    <footer class="site-footer">
        Sloan AI · General product info · not medical advice
    </footer>

    <x-ai-advisor-widget-internal />

    @stack('scripts')
</body>
</html>
