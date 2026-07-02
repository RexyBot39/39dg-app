/**
 * 39DollarGlasses Lens & Frame Advisor — v4 "Premium"
 * Self-contained. Zendesk Web Widget (classic) handoff.
 * Brand palette: orange #f6971f · gray #787878.
 */
(function () {
  'use strict';

  // Topic rows — icon + label + question
  const TOPICS = [
    { ico: 'scan',  label: 'Read my prescription',        q: '__READ_RX__' },
    { ico: 'lens',  label: 'Help me choose lenses',     q: 'Help me understand my lens options. What are the main types?' },
    { ico: 'frame', label: 'Help me choose frames',     q: 'How do I choose the right frames for me?' },
    { ico: 'prog',  label: 'Progressive lenses',        q: 'What are progressive lenses and how do they work?' },
    { ico: 'blue',  label: 'Blue light & Blue495',      q: 'What is Blue495 and how does blue light filtering work?' },
    { ico: 'rx',    label: 'Frames for strong Rx',      q: 'What frames work best for stronger prescriptions?' },
    { ico: 'light', label: 'Lightweight frames',        q: 'Show me your most lightweight frame options.' },
  ];

  // 39DG logo (inline SVG, brand colors baked in)
  const LOGO = `<svg class="advisor-logo" viewBox="0 0 469.09 133.87" xmlns="http://www.w3.org/2000/svg" aria-label="39DollarGlasses"><defs><style>.l1{fill:#787878}.l2{fill:#f6971f}</style></defs><g><path class="l1" d="M53.14,46.26a10.09,10.09,0,0,1,2.35,3.22,11.69,11.69,0,0,1,.92,5,13.82,13.82,0,0,1-1.1,5.57,13.26,13.26,0,0,1-3.1,4.41A14.06,14.06,0,0,1,47,67.56a21.05,21.05,0,0,1-6.63,1,30.38,30.38,0,0,1-7.27-.88,34.14,34.14,0,0,1-5.87-1.92V59.53h.45a24.31,24.31,0,0,0,6,2.77,21.57,21.57,0,0,0,6.62,1.1,14.12,14.12,0,0,0,4-.63,8.45,8.45,0,0,0,3.43-1.85,8.7,8.7,0,0,0,2-2.89,10.28,10.28,0,0,0,.67-4,9,9,0,0,0-.76-3.95,6.07,6.07,0,0,0-2.1-2.46,8.23,8.23,0,0,0-3.25-1.27A23.33,23.33,0,0,0,40.12,46H37.43V41.07h2.09a12.36,12.36,0,0,0,7.23-1.89,6.33,6.33,0,0,0,2.7-5.53,5.42,5.42,0,0,0-2.59-4.8,8.58,8.58,0,0,0-2.74-1.07,16.67,16.67,0,0,0-3.31-.3,19.93,19.93,0,0,0-6,1,24.61,24.61,0,0,0-6,2.86h-.3V25.13a28.84,28.84,0,0,1,5.65-1.92A28.3,28.3,0,0,1,41,22.32a24.59,24.59,0,0,1,5.72.6,14.09,14.09,0,0,1,4.47,1.91A9.25,9.25,0,0,1,55.5,33a9.09,9.09,0,0,1-2.61,6.45A11.43,11.43,0,0,1,46.73,43v.42a15.52,15.52,0,0,1,3.28,1A11,11,0,0,1,53.14,46.26Z" transform="translate(-19.87 -1)"/></g></svg>`;
  // Note: full multi-path logo is large; the wordmark text fallback is rendered alongside.

  const I = {
    glasses: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M2 15h0M10 15h4M22 15h0"/><path d="M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/></svg>`,
    close:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" width="16" height="16" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>`,
    back:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>`,
    arrow:   `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>`,
    send:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg>`,
    chat:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,
    check:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>`,
    scan:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>`,
  };

  const TOPIC_ICONS = {
    lens:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18"/></svg>`,
    frame: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="14" r="3.5"/><circle cx="18" cy="14" r="3.5"/><path d="M9.5 14h5M3 13l1-2M21 13l-1-2"/></svg>`,
    prog:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M5 12h14M8 18h8"/></svg>`,
    blue:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>`,
    rx:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h6a4 4 0 0 1 0 8H5zM5 12l8 8M11 16l5-5"/></svg>`,
    light: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>`,
    scan:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>`,
  };

  // Brands selectable in the internal tool. Key = value sent to the API.
  const BRANDS = [
    { key: '39dg',           label: '39DollarGlasses' },
    { key: 'ocusafe',        label: 'Ocusafe' },
    { key: 'onlinecontacts', label: 'Onlinecontacts' },
  ];
  const brandLabel = (k) => (BRANDS.find(b => b.key === k) || BRANDS[0]).label;

  function openZendesk(fallback, prefill) {
    if (typeof window.zE !== 'function') {
      window.open(fallback || 'https://www.39dollarglasses.com/contact', '_blank', 'noopener');
      return;
    }
    try {
      if (prefill) window.zE('webWidget', 'prefill', {
        subject: { value: 'Advisor handoff', readOnly: false },
        description: { value: prefill, readOnly: false },
      });
      window.zE('webWidget', 'open');
    } catch (e) {
      window.open(fallback || 'https://www.39dollarglasses.com/contact', '_blank', 'noopener');
    }
  }
  const prefillMsg = (q, t) =>
    `Customer transferred from Sloan AI Support Assistant.\n\nLast question: "${q}"\nResponse type: ${t || 'unknown'}\n\nPlease assist.`;

  class AdvisorWidget {
    constructor(cfg = {}) {
      this.api = cfg.apiUrl || '/advisor/ask';
      this.rxApi = this.api.replace(/\/ask$/, '/read-prescription');
      this.ctx = cfg.pageContext || '';
      this.support = cfg.supportUrl || 'https://www.39dollarglasses.com/contact';
      this.logoUrl = cfg.logoUrl || '/ai-advisor/sloan-ai-logo.svg';
      this.sid = this._sid();
      this.open = false;
      this._lastQ = ''; this._lastType = '';
      this.turns = [];           // conversation history for this open session
      this._threadMode = false;  // false = topics screen, true = active thread

      // Brand handling. Public widgets hardcode `brand`. The internal tool
      // passes allowBrandSwitch:true to expose the switcher.
      this.brand = cfg.brand || '39dg';
      this.allowBrandSwitch = !!cfg.allowBrandSwitch;
      this._brandPickerOpen = false;
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
      this.turns = [];
      this._threadMode = false;
      this._panel.classList.remove('is-open');
      this._panel.setAttribute('aria-hidden', 'true');
      this._root.querySelector('.advisor-launcher').setAttribute('aria-expanded', 'false');
    }

    _toggleBrandPicker() {
      if (!this._picker) return;
      this._brandPickerOpen = !this._brandPickerOpen;
      this._picker.classList.toggle('is-open', this._brandPickerOpen);
      this._picker.setAttribute('aria-hidden', this._brandPickerOpen ? 'false' : 'true');
    }

    _setBrand(key) {
      this.brand = key;
      // Update active label in header
      const active = this._root.querySelector('.advisor-brand-active');
      if (active) active.textContent = brandLabel(key);
      // Update active state in picker
      this._picker.querySelectorAll('.advisor-brand-option').forEach(opt => {
        opt.classList.toggle('is-active', opt.textContent === brandLabel(key));
      });
      this._toggleBrandPicker();
      // Reset to home so the conversation starts fresh under the new brand
      this._home();
      this._track('advisor_brand_switched', { brand: key });
    }

    _build() {
      const root = document.createElement('div');
      root.id = 'advisor-widget';
      root.setAttribute('role', 'complementary');
      root.setAttribute('aria-label', 'Sloan AI Support Assistant');
      this._root = root;
      document.body.appendChild(root);

      // Launcher
      const btn = document.createElement('button');
      btn.className = 'advisor-launcher';
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', 'advisor-panel');
      btn.innerHTML = `<span class="advisor-launcher-badge">${I.glasses}</span><span>Need help choosing?</span><span class="advisor-launcher-dot" aria-hidden="true"></span>`;
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

      // Header — real logo via <img>, with a wordmark divider + role label
      const hdr = document.createElement('div');
      hdr.className = 'advisor-header';
      const brandRow = this.allowBrandSwitch
        ? `<button class="advisor-brand-switch" type="button" aria-label="Switch brand">
             <span class="advisor-brand-active">${brandLabel(this.brand)}</span>
             <span class="advisor-brand-switch-link">switch</span>
           </button>`
        : '';
      hdr.innerHTML = `
        <div class="advisor-header-left">
          <img class="advisor-logo" src="${this.logoUrl}" alt="Sloan AI" />
          <div class="advisor-header-divide"></div>
          <div class="advisor-header-meta">
            <div class="advisor-header-title">Your AI Support Assistant</div>
            <div class="advisor-header-status">
              <span class="advisor-header-status-dot" aria-hidden="true"></span>
              <span>Online now</span>
            </div>
            ${brandRow}
          </div>
        </div>
      `;
      const x = document.createElement('button');
      x.className = 'advisor-close';
      x.setAttribute('aria-label', 'Close');
      x.innerHTML = I.close;
      x.addEventListener('click', () => this._close());
      hdr.appendChild(x);

      // Brand picker (inline list), hidden until "switch" is clicked
      if (this.allowBrandSwitch) {
        const switchBtn = hdr.querySelector('.advisor-brand-switch');
        switchBtn.addEventListener('click', () => this._toggleBrandPicker());

        const picker = document.createElement('div');
        picker.className = 'advisor-brand-picker';
        picker.setAttribute('aria-hidden', 'true');
        BRANDS.forEach(b => {
          const opt = document.createElement('button');
          opt.type = 'button';
          opt.className = 'advisor-brand-option' + (b.key === this.brand ? ' is-active' : '');
          opt.textContent = b.label;
          opt.addEventListener('click', () => this._setBrand(b.key));
          picker.appendChild(opt);
        });
        this._picker = picker;
        hdr.appendChild(picker);
      }
      panel.appendChild(hdr);

      const body = document.createElement('div');
      body.className = 'advisor-body';
      this._body = body;
      panel.appendChild(body);

      // Footer
      const ftr = document.createElement('div');
      ftr.className = 'advisor-footer';
      const cta = document.createElement('button');
      cta.className = 'advisor-footer-cta';
      cta.innerHTML = `${I.chat} Speak with customer service`;
      cta.addEventListener('click', () => this._handoff());
      const note = document.createElement('span');
      note.className = 'advisor-footer-note';
      note.textContent = 'General product info · not medical advice';
      ftr.append(cta, note);
      panel.appendChild(ftr);
      root.appendChild(panel);

      this._home();
    }

    _home() {
      this._clear();
      this.turns = [];
      this._threadMode = false;
      this._thread = null;

      const greet = document.createElement('div');
      greet.className = 'advisor-greeting';
      greet.innerHTML = `
        <div class="advisor-greeting-hi">How can we help?</div>
        <div class="advisor-greeting-sub">Get expert guidance on lenses and frames, plus tailored picks from our collection.</div>
      `;
      this._body.appendChild(greet);

      const lbl = document.createElement('div');
      lbl.className = 'advisor-label';
      lbl.textContent = 'Popular topics';
      this._body.appendChild(lbl);

      const topics = document.createElement('div');
      topics.className = 'advisor-topics';
      TOPICS.forEach(t => {
        const row = document.createElement('button');
        row.className = 'advisor-topic';
        row.innerHTML = `<span class="advisor-topic-ico">${TOPIC_ICONS[t.ico]}</span><span>${t.label}</span><span class="advisor-topic-arrow">${I.arrow}</span>`;
        row.addEventListener('click', () => this._ask(t.q));
        topics.appendChild(row);
      });
      this._body.appendChild(topics);

      const div = document.createElement('div');
      div.className = 'advisor-divider';
      div.textContent = 'or ask anything';
      this._body.appendChild(div);

      this._buildInput();
    }

    _buildInput() {
      const input = document.createElement('div');
      input.className = 'advisor-input';

      const ta = document.createElement('textarea');
      ta.className = 'advisor-textarea';
      ta.placeholder = 'Ask a question…';
      ta.rows = 2; ta.maxLength = 500;
      ta.setAttribute('aria-label', 'Your question');

      const foot = document.createElement('div');
      foot.className = 'advisor-input-foot';
      const cc = document.createElement('span');
      cc.className = 'advisor-char';
      const send = document.createElement('button');
      send.className = 'advisor-send';
      send.innerHTML = `${I.send} Ask`;

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
      send.addEventListener('click', () => { if (ta.value.trim()) this._ask(ta.value.trim()); });

      const rxBtn = document.createElement('button');
      rxBtn.className = 'advisor-rx-btn';
      rxBtn.type = 'button';
      rxBtn.title = 'Read my prescription';
      rxBtn.setAttribute('aria-label', 'Read my prescription');
      rxBtn.innerHTML = I.scan;
      rxBtn.addEventListener('click', () => this._startRxUpload());

      const btnGroup = document.createElement('div');
      btnGroup.className = 'advisor-input-btns';
      btnGroup.append(rxBtn, send);
      foot.append(cc, btnGroup);
      input.append(ta, foot);
      this._body.appendChild(input);
    }

    _loading() {
      this._clear();
      const w = document.createElement('div');
      w.className = 'advisor-loading';
      w.setAttribute('aria-live', 'polite');
      const d = document.createElement('div');
      d.className = 'advisor-dots';
      d.setAttribute('role', 'status');
      d.setAttribute('aria-label', 'Loading');
      [0,1,2].forEach(() => d.appendChild(document.createElement('span')));
      const t = document.createElement('p');
      t.textContent = 'Finding the best guidance…';
      w.append(d, t);
      this._body.appendChild(w);
    }

    _result(data, q) {
      this._clear();
      this._lastQ = q; this._lastType = data.answer_type;

      const back = document.createElement('button');
      back.className = 'advisor-back';
      back.innerHTML = `${I.back} Back`;
      back.addEventListener('click', () => this._home());
      this._body.appendChild(back);

      if (data.short_answer) {
        const a = document.createElement('p');
        a.className = 'advisor-answer';
        a.textContent = data.short_answer;
        this._body.appendChild(a);
      }

      if (data.educational_points?.length) {
        const ul = document.createElement('ul');
        ul.className = 'advisor-bullets';
        data.educational_points.forEach(pt => {
          const li = document.createElement('li');
          li.innerHTML = I.check;
          const s = document.createElement('span');
          s.textContent = pt;
          li.appendChild(s);
          ul.appendChild(li);
        });
        this._body.appendChild(ul);
      }

      if (data.recommended_products?.length) {
        const lbl = document.createElement('div');
        lbl.className = 'advisor-products-label';
        lbl.textContent = 'Recommended for you';
        this._body.appendChild(lbl);
        const list = document.createElement('div');
        list.className = 'advisor-products';
        data.recommended_products.forEach(p => {
          list.appendChild(this._card(p));
          this._track('advisor_product_shown', { product_id: p.product_id });
        });
        this._body.appendChild(list);
      }

      if (data.support_handoff?.needed)
        this._body.appendChild(this._handoffCard(data.support_handoff.message));

      if (data.disclaimer) {
        const d = document.createElement('p');
        d.className = 'advisor-disclaimer';
        d.textContent = data.disclaimer;
        this._body.appendChild(d);
      }

      this._track('advisor_response_received', { answer_type: data.answer_type });
    }

    _error() {
      this._clear();
      const back = document.createElement('button');
      back.className = 'advisor-back';
      back.innerHTML = `${I.back} Back`;
      back.addEventListener('click', () => this._home());
      this._body.appendChild(back);
      const e = document.createElement('div');
      e.className = 'advisor-error';
      e.setAttribute('role', 'alert');
      e.textContent = 'Something went wrong. Please try again or start a live chat.';
      this._body.appendChild(e);
      this._body.appendChild(this._handoffCard(null));
    }

    _card(p) {
      const a = document.createElement('a');
      a.className = 'advisor-card';
      a.href = p.public_url; a.target = '_blank'; a.rel = 'noopener noreferrer';
      a.setAttribute('aria-label', `View ${p.title} — ${p.price}`);
      a.addEventListener('click', () => this._track('advisor_product_clicked', { product_id: p.product_id }));

      const img = document.createElement('div');
      img.className = 'advisor-card-img';
      if (p.image_url) {
        const im = document.createElement('img');
        im.alt = p.title; im.loading = 'lazy'; im.src = p.image_url;
        im.onerror = function () { this.parentNode.innerHTML = `<span class="advisor-card-img-ph" aria-hidden="true">🕶️</span>`; };
        img.appendChild(im);
      } else img.innerHTML = `<span class="advisor-card-img-ph" aria-hidden="true">🕶️</span>`;
      a.appendChild(img);

      const b = document.createElement('div');
      b.className = 'advisor-card-body';
      const n = document.createElement('div');
      n.className = 'advisor-card-name'; n.textContent = p.title;
      b.appendChild(n);
      if (p.reason) {
        const w = document.createElement('div');
        w.className = 'advisor-card-why'; w.textContent = p.reason;
        b.appendChild(w);
      }
      const f = document.createElement('div');
      f.className = 'advisor-card-foot';
      const pr = document.createElement('span');
      pr.className = 'advisor-card-price'; pr.textContent = p.price;
      const v = document.createElement('span');
      v.className = 'advisor-card-view'; v.textContent = 'View →';
      f.append(pr, v);
      b.appendChild(f);
      a.appendChild(b);
      return a;
    }

    _handoffCard(msg) {
      const wrap = document.createElement('div');
      wrap.className = 'advisor-handoff';
      const top = document.createElement('div');
      top.className = 'advisor-handoff-top';
      top.innerHTML = `
        <div class="advisor-handoff-ico">${I.chat}</div>
        <div>
          <div class="advisor-handoff-title">Need account or order help?</div>
          <div class="advisor-handoff-desc">${msg || 'Our New York team handles orders, prescriptions, refunds, and account questions.'}</div>
        </div>`;
      wrap.appendChild(top);
      const btn = document.createElement('button');
      btn.className = 'advisor-handoff-btn';
      btn.innerHTML = `${I.chat} Start live chat`;
      btn.addEventListener('click', () => this._handoff());
      wrap.appendChild(btn);
      return wrap;
    }

    _handoff() {
      this._track('advisor_zendesk_handoff');
      openZendesk(this.support, this._lastQ ? prefillMsg(this._lastQ, this._lastType) : null);
    }

    _enterThreadMode() {
      this._threadMode = true;
      this._clear();
      // Scrolling thread container
      const thread = document.createElement('div');
      thread.className = 'advisor-thread';
      this._thread = thread;
      this._body.appendChild(thread);
      // Persistent input pinned below the thread
      this._buildInput();
      // A subtle "start over" control in the header area of the thread
      const startOver = document.createElement('button');
      startOver.className = 'advisor-startover';
      startOver.type = 'button';
      startOver.innerHTML = `${I.back} Start over`;
      startOver.addEventListener('click', () => this._home());
      thread.appendChild(startOver);
    }

    _appendQuestion(q) {
      const row = document.createElement('div');
      row.className = 'advisor-q';
      const bubble = document.createElement('div');
      bubble.className = 'advisor-q-bubble';
      bubble.textContent = q;
      row.appendChild(bubble);
      this._thread.appendChild(row);
    }

    _appendLoading() {
      const w = document.createElement('div');
      w.className = 'advisor-a advisor-loading';
      w.setAttribute('aria-live', 'polite');
      const d = document.createElement('div');
      d.className = 'advisor-dots';
      d.setAttribute('role', 'status');
      d.setAttribute('aria-label', 'Loading');
      [0,1,2].forEach(() => d.appendChild(document.createElement('span')));
      w.appendChild(d);
      this._thread.appendChild(w);
      return w;
    }

    _appendAnswer(data) {
      const block = document.createElement('div');
      block.className = 'advisor-a';

      if (data.short_answer) {
        const a = document.createElement('p');
        a.className = 'advisor-answer';
        a.textContent = data.short_answer;
        block.appendChild(a);
      }

      if (data.educational_points?.length) {
        const ul = document.createElement('ul');
        ul.className = 'advisor-bullets';
        data.educational_points.forEach(pt => {
          const li = document.createElement('li');
          li.innerHTML = I.check;
          const sp = document.createElement('span');
          sp.textContent = pt;
          li.appendChild(sp);
          ul.appendChild(li);
        });
        block.appendChild(ul);
      }

      if (data.recommended_products?.length) {
        const lbl = document.createElement('div');
        lbl.className = 'advisor-products-label';
        lbl.textContent = 'Recommended for you';
        block.appendChild(lbl);
        const list = document.createElement('div');
        list.className = 'advisor-products';
        data.recommended_products.forEach(pp => {
          list.appendChild(this._card(pp));
          this._track('advisor_product_shown', { product_id: pp.product_id });
        });
        block.appendChild(list);
      }

      if (data.support_handoff?.needed)
        block.appendChild(this._handoffCard(data.support_handoff.message));

      if (data.disclaimer) {
        const d = document.createElement('p');
        d.className = 'advisor-disclaimer';
        d.textContent = data.disclaimer;
        block.appendChild(d);
      }

      this._thread.appendChild(block);
    }

    _appendError() {
      const block = document.createElement('div');
      block.className = 'advisor-a';
      const e = document.createElement('div');
      e.className = 'advisor-error';
      e.setAttribute('role', 'alert');
      e.textContent = 'Something went wrong. Please try again or start a live chat.';
      block.appendChild(e);
      block.appendChild(this._handoffCard(null));
      this._thread.appendChild(block);
    }

    _scrollThread() {
      if (this._thread) this._thread.scrollTop = this._thread.scrollHeight;
    }

    _setInputEnabled(on) {
      const ta = this._root.querySelector('.advisor-textarea');
      const send = this._root.querySelector('.advisor-send');
      if (ta) ta.disabled = !on;
      if (send) send.disabled = !on;
      if (on && ta) { ta.value = ''; ta.focus(); }
    }

    _startRxUpload() {
      this._track('advisor_rx_upload_started');
      let inp = this._rxInput;
      if (!inp) {
        inp = document.createElement('input');
        inp.type = 'file';
        inp.accept = 'image/jpeg,image/png,image/webp,application/pdf';
        inp.style.display = 'none';
        inp.addEventListener('change', () => {
          const f = inp.files && inp.files[0];
          if (f) this._readRxFile(f);
          inp.value = '';
        });
        this._root.appendChild(inp);
        this._rxInput = inp;
      }
      inp.click();
    }

    _readRxFile(file) {
      const okTypes = ['image/jpeg','image/png','image/webp','application/pdf'];
      if (!okTypes.includes(file.type)) {
        if (!this._threadMode) this._enterThreadMode();
        this._appendRxError('That file type is not supported. Please upload a JPG, PNG, or PDF.');
        return;
      }
      if (file.size > 10 * 1024 * 1024) {
        if (!this._threadMode) this._enterThreadMode();
        this._appendRxError('That file is too large. Please upload an image under 10MB.');
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const b64 = String(reader.result).split(',')[1] || '';
        this._uploadRx(b64, file.type);
      };
      reader.onerror = () => {
        if (!this._threadMode) this._enterThreadMode();
        this._appendRxError('Could not read that file. Please try again.');
      };
      reader.readAsDataURL(file);
    }

    async _uploadRx(b64, mime) {
      if (!this._threadMode) this._enterThreadMode();
      this._appendQuestion('Reading my prescription…');
      const loadingEl = this._appendLoading();
      this._scrollThread();
      try {
        const res = await fetch(this.rxApi, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ image: b64, mime, brand: this.brand, session_id: this.sid }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        loadingEl.remove();
        this._showRxConfirm(data.prescription || {}, data.disclaimer || '');
        this._track('advisor_rx_read_ok', { confidence: (data.prescription || {}).confidence });
      } catch (e) {
        console.error('[Advisor] Rx read', e);
        loadingEl.remove();
        this._appendRxError('We could not read that prescription. Please try a clearer photo, or type your values.');
        this._track('advisor_rx_read_error', { error: e.message });
      } finally {
        this._scrollThread();
      }
    }

    _appendRxError(msg) {
      const block = document.createElement('div');
      block.className = 'advisor-a';
      const e = document.createElement('div');
      e.className = 'advisor-error';
      e.setAttribute('role', 'alert');
      e.textContent = msg;
      block.appendChild(e);
      this._thread.appendChild(block);
      this._scrollThread();
    }

    _showRxConfirm(rx, disclaimer) {
      const isContacts = rx.type === 'contacts';
      const block = document.createElement('div');
      block.className = 'advisor-a advisor-rx-card';

      const title = document.createElement('div');
      title.className = 'advisor-rx-title';
      title.textContent = "Here's what I read — please check and correct anything:";
      block.appendChild(title);

      // Build an editable grid. Columns depend on type.
      const cols = isContacts
        ? [['sph','SPH'], ['bc','BC'], ['dia','DIA'], ['cyl','CYL'], ['axis','AXIS']]
        : [['sph','SPH'], ['cyl','CYL'], ['axis','AXIS'], ['add','ADD']];

      const table = document.createElement('div');
      table.className = 'advisor-rx-grid';
      table.style.gridTemplateColumns = `64px repeat(${cols.length}, 1fr)`;

      // header row
      table.appendChild(this._rxCell('', 'hdr'));
      cols.forEach(([, label]) => table.appendChild(this._rxCell(label, 'hdr')));

      // OD / OS rows
      ['od','os'].forEach(eye => {
        table.appendChild(this._rxCell(eye.toUpperCase(), 'rowlbl'));
        cols.forEach(([key]) => {
          const cell = document.createElement('div');
          const input = document.createElement('input');
          input.type = 'text';
          input.className = 'advisor-rx-input';
          input.dataset.eye = eye;
          input.dataset.key = key;
          input.value = (rx[eye] && rx[eye][key]) ? rx[eye][key] : '';
          cell.appendChild(input);
          table.appendChild(cell);
        });
      });
      block.appendChild(table);

      // PD field
      const pdWrap = document.createElement('div');
      pdWrap.className = 'advisor-rx-pd';
      const pdLbl = document.createElement('span');
      pdLbl.textContent = 'PD';
      const pdInput = document.createElement('input');
      pdInput.type = 'text';
      pdInput.className = 'advisor-rx-input advisor-rx-pd-input';
      pdInput.dataset.key = 'pd';
      pdInput.value = rx.pd || '';
      pdWrap.append(pdLbl, pdInput);
      block.appendChild(pdWrap);

      this._rxType = rx.type || 'glasses';

      if (disclaimer) {
        const dis = document.createElement('p');
        dis.className = 'advisor-disclaimer';
        dis.textContent = disclaimer;
        block.appendChild(dis);
      }

      const actions = document.createElement('div');
      actions.className = 'advisor-rx-actions';
      const confirm = document.createElement('button');
      confirm.className = 'advisor-send advisor-rx-confirm';
      confirm.textContent = 'Confirm & get recommendations';
      confirm.addEventListener('click', () => this._confirmRx(block));
      const redo = document.createElement('button');
      redo.className = 'advisor-rx-redo';
      redo.textContent = 'Re-upload';
      redo.addEventListener('click', () => this._startRxUpload());
      actions.append(confirm, redo);
      block.appendChild(actions);

      this._thread.appendChild(block);
      this._scrollThread();
    }

    _rxCell(text, kind) {
      const c = document.createElement('div');
      c.className = 'advisor-rx-' + (kind || 'cell');
      c.textContent = text;
      return c;
    }

    _confirmRx(block) {
      const vals = { od: {}, os: {}, pd: '' };
      block.querySelectorAll('.advisor-rx-input').forEach(inp => {
        const v = inp.value.trim();
        if (inp.dataset.key === 'pd') { vals.pd = v; return; }
        vals[inp.dataset.eye][inp.dataset.key] = v;
      });

      const fmtEye = (e) => Object.entries(e)
        .filter(([, v]) => v !== '')
        .map(([k, v]) => `${k.toUpperCase()} ${v}`).join(', ');

      const odStr = fmtEye(vals.od);
      const osStr = fmtEye(vals.os);
      const pdStr = vals.pd ? `, PD ${vals.pd}` : '';
      const typeStr = this._rxType === 'contacts' ? 'contact lens' : 'eyeglass';

      const q = `Here is my ${typeStr} prescription. Right eye (OD): ${odStr || 'n/a'}. `
        + `Left eye (OS): ${osStr || 'n/a'}${pdStr}. `
        + `Based on this, what lens options do you recommend for me?`;

      this._track('advisor_rx_confirmed');
      this._ask(q);
    }

    async _ask(q) {
      if (!q?.trim()) return;
      q = q.trim();
      if (q === '__READ_RX__') { this._startRxUpload(); return; }
      this._track('advisor_question_asked', { question_length: q.length });

      // First question transitions from topics screen into thread mode.
      if (!this._threadMode) this._enterThreadMode();

      // Show the user's question in the thread immediately.
      this._appendQuestion(q);
      const loadingEl = this._appendLoading();
      this._scrollThread();

      // Disable input while awaiting the answer.
      this._setInputEnabled(false);

      try {
        const res = await fetch(this.api, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            question: q,
            page_context: this.ctx,
            session_id: this.sid,
            brand: this.brand,
            site: '39dollarglasses',
            history: this.turns.slice(-6),
          }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        loadingEl.remove();
        this._appendAnswer(data);
        this.turns.push({ question: q, answer: data.short_answer || '' });
        this._track('advisor_response_received', { answer_type: data.answer_type });
      } catch (e) {
        console.error('[Advisor]', e);
        loadingEl.remove();
        this._appendError();
        this._track('advisor_error', { error: e.message });
      } finally {
        this._setInputEnabled(true);
        this._scrollThread();
      }
    }

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

  function init() {
    if (document.getElementById('advisor-widget')) return;
    window._advisorWidget = new AdvisorWidget(window.advisorConfig || {});
    window.__sloanOpen = function () { try { window._advisorWidget && window._advisorWidget._open(); } catch (e) {} };
  }
  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
  window.AdvisorWidget = AdvisorWidget;
})();
