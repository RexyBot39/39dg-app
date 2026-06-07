/**
 * 39DollarGlasses Lens & Frame Advisor Widget v3
 * Self-contained — no external dependencies.
 * Zendesk Web Widget (classic) handoff.
 */
(function () {
  'use strict';

  const PROMPTS = [
    { label: 'Help me choose lenses',  question: 'Help me understand my lens options. What are the main types?' },
    { label: 'Neurolux lenses',        question: 'What are Neurolux lenses?' },
    { label: 'Lumeo lenses',           question: 'What are Lumeo lenses?' },
    { label: 'Blue495 coating',        question: 'What is Blue495?' },
    { label: 'Progressive lenses',     question: 'What are progressive lenses and how do they work?' },
    { label: 'Help me choose frames',  question: 'How do I choose the right frames for me?' },
    { label: 'Lightweight frames',     question: 'Show me your most lightweight frame options.' },
    { label: 'Frames for strong Rx',   question: 'What frames work best for stronger prescriptions?' },
  ];

  const I = {
    glasses: `<svg class="advisor-launcher-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M2 15h0M10 15h4M22 15h0"/><path d="M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/></svg>`,
    close:   `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>`,
    back:    `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>`,
    send:    `<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg>`,
    chat:    `<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,
  };

  // ── Zendesk ────────────────────────────────────────────────────────────────
  function openZendesk(fallback, prefill) {
    if (typeof window.zE !== 'function') {
      window.open(fallback || 'https://www.39dollarglasses.com/contact', '_blank', 'noopener');
      return;
    }
    try {
      if (prefill) {
        window.zE('webWidget', 'prefill', {
          subject:     { value: 'Advisor handoff', readOnly: false },
          description: { value: prefill, readOnly: false },
        });
      }
      window.zE('webWidget', 'open');
    } catch(e) {
      window.open(fallback || 'https://www.39dollarglasses.com/contact', '_blank', 'noopener');
    }
  }

  function prefillMsg(q, type) {
    return `Customer transferred from AI Lens & Frame Advisor.\n\nLast question: "${q}"\nResponse type: ${type || 'unknown'}\n\nPlease assist.`;
  }

  // ── Widget ─────────────────────────────────────────────────────────────────
  class AdvisorWidget {
    constructor(cfg = {}) {
      this.api        = cfg.apiUrl     || '/advisor/ask';
      this.ctx        = cfg.pageContext || '';
      this.support    = cfg.supportUrl || 'https://www.39dollarglasses.com/contact';
      this.sid        = this._sid();
      this.open       = false;
      this._panel     = null;
      this._body      = null;
      this._lastQ     = '';
      this._lastType  = '';
      this._build();
    }

    toggle() { this.open ? this._close() : this._open(); }

    _open() {
      this.open = true;
      this._panel.classList.add('is-open');
      this._panel.removeAttribute('aria-hidden');
      this._root.querySelector('.advisor-launcher').setAttribute('aria-expanded', 'true');
      this._track('advisor_opened');
    }

    _close() {
      this.open = false;
      this._panel.classList.remove('is-open');
      this._panel.setAttribute('aria-hidden', 'true');
      this._root.querySelector('.advisor-launcher').setAttribute('aria-expanded', 'false');
    }

    _build() {
      const root = document.createElement('div');
      root.id = 'advisor-widget';
      root.setAttribute('role', 'complementary');
      root.setAttribute('aria-label', 'Lens & Frame Advisor');
      this._root = root;
      document.body.appendChild(root);

      // Launcher
      const btn = document.createElement('button');
      btn.className = 'advisor-launcher';
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', 'advisor-panel');
      btn.innerHTML = `${I.glasses}<span>Need help choosing?</span><span class="advisor-launcher-dot" aria-hidden="true"></span>`;
      btn.addEventListener('click', () => this.toggle());
      root.appendChild(btn);

      // Panel
      const panel = document.createElement('div');
      panel.className = 'advisor-panel';
      panel.id = 'advisor-panel';
      panel.setAttribute('aria-hidden', 'true');
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'Lens and Frame Advisor');
      this._panel = panel;

      // Header
      const hdr = document.createElement('div');
      hdr.className = 'advisor-header';
      hdr.innerHTML = `
        <div class="advisor-header-left">
          <div class="advisor-header-avatar" aria-hidden="true">🕶️</div>
          <div>
            <div class="advisor-header-name">Lens &amp; Frame Advisor</div>
            <div class="advisor-header-sub">
              <span class="advisor-header-online" aria-hidden="true"></span>
              <span>Online · 39DollarGlasses</span>
            </div>
          </div>
        </div>
      `;
      const xBtn = document.createElement('button');
      xBtn.className = 'advisor-header-close';
      xBtn.setAttribute('aria-label', 'Close');
      xBtn.innerHTML = I.close;
      xBtn.addEventListener('click', () => this._close());
      hdr.appendChild(xBtn);
      panel.appendChild(hdr);

      // Body
      const body = document.createElement('div');
      body.className = 'advisor-body';
      this._body = body;
      panel.appendChild(body);

      // Footer
      const ftr = document.createElement('div');
      ftr.className = 'advisor-footer';

      const fl = document.createElement('div');
      fl.className = 'advisor-footer-left';
      const sl = document.createElement('a');
      sl.href = this.support; sl.textContent = 'Support';
      sl.target = '_blank'; sl.rel = 'noopener noreferrer';
      const sep = document.createElement('span');
      sep.className = 'advisor-footer-sep'; sep.textContent = '·';
      const note = document.createElement('span');
      note.textContent = 'Product info only';
      fl.append(sl, sep, note);

      const livBtn = document.createElement('button');
      livBtn.className = 'advisor-footer-live';
      livBtn.innerHTML = `${I.chat} Live chat`;
      livBtn.addEventListener('click', () => this._handoff());
      ftr.append(fl, livBtn);
      panel.appendChild(ftr);
      root.appendChild(panel);

      this._home();
    }

    // ── Screens ──────────────────────────────────────────────────────────────

    _home() {
      this._clear();

      // Welcome card
      const welcome = document.createElement('div');
      welcome.className = 'advisor-welcome';
      welcome.textContent = 'I can explain lens and frame options and suggest specific products from our catalog. Choose a topic or ask your own question.';
      this._body.appendChild(welcome);

      // Prompts
      const lbl = document.createElement('div');
      lbl.className = 'advisor-label'; lbl.textContent = 'Quick topics';
      this._body.appendChild(lbl);

      const chips = document.createElement('div');
      chips.className = 'advisor-prompts';
      PROMPTS.forEach(({ label, question }) => {
        const c = document.createElement('button');
        c.className = 'advisor-chip'; c.textContent = label;
        c.addEventListener('click', () => this._ask(question));
        chips.appendChild(c);
      });
      this._body.appendChild(chips);

      // Divider
      const div = document.createElement('div');
      div.className = 'advisor-divider'; div.textContent = 'or ask your own question';
      this._body.appendChild(div);

      // Input
      const wrap = document.createElement('div');
      wrap.className = 'advisor-input-wrap';

      const ta = document.createElement('textarea');
      ta.className = 'advisor-textarea';
      ta.placeholder = 'e.g. What coating is best for computer use?';
      ta.rows = 3; ta.maxLength = 500;
      ta.setAttribute('aria-label', 'Your question');

      const bar = document.createElement('div');
      bar.className = 'advisor-input-bar';

      const cc = document.createElement('span');
      cc.className = 'advisor-char';

      ta.addEventListener('input', () => {
        const n = ta.value.length;
        cc.textContent = n > 400 ? `${n}/500` : '';
        cc.classList.toggle('warn', n > 460);
      });
      ta.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (ta.value.trim()) this._ask(ta.value.trim());
        }
      });

      const sendBtn = document.createElement('button');
      sendBtn.className = 'advisor-send';
      sendBtn.innerHTML = `${I.send} Ask`;
      sendBtn.addEventListener('click', () => { if (ta.value.trim()) this._ask(ta.value.trim()); });

      bar.append(cc, sendBtn);
      wrap.append(ta, bar);
      this._body.appendChild(wrap);
    }

    _loading() {
      this._clear();
      const w = document.createElement('div');
      w.className = 'advisor-loading';
      w.setAttribute('aria-live', 'polite');
      const dots = document.createElement('div');
      dots.className = 'advisor-dots';
      dots.setAttribute('role', 'status');
      dots.setAttribute('aria-label', 'Loading');
      [0,1,2].forEach(() => dots.appendChild(document.createElement('span')));
      const t = document.createElement('p'); t.textContent = 'Looking that up…';
      w.append(dots, t);
      this._body.appendChild(w);
    }

    _result(data, q) {
      this._clear();
      this._lastQ = q; this._lastType = data.answer_type;

      const back = document.createElement('button');
      back.className = 'advisor-back';
      back.innerHTML = `${I.back} Ask another question`;
      back.addEventListener('click', () => this._home());
      this._body.appendChild(back);

      if (data.short_answer) {
        const a = document.createElement('p');
        a.className = 'advisor-answer'; a.textContent = data.short_answer;
        this._body.appendChild(a);
      }

      if (data.educational_points?.length) {
        const ul = document.createElement('ul');
        ul.className = 'advisor-bullets';
        data.educational_points.forEach(pt => {
          const li = document.createElement('li'); li.textContent = pt;
          ul.appendChild(li);
        });
        this._body.appendChild(ul);
      }

      if (data.recommended_products?.length) {
        const lbl = document.createElement('div');
        lbl.className = 'advisor-products-label'; lbl.textContent = 'Suggested frames';
        this._body.appendChild(lbl);
        const list = document.createElement('div');
        list.className = 'advisor-products';
        data.recommended_products.forEach(p => {
          list.appendChild(this._card(p));
          this._track('advisor_product_shown', { product_id: p.product_id });
        });
        this._body.appendChild(list);
      }

      if (data.support_handoff?.needed) {
        this._body.appendChild(this._handoffCard(data.support_handoff.message));
      }

      if (data.disclaimer) {
        const d = document.createElement('p');
        d.className = 'advisor-disclaimer'; d.textContent = data.disclaimer;
        this._body.appendChild(d);
      }

      this._track('advisor_response_received', { answer_type: data.answer_type });
    }

    _error() {
      this._clear();
      const back = document.createElement('button');
      back.className = 'advisor-back';
      back.innerHTML = `${I.back} Try again`;
      back.addEventListener('click', () => this._home());
      this._body.appendChild(back);
      const err = document.createElement('div');
      err.className = 'advisor-error';
      err.setAttribute('role', 'alert');
      err.textContent = 'Something went wrong. Please try again or start a live chat.';
      this._body.appendChild(err);
      this._body.appendChild(this._handoffCard(null));
    }

    // ── Components ────────────────────────────────────────────────────────────

    _card(p) {
      const a = document.createElement('a');
      a.className = 'advisor-card';
      a.href = p.public_url; a.target = '_blank'; a.rel = 'noopener noreferrer';
      a.setAttribute('aria-label', `View ${p.title} — ${p.price}`);
      a.addEventListener('click', () => this._track('advisor_product_clicked', { product_id: p.product_id }));

      const imgWrap = document.createElement('div');
      imgWrap.className = 'advisor-card-img';
      if (p.image_url) {
        const img = document.createElement('img');
        img.alt = p.title; img.loading = 'lazy'; img.src = p.image_url;
        img.onerror = function() { this.parentNode.innerHTML = `<span class="advisor-card-img-ph" aria-hidden="true">🕶️</span>`; };
        imgWrap.appendChild(img);
      } else {
        imgWrap.innerHTML = `<span class="advisor-card-img-ph" aria-hidden="true">🕶️</span>`;
      }
      a.appendChild(imgWrap);

      const body = document.createElement('div');
      body.className = 'advisor-card-body';

      const name = document.createElement('div');
      name.className = 'advisor-card-name'; name.textContent = p.title;
      body.appendChild(name);

      if (p.reason) {
        const why = document.createElement('div');
        why.className = 'advisor-card-why'; why.textContent = p.reason;
        body.appendChild(why);
      }

      const foot = document.createElement('div');
      foot.className = 'advisor-card-foot';
      const price = document.createElement('span');
      price.className = 'advisor-card-price'; price.textContent = p.price;
      const cta = document.createElement('span');
      cta.className = 'advisor-card-cta'; cta.textContent = 'View →';
      cta.setAttribute('aria-hidden', 'true');
      foot.append(price, cta);
      body.appendChild(foot);
      a.appendChild(body);
      return a;
    }

    _handoffCard(msg) {
      const wrap = document.createElement('div');
      wrap.className = 'advisor-handoff';

      const top = document.createElement('div');
      top.className = 'advisor-handoff-top';

      const iconWrap = document.createElement('div');
      iconWrap.className = 'advisor-handoff-icon-wrap';
      iconWrap.setAttribute('aria-hidden', 'true');
      iconWrap.textContent = '💬';

      const txt = document.createElement('div');
      const title = document.createElement('div');
      title.className = 'advisor-handoff-title';
      title.textContent = 'Need account or order help?';
      const desc = document.createElement('div');
      desc.className = 'advisor-handoff-desc';
      desc.textContent = msg || 'Our team handles orders, prescriptions, refunds, and account questions.';
      txt.append(title, desc);
      top.append(iconWrap, txt);
      wrap.appendChild(top);

      const btn = document.createElement('button');
      btn.className = 'advisor-handoff-btn';
      btn.innerHTML = `${I.chat} Start live chat`;
      btn.addEventListener('click', () => this._handoff());
      wrap.appendChild(btn);
      return wrap;
    }

    // ── Zendesk ───────────────────────────────────────────────────────────────

    _handoff() {
      this._track('advisor_zendesk_handoff');
      openZendesk(this.support, this._lastQ ? prefillMsg(this._lastQ, this._lastType) : null);
    }

    // ── API ───────────────────────────────────────────────────────────────────

    async _ask(q) {
      if (!q?.trim()) return;
      this._track('advisor_question_asked', { question_length: q.length });
      this._loading();
      try {
        const res = await fetch(this.api, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ question: q.trim(), page_context: this.ctx, session_id: this.sid, site: '39dollarglasses' }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        this._result(await res.json(), q);
      } catch(e) {
        console.error('[Advisor]', e);
        this._error();
        this._track('advisor_error', { error: e.message });
      }
    }

    // ── Utils ─────────────────────────────────────────────────────────────────

    _clear() { this._body.replaceChildren(); }

    _sid() {
      try {
        let id = sessionStorage.getItem('adv_sid');
        if (!id) {
          id = ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
            (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16));
          sessionStorage.setItem('adv_sid', id);
        }
        return id;
      } catch { return null; }
    }

    _track(ev, d = {}) {
      if (typeof window.gtag === 'function') window.gtag('event', ev, { event_category: 'advisor_widget', ...d });
      if (window.posthog?.capture) window.posthog.capture(ev, { source: 'advisor_widget', ...d });
      if (typeof window.advisorAnalytics === 'function') window.advisorAnalytics(ev, d);
    }
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    if (document.getElementById('advisor-widget')) return;
    window._advisorWidget = new AdvisorWidget(window.advisorConfig || {});
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();

  window.AdvisorWidget = AdvisorWidget;
})();
