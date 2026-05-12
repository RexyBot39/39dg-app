/**
 * 39DollarGlasses Lens & Frame Advisor Widget
 * Self-contained, no external dependencies.
 *
 * Usage:
 *   <script>
 *     window.advisorConfig = {
 *       apiUrl:      '/api/ai-advisor/ask',
 *       pageContext: 'neurolux',
 *       supportUrl:  'https://www.39dollarglasses.com/contact',
 *     };
 *   </script>
 *   <script src="/ai-advisor/advisor-widget.js" defer></script>
 */

(function () {
  'use strict';

  // ── Suggested prompt buttons ──────────────────────────────────────────────
  const PROMPTS = [
    { label: 'Help me choose lenses',        question: 'Help me understand my lens options. What are the main types?' },
    { label: 'Explain Neurolux',              question: 'What are Neurolux lenses?' },
    { label: 'Explain Lumeo',                 question: 'What are Lumeo lenses?' },
    { label: 'Explain Blue495',               question: 'What is Blue495?' },
    { label: 'What are progressives?',        question: 'What are progressive lenses and how do they work?' },
    { label: 'Help me choose frames',         question: 'How do I choose the right frames for me?' },
    { label: 'Lightweight frames',            question: 'Show me your most lightweight frame options.' },
    { label: 'Frames for strong Rx',          question: 'What frames work best for stronger prescriptions?' },
  ];

  // ── Icons (inline SVG — no external assets required) ─────────────────────
  const ICON_GLASSES = `<svg class="advisor-launcher-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M2 15h0M10 15h4M22 15h0"/><path d="M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/></svg>`;
  const ICON_CLOSE   = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>`;
  const ICON_BACK    = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>`;
  const ICON_SUPPORT = `💬`;

  // ── Widget class ──────────────────────────────────────────────────────────
  class AdvisorWidget {
    constructor(config = {}) {
      this.apiUrl      = config.apiUrl      || '/api/ai-advisor/ask';
      this.pageContext = config.pageContext  || '';
      this.supportUrl  = config.supportUrl  || 'https://www.39dollarglasses.com/contact';
      this.sessionId   = this._getOrCreateSessionId();
      this.isOpen      = false;
      this._root       = null;
      this._panel      = null;
      this._body       = null;

      this._mount();
    }

    // ── Public API ───────────────────────────────────────────────────────────

    open() {
      this.isOpen = true;
      this._panel.classList.add('is-open');
      this._panel.removeAttribute('aria-hidden');
      this._root.querySelector('.advisor-launcher').setAttribute('aria-expanded', 'true');
      this._track('advisor_opened', { page_context: this.pageContext });
    }

    close() {
      this.isOpen = false;
      this._panel.classList.remove('is-open');
      this._panel.setAttribute('aria-hidden', 'true');
      this._root.querySelector('.advisor-launcher').setAttribute('aria-expanded', 'false');
    }

    // ── Mounting ─────────────────────────────────────────────────────────────

    _mount() {
      this._root = document.createElement('div');
      this._root.id = 'advisor-widget';
      this._root.setAttribute('role', 'complementary');
      this._root.setAttribute('aria-label', 'Lens & Frame Advisor');
      document.body.appendChild(this._root);

      this._renderShell();
      this._showInitialState();
    }

    _renderShell() {
      // Launcher button
      const launcher = document.createElement('button');
      launcher.className = 'advisor-launcher';
      launcher.setAttribute('aria-expanded', 'false');
      launcher.setAttribute('aria-controls', 'advisor-panel');
      launcher.innerHTML = `${ICON_GLASSES}<span>Need help choosing?</span>`;
      launcher.addEventListener('click', () => this.isOpen ? this.close() : this.open());
      this._root.appendChild(launcher);

      // Panel
      const panel = document.createElement('div');
      panel.className = 'advisor-panel';
      panel.id = 'advisor-panel';
      panel.setAttribute('aria-hidden', 'true');
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'Lens and Frame Advisor');
      this._panel = panel;

      // Header
      const header = document.createElement('div');
      header.className = 'advisor-header';
      header.innerHTML = `
        <div class="advisor-header-left">
          <div class="advisor-header-icon" aria-hidden="true">🕶️</div>
          <div>
            <div class="advisor-header-title">Lens &amp; Frame Advisor</div>
            <div class="advisor-header-subtitle">39DollarGlasses</div>
          </div>
        </div>
      `;
      const closeBtn = document.createElement('button');
      closeBtn.className = 'advisor-close-btn';
      closeBtn.setAttribute('aria-label', 'Close advisor');
      closeBtn.innerHTML = ICON_CLOSE;
      closeBtn.addEventListener('click', () => this.close());
      header.appendChild(closeBtn);
      panel.appendChild(header);

      // Body
      const body = document.createElement('div');
      body.className = 'advisor-body';
      this._body = body;
      panel.appendChild(body);

      // Footer
      const footer = document.createElement('div');
      footer.className = 'advisor-footer';
      const supportLink = document.createElement('a');
      supportLink.href = this.supportUrl;
      supportLink.textContent = 'Talk to a human';
      supportLink.target = '_blank';
      supportLink.rel = 'noopener noreferrer';
      supportLink.addEventListener('click', () => this._track('advisor_support_footer_clicked'));
      const sep = document.createElement('span');
      sep.className = 'advisor-footer-sep';
      sep.textContent = '·';
      const note = document.createElement('span');
      note.textContent = 'General product info only';
      footer.appendChild(supportLink);
      footer.appendChild(sep);
      footer.appendChild(note);
      panel.appendChild(footer);

      this._root.appendChild(panel);
    }

    // ── States ───────────────────────────────────────────────────────────────

    _showInitialState() {
      this._clearBody();

      // Welcome text
      const welcome = document.createElement('p');
      welcome.className = 'advisor-welcome-text';
      welcome.textContent = 'I can explain lens and frame options and suggest specific products. Choose a topic or ask your own question.';
      this._body.appendChild(welcome);

      // Prompt buttons
      const promptsWrap = document.createElement('div');
      promptsWrap.className = 'advisor-prompts';
      PROMPTS.forEach(({ label, question }) => {
        const btn = document.createElement('button');
        btn.className = 'advisor-prompt-btn';
        btn.textContent = label;
        btn.addEventListener('click', () => this._submit(question));
        promptsWrap.appendChild(btn);
      });
      this._body.appendChild(promptsWrap);

      // Divider
      const divider = document.createElement('div');
      divider.className = 'advisor-divider';
      divider.textContent = 'or ask your own question';
      this._body.appendChild(divider);

      // Free-text input row
      const inputRow = document.createElement('div');
      inputRow.className = 'advisor-input-row';

      const textarea = document.createElement('textarea');
      textarea.className = 'advisor-textarea';
      textarea.placeholder = 'e.g. What coating is best for computer use?';
      textarea.rows = 2;
      textarea.maxLength = 500;
      textarea.setAttribute('aria-label', 'Your question');
      textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (textarea.value.trim()) this._submit(textarea.value.trim());
        }
      });

      const submitBtn = document.createElement('button');
      submitBtn.className = 'advisor-submit-btn';
      submitBtn.textContent = 'Ask';
      submitBtn.addEventListener('click', () => {
        if (textarea.value.trim()) this._submit(textarea.value.trim());
      });

      inputRow.appendChild(textarea);
      inputRow.appendChild(submitBtn);
      this._body.appendChild(inputRow);
    }

    _showLoadingState() {
      this._clearBody();

      const wrap = document.createElement('div');
      wrap.className = 'advisor-loading';
      wrap.setAttribute('aria-live', 'polite');
      wrap.setAttribute('aria-busy', 'true');

      const spinner = document.createElement('div');
      spinner.className = 'advisor-spinner';
      spinner.setAttribute('role', 'status');

      const text = document.createElement('p');
      text.textContent = 'Looking that up…';

      wrap.appendChild(spinner);
      wrap.appendChild(text);
      this._body.appendChild(wrap);
    }

    _showResponseState(data, question) {
      this._clearBody();

      // Back button
      const backBtn = document.createElement('button');
      backBtn.className = 'advisor-back-btn';
      backBtn.innerHTML = `${ICON_BACK} Ask another question`;
      backBtn.addEventListener('click', () => this._showInitialState());
      this._body.appendChild(backBtn);

      // Main answer
      if (data.short_answer) {
        const answer = document.createElement('p');
        answer.className = 'advisor-answer';
        answer.textContent = data.short_answer;
        this._body.appendChild(answer);
      }

      // Educational bullet points
      if (data.educational_points && data.educational_points.length > 0) {
        const list = document.createElement('ul');
        list.className = 'advisor-points';
        data.educational_points.forEach((point) => {
          const li = document.createElement('li');
          li.textContent = point;
          list.appendChild(li);
        });
        this._body.appendChild(list);
      }

      // Product cards
      if (data.recommended_products && data.recommended_products.length > 0) {
        const heading = document.createElement('div');
        heading.className = 'advisor-products-heading';
        heading.textContent = 'Suggested frames';
        this._body.appendChild(heading);

        const productList = document.createElement('div');
        productList.className = 'advisor-products-list';

        data.recommended_products.forEach((product) => {
          const card = this._buildProductCard(product);
          productList.appendChild(card);
          this._track('advisor_product_shown', {
            product_id:    product.product_id,
            product_title: product.title,
            page_context:  this.pageContext,
          });
        });

        this._body.appendChild(productList);
      }

      // Support handoff
      if (data.support_handoff && data.support_handoff.needed) {
        const handoff = this._buildHandoff(data.support_handoff.message);
        this._body.appendChild(handoff);
        this._track('advisor_handoff_shown', {
          reason: data.support_handoff.reason,
        });
      }

      // Disclaimer
      if (data.disclaimer) {
        const disclaimer = document.createElement('p');
        disclaimer.className = 'advisor-disclaimer';
        disclaimer.textContent = data.disclaimer;
        this._body.appendChild(disclaimer);
      }

      this._track('advisor_response_received', {
        answer_type:    data.answer_type,
        has_products:   (data.recommended_products || []).length > 0,
        has_handoff:    !!(data.support_handoff && data.support_handoff.needed),
        page_context:   this.pageContext,
      });
    }

    _showErrorState() {
      this._clearBody();

      const backBtn = document.createElement('button');
      backBtn.className = 'advisor-back-btn';
      backBtn.innerHTML = `${ICON_BACK} Try again`;
      backBtn.addEventListener('click', () => this._showInitialState());
      this._body.appendChild(backBtn);

      const err = document.createElement('div');
      err.className = 'advisor-error';
      err.setAttribute('role', 'alert');
      err.textContent = "Something went wrong on our end. Please try again or contact our support team directly.";
      this._body.appendChild(err);

      const handoff = this._buildHandoff(null);
      this._body.appendChild(handoff);
    }

    // ── Building blocks ──────────────────────────────────────────────────────

    _buildProductCard(product) {
      const card = document.createElement('a');
      card.className = 'advisor-product-card';
      card.href = product.public_url;
      card.target = '_blank';
      card.rel = 'noopener noreferrer';
      card.setAttribute('aria-label', `View ${product.title} — ${product.price}`);
      card.addEventListener('click', () => {
        this._track('advisor_product_clicked', {
          product_id:    product.product_id,
          product_title: product.title,
          page_context:  this.pageContext,
        });
      });

      // Image
      const imgWrap = document.createElement('div');
      imgWrap.className = 'advisor-product-img-wrap';

      if (product.image_url) {
        const img = document.createElement('img');
        img.className = 'advisor-product-img';
        img.alt = product.title;
        img.loading = 'lazy';
        img.src = product.image_url;
        img.onerror = function () {
          this.parentNode.innerHTML = `<span class="advisor-product-img-placeholder" aria-hidden="true">🕶️</span>`;
        };
        imgWrap.appendChild(img);
      } else {
        const placeholder = document.createElement('span');
        placeholder.className = 'advisor-product-img-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = '🕶️';
        imgWrap.appendChild(placeholder);
      }
      card.appendChild(imgWrap);

      // Info
      const info = document.createElement('div');
      info.className = 'advisor-product-info';

      const title = document.createElement('div');
      title.className = 'advisor-product-title';
      title.textContent = product.title;
      info.appendChild(title);

      if (product.reason) {
        const reason = document.createElement('div');
        reason.className = 'advisor-product-reason';
        reason.textContent = product.reason;
        info.appendChild(reason);
      }

      const footer = document.createElement('div');
      footer.className = 'advisor-product-footer';

      const price = document.createElement('span');
      price.className = 'advisor-product-price';
      price.textContent = product.price;
      footer.appendChild(price);

      const cta = document.createElement('span');
      cta.className = 'advisor-product-cta';
      cta.setAttribute('aria-hidden', 'true'); // card itself is the link
      cta.textContent = 'View →';
      footer.appendChild(cta);

      info.appendChild(footer);
      card.appendChild(info);

      return card;
    }

    _buildHandoff(message) {
      const wrap = document.createElement('div');
      wrap.className = 'advisor-handoff';

      const icon = document.createElement('div');
      icon.className = 'advisor-handoff-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = ICON_SUPPORT;
      wrap.appendChild(icon);

      const text = document.createElement('div');
      text.className = 'advisor-handoff-text';

      const strong = document.createElement('strong');
      strong.textContent = 'Need account or order help?';
      text.appendChild(strong);

      const desc = document.createElement('span');
      desc.textContent = message || 'Our support team can help with orders, prescriptions, refunds, remakes, and account questions.';
      text.appendChild(desc);

      const link = document.createElement('a');
      link.className = 'advisor-handoff-link';
      link.href = this.supportUrl;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'Contact customer support →';
      link.addEventListener('click', () => this._track('advisor_handoff_clicked'));
      text.appendChild(document.createElement('br'));
      text.appendChild(link);

      wrap.appendChild(text);
      return wrap;
    }

    // ── API call ─────────────────────────────────────────────────────────────

    async _submit(question) {
      if (!question || !question.trim()) return;

      this._track('advisor_question_asked', {
        page_context: this.pageContext,
        question_length: question.length,
      });

      this._showLoadingState();

      try {
        const response = await fetch(this.apiUrl, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body:    JSON.stringify({
            question:         question.trim(),
            page_context:     this.pageContext,
            session_id:       this.sessionId,
            site:             '39dollarglasses',
          }),
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        this._showResponseState(data, question);

      } catch (err) {
        console.error('[AdvisorWidget] API error:', err);
        this._showErrorState();
        this._track('advisor_error', { error: err.message });
      }
    }

    // ── Utilities ────────────────────────────────────────────────────────────

    _clearBody() {
      while (this._body.firstChild) {
        this._body.removeChild(this._body.firstChild);
      }
    }

    _getOrCreateSessionId() {
      try {
        let id = sessionStorage.getItem('advisor_session_id');
        if (!id) {
          id = ([1e7] + -1e3 + -4e3 + -8e3 + -1e11)
            .replace(/[018]/g, c =>
              (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)
            );
          sessionStorage.setItem('advisor_session_id', id);
        }
        return id;
      } catch {
        return null;
      }
    }

    _track(event, data = {}) {
      // GA4
      if (typeof window.gtag === 'function') {
        window.gtag('event', event, {
          event_category: 'advisor_widget',
          ...data,
        });
      }

      // PostHog
      if (typeof window.posthog !== 'undefined' && typeof window.posthog.capture === 'function') {
        window.posthog.capture(event, { source: 'advisor_widget', ...data });
      }

      // Custom callback
      if (typeof window.advisorAnalytics === 'function') {
        window.advisorAnalytics(event, data);
      }
    }
  }

  // ── Auto-initialize ───────────────────────────────────────────────────────
  function init() {
    if (document.getElementById('advisor-widget')) return; // already mounted
    const config = window.advisorConfig || {};
    window._advisorWidget = new AdvisorWidget(config);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose class for manual initialization
  window.AdvisorWidget = AdvisorWidget;

})();
