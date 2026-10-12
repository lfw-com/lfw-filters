/* LFW Filters: progressive enhancement. Without this file every control is a
   plain GET form and every chip a plain link. With it, changes fetch the same
   URL, swap the result regions in place, push history and announce the count.
   No dependencies, no build step.

   Static copies: when the page is served as plain files (an exported demo), no
   server filters anything. Then this file filters the already-rendered items
   in place, using the term slugs the results block printed on each item
   (data-lfwf-terms) and the vocabulary of the form itself: a value counts only
   if a control of the form offers it, which is the same allowlist the server
   applies. Static mode starts when the results block carries
   data-lfw-filters-static (set by the exporter), or when a fetch for a URL
   with known filters comes back with no active chips, which means the host
   ignored the query string. On WordPress nothing changes: the server still
   filters, and the attributes are inert. */
(function () {
  'use strict';

  const form = document.querySelector('[data-lfwf-form]');
  if (!form || !window.fetch || !window.DOMParser || !window.history || !window.history.pushState) {
    return;
  }

  const KEYWORD = 'q';
  const keyword = form.querySelector('input[name="' + KEYWORD + '"]');
  const submit = form.querySelector('[data-lfwf-submit]');
  const panel = form.querySelector('[data-lfwf-panel]');
  const drops = Array.prototype.slice.call(form.querySelectorAll('details.lfwf-drop'));
  let controller = null;
  let typingTimer = null;
  let announceTimer = null;
  let staticMode = !!document.querySelector('#lfwf-results[data-lfw-filters-static]');

  function base() {
    const u = new URL(form.action, location.href);
    return u;
  }

  /* The URL a plain submit would produce, minus empty values. */
  function urlFromForm() {
    const u = base();
    const params = new URLSearchParams(u.search);
    const data = new FormData(form);
    data.forEach(function (value, name) {
      const v = String(value).trim();
      if (v !== '' && !params.has(name)) {
        params.append(name, v);
      } else if (v !== '' && params.getAll(name).indexOf(v) === -1) {
        params.append(name, v);
      }
    });
    u.search = params.toString();
    return u.toString();
  }

  /* Make the controls match a URL (back and forward). The keyword is the
     visitor's own text from the address bar; the server never prints it. */
  function syncControls(url) {
    const params = new URL(url, location.href).searchParams;
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'hidden' || el.type === 'submit') {
        return;
      }
      let values = [];
      params.getAll(el.name).forEach(function (v) { values = values.concat(v.split(',')); });
      if (el.type === 'checkbox') {
        el.checked = values.indexOf(el.value) !== -1;
      } else if (el.tagName === 'SELECT') {
        el.value = values.length && el.querySelector('option[value="' + CSS.escape(values[0]) + '"]') ? values[0] : '';
      } else if (el === keyword) {
        el.value = (params.get(KEYWORD) || '').slice(0, 80);
      }
    });
  }

  function announce(liveEl, text) {
    clearTimeout(announceTimer);
    /* Replace the text inside the existing live region so it is announced. */
    announceTimer = setTimeout(function () {
      if (liveEl.textContent !== text) {
        liveEl.textContent = text;
      } else {
        liveEl.textContent = '';
        setTimeout(function () { liveEl.textContent = text; }, 60);
      }
    }, 150);
  }

  function apply(doc) {
    /* Whole regions (results, chips) by id. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-lfwf-region][id]'), function (el) {
      const next = doc.getElementById(el.id);
      if (!next) {
        return;
      }
      const clone = document.importNode(next, true);
      el.replaceWith(clone);
    });
    /* Option counts and selected badges. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-lfwf-sync]'), function (el) {
      const next = doc.querySelector('[data-lfwf-sync="' + el.getAttribute('data-lfwf-sync') + '"]');
      el.innerHTML = next ? next.innerHTML : '';
    });
    /* Select option labels carry counts; keep the visitor's selection. */
    Array.prototype.forEach.call(document.querySelectorAll('select[data-lfwf-sync-options]'), function (sel) {
      const next = doc.querySelector('select[data-lfwf-sync-options="' + sel.getAttribute('data-lfwf-sync-options') + '"]');
      if (!next) {
        return;
      }
      Array.prototype.forEach.call(sel.options, function (opt) {
        const match = next.querySelector('option[value="' + CSS.escape(opt.value) + '"]');
        if (match) {
          opt.textContent = match.textContent;
        }
      });
    });
    /* The count: text only, inside the live region. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-lfwf-live][id]'), function (el) {
      const next = doc.getElementById(el.id);
      if (next) {
        announce(el, next.textContent);
      }
    });
    bindChips();
  }

  function setBusy(on) {
    const results = document.getElementById('lfwf-results');
    if (results) {
      if (on) {
        results.setAttribute('aria-busy', 'true');
      } else {
        results.removeAttribute('aria-busy');
      }
    }
  }

  /* ---------- Static copies: filter the rendered items in place ---------- */

  /* "%1$s of %2$s" style templates (the server's own sentences). */
  function tpl(template, args) {
    let i = 0;
    return String(template || '').replace(/%(?:(\d+)\$)?s/g, function (all, n) {
      const idx = n ? parseInt(n, 10) - 1 : i++;
      return args[idx] === undefined ? '' : String(args[idx]);
    });
  }

  function fmt(n) {
    try { return n.toLocaleString(document.documentElement.lang || undefined); } catch (e) { return String(n); }
  }

  /* The vocabulary of the form: taxonomy -> slug -> visible name. */
  function vocabulary() {
    const v = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.name === KEYWORD || el.type === 'hidden' || el.type === 'submit') {
        return;
      }
      v[el.name] = v[el.name] || {};
      if (el.type === 'checkbox') {
        const label = el.closest('label');
        const opt = label && label.querySelector('.lfwf-opt');
        v[el.name][el.value] = opt ? opt.textContent.trim() : (label ? label.textContent.trim() : el.value);
      } else if (el.tagName === 'SELECT') {
        Array.prototype.forEach.call(el.options, function (o) {
          if (o.value !== '') {
            v[el.name][o.value] = o.getAttribute('data-lfwf-name') || o.textContent.replace(/\s*\(\d[\d,.]*\)\s*$/, '').trim();
          }
        });
      }
    });
    return v;
  }

  /* The validated state a URL means: only values the form offers, plus the
     keyword (lowercased, capped like the server caps it). */
  function stateFromUrl(url) {
    const params = new URL(url, location.href).searchParams;
    const vocab = vocabulary();
    const tax = {};
    let q = '';
    params.forEach(function (value, name) {
      if (name === KEYWORD) {
        q = value.trim().slice(0, 80).toLowerCase().split(/\s+/).filter(Boolean).slice(0, 8).join(' ');
        return;
      }
      if (!vocab[name]) {
        return;
      }
      value.split(',').forEach(function (s) {
        s = s.trim();
        if (s && vocab[name][s] !== undefined) {
          tax[name] = tax[name] || [];
          if (tax[name].indexOf(s) === -1) {
            tax[name].push(s);
          }
        }
      });
    });
    return { tax: tax, q: q, vocab: vocab, active: Object.keys(tax).length > 0 || q !== '' };
  }

  function itemTerms(li) {
    if (!li.lfwfTerms) {
      li.lfwfTerms = (li.getAttribute('data-lfwf-terms') || '').split(/\s+/).filter(Boolean);
    }
    return li.lfwfTerms;
  }

  function itemText(li) {
    if (li.lfwfText === undefined) {
      li.lfwfText = (li.textContent || '').replace(/\s+/g, ' ').toLowerCase();
    }
    return li.lfwfText;
  }

  /* Does an item match the state? Values in one taxonomy are OR, taxonomies
     are AND, every keyword word must appear in the item's text. */
  function itemMatches(li, state, exceptTax, extra) {
    const terms = itemTerms(li);
    const taxes = Object.keys(state.tax);
    for (let i = 0; i < taxes.length; i++) {
      if (taxes[i] === exceptTax) {
        continue;
      }
      const wanted = state.tax[taxes[i]];
      let hit = false;
      for (let j = 0; j < wanted.length; j++) {
        if (terms.indexOf(taxes[i] + ':' + wanted[j]) !== -1) {
          hit = true;
          break;
        }
      }
      if (!hit) {
        return false;
      }
    }
    if (extra && terms.indexOf(extra) === -1) {
      return false;
    }
    if (state.q) {
      const text = itemText(li);
      const words = state.q.split(' ');
      for (let k = 0; k < words.length; k++) {
        if (text.indexOf(words[k]) === -1) {
          return false;
        }
      }
    }
    return true;
  }

  /* A link to the state without one value (or without the keyword), built
     like the server builds chip links: taxonomy values only, never the keyword. */
  function linkWithout(state, tax, slug) {
    const active = document.getElementById('lfwf-active');
    const b = (active && active.getAttribute('data-lfwf-base')) || base().toString();
    const parts = [];
    Object.keys(state.tax).forEach(function (name) {
      state.tax[name].forEach(function (s) {
        if (name === tax && (slug === null || s === slug)) {
          return;
        }
        parts.push(encodeURIComponent(name) + '=' + encodeURIComponent(s));
      });
    });
    return parts.length ? b + (b.indexOf('?') === -1 ? '?' : '&') + parts.join('&') : b;
  }

  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text !== undefined) { e.textContent = text; }
    return e;
  }

  function renderChips(state) {
    const active = document.getElementById('lfwf-active');
    if (!active) {
      return;
    }
    const chips = [];
    Object.keys(state.tax).forEach(function (tax) {
      state.tax[tax].forEach(function (slug) {
        chips.push({ key: tax + ':' + slug, name: state.vocab[tax][slug], href: linkWithout(state, tax, slug) });
      });
    });
    if (state.q) {
      chips.push({ key: KEYWORD, name: active.getAttribute('data-lfwf-keyword-label') || 'Keyword search', href: linkWithout(state, null, null) });
    }
    active.textContent = '';
    if (!chips.length) {
      active.hidden = true;
      return;
    }
    const ul = el('ul', 'lfwf-chips');
    ul.setAttribute('role', 'list');
    ul.setAttribute('aria-label', active.getAttribute('data-lfwf-list-label') || '');
    chips.forEach(function (c) {
      const li = el('li');
      const a = el('a', 'lfwf-chip');
      a.href = c.href;
      a.setAttribute('data-lfwf-remove', c.key);
      a.appendChild(el('span', 'lfwf-sr', (active.getAttribute('data-lfwf-remove-label') || '') + ' '));
      a.appendChild(el('span', '', c.name));
      const x = el('span', 'lfwf-x');
      x.setAttribute('aria-hidden', 'true');
      a.appendChild(x);
      li.appendChild(a);
      ul.appendChild(li);
    });
    const clear = el('a', 'lfwf-clear', active.getAttribute('data-lfwf-clear-label') || '');
    clear.href = active.getAttribute('data-lfwf-base') || base().toString();
    clear.setAttribute('data-lfwf-clear', '');
    active.appendChild(ul);
    active.appendChild(clear);
    active.hidden = false;
  }

  /* Per-option counts and the "n selected" badges, from the items. */
  function syncOptions(state, items) {
    const results = document.getElementById('lfwf-results');
    const srOne = results ? results.getAttribute('data-lfwf-sr-one') : '(%s result)';
    const srMany = results ? results.getAttribute('data-lfwf-sr-many') : '(%s results)';
    const countFor = function (tax, slug) {
      let n = 0;
      items.forEach(function (li) { if (itemMatches(li, state, tax, tax + ':' + slug)) { n++; } });
      return n;
    };
    Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"][name]'), function (box) {
      const target = form.querySelector('[data-lfwf-sync="n-' + CSS.escape(box.name + '-' + box.value) + '"]');
      if (!target) {
        return;
      }
      const n = countFor(box.name, box.value);
      target.textContent = '';
      const vis = el('span', '', fmt(n));
      vis.setAttribute('aria-hidden', 'true');
      target.appendChild(vis);
      target.appendChild(el('span', 'lfwf-sr', tpl(n === 1 ? srOne : srMany, [fmt(n)])));
    });
    Array.prototype.forEach.call(form.querySelectorAll('select[data-lfwf-sync-options]'), function (sel) {
      Array.prototype.forEach.call(sel.options, function (o) {
        if (o.value === '' || !/\(\d[\d,.]*\)\s*$/.test(o.textContent)) {
          return;
        }
        o.textContent = (o.getAttribute('data-lfwf-name') || o.textContent.replace(/\s*\(\d[\d,.]*\)\s*$/, '')) + ' (' + fmt(countFor(sel.name, o.value)) + ')';
      });
    });
    const selectedLabel = form.getAttribute('data-lfwf-selected-label') || 'selected';
    Array.prototype.forEach.call(form.querySelectorAll('[data-lfwf-sync^="sel-"]'), function (badge) {
      const tax = badge.getAttribute('data-lfwf-sync').slice(4);
      const n = state.tax[tax] ? state.tax[tax].length : 0;
      badge.textContent = '';
      if (n) {
        badge.appendChild(document.createTextNode(fmt(n)));
        badge.appendChild(el('span', 'lfwf-sr', ' ' + selectedLabel));
      }
    });
  }

  function applyStatic(url) {
    const results = document.getElementById('lfwf-results');
    if (!results) {
      return;
    }
    const state = stateFromUrl(url);
    const items = Array.prototype.slice.call(results.querySelectorAll('[data-lfwf-item]'));
    let shown = 0;
    items.forEach(function (li) {
      const ok = !state.active || itemMatches(li, state);
      li.hidden = !ok;
      li.classList.toggle('lfwf-hidden', !ok);
      if (ok) { shown++; }
    });
    /* Groups (results grouped by a taxonomy): recount, hide empty ones. */
    Array.prototype.forEach.call(results.querySelectorAll('.lfwf-group'), function (g) {
      const n = g.querySelectorAll('[data-lfwf-item]:not([hidden])').length;
      g.hidden = n === 0;
      g.classList.toggle('lfwf-hidden', n === 0);
      const num = g.querySelector('.lfwf-group-n [aria-hidden]');
      if (num) { num.textContent = fmt(n); }
      const sr = g.querySelector('.lfwf-group-n .lfwf-sr');
      if (sr) { sr.textContent = tpl(n === 1 ? results.getAttribute('data-lfwf-sr-one') : results.getAttribute('data-lfwf-sr-many'), [fmt(n)]); }
    });
    /* Empty state, the server's own words. */
    let empty = results.querySelector('.lfwf-empty');
    if (shown === 0) {
      if (!empty) {
        empty = el('div', 'lfwf-empty');
        empty.appendChild(el('p', 'lfwf-empty-heading', results.getAttribute('data-lfwf-empty-heading') || ''));
        const line = results.getAttribute('data-lfwf-empty-text');
        if (line) { empty.appendChild(el('p', '', line)); }
        results.appendChild(empty);
      }
    } else if (empty) {
      empty.remove();
    }
    /* The count, announced in its live region. */
    const count = document.getElementById('lfwf-count');
    if (count) {
      const total = parseInt(count.getAttribute('data-lfwf-total'), 10) || items.length;
      const s = count.getAttribute('data-lfwf-singular') || '';
      const p = count.getAttribute('data-lfwf-plural') || '';
      let text;
      if (!state.active) {
        text = tpl(count.getAttribute('data-lfwf-text-all'), [fmt(total), total === 1 ? s : p]);
      } else if (shown === 0) {
        text = tpl(count.getAttribute('data-lfwf-text-none'), [p]);
      } else {
        text = tpl(count.getAttribute('data-lfwf-text-some'), [fmt(shown), fmt(total), total === 1 ? s : p]);
      }
      announce(count, text);
    }
    renderChips(state);
    syncOptions(state, items);
    bindChips();
  }

  /* A fetched page that ignored known filters means there is no server
     behind this copy: switch to static mode for the rest of the visit. */
  function hostIgnoredFilters(url, doc) {
    const state = stateFromUrl(url);
    if (!state.active) {
      return false;
    }
    const active = doc.getElementById('lfwf-active');
    return !!active && active.hidden && !active.querySelector('a.lfwf-chip');
  }

  /* ---------- Fetch a filtered view and swap it in. Any failure loads the page. */
  function go(url, opts) {
    opts = opts || {};
    if (controller) {
      controller.abort();
    }
    if (staticMode) {
      if (opts.push) {
        history.pushState({ lfwf: true }, '', url);
      }
      applyStatic(url);
      if (opts.after) {
        opts.after();
      }
      return Promise.resolve();
    }
    controller = typeof AbortController === 'function' ? new AbortController() : null;
    setBusy(true);
    return fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'text/html' },
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.text();
    }).then(function (html) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      if (opts.push) {
        history.pushState({ lfwf: true }, '', url);
      }
      if (hostIgnoredFilters(url, doc)) {
        staticMode = true;
        applyStatic(url);
      } else {
        apply(doc);
      }
      setBusy(false);
      if (opts.after) {
        opts.after();
      }
    }).catch(function (err) {
      if (err && err.name === 'AbortError') {
        return;
      }
      location.href = url;
    });
  }

  function update() {
    const url = urlFromForm();
    if (url === location.href) {
      return;
    }
    go(url, { push: true });
  }

  /* Chips and Clear filters: links that work without this file. */
  function bindChips() {
    const active = document.getElementById('lfwf-active');
    if (!active) {
      return;
    }
    const chips = Array.prototype.slice.call(active.querySelectorAll('a.lfwf-chip'));
    chips.forEach(function (chip, index) {
      if (chip.lfwfBound) {
        return;
      }
      chip.lfwfBound = true;
      chip.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
          return;
        }
        e.preventDefault();
        const key = chip.getAttribute('data-lfwf-remove') || '';
        if (key === KEYWORD) {
          if (keyword) { keyword.value = ''; }
        } else {
          const parts = key.split(':');
          Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name !== parts[0]) { return; }
            if (el.type === 'checkbox' && el.value === parts[1]) { el.checked = false; }
            if (el.tagName === 'SELECT' && el.value === parts[1]) { el.value = ''; }
          });
        }
        go(urlFromForm(), { push: true, after: function () { focusAfterRemove(index); } });
      });
    });
    const clear = active.querySelector('[data-lfwf-clear]');
    if (clear && !clear.lfwfBound) {
      clear.lfwfBound = true;
      clear.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
          return;
        }
        e.preventDefault();
        form.reset();
        Array.prototype.forEach.call(form.querySelectorAll('input[type=checkbox]'), function (b) { b.checked = false; });
        Array.prototype.forEach.call(form.querySelectorAll('select'), function (s) { s.value = ''; });
        if (keyword) { keyword.value = ''; }
        go(urlFromForm(), {
          push: true,
          after: function () {
            const first = keyword || form.querySelector('summary, input, select');
            if (first) { first.focus(); }
          }
        });
      });
    }
  }

  function focusAfterRemove(index) {
    const active = document.getElementById('lfwf-active');
    const chips = active ? active.querySelectorAll('a.lfwf-chip') : [];
    const target = chips[Math.min(index, chips.length - 1)] ||
      (active && !active.hidden && active.querySelector('[data-lfwf-clear]')) ||
      document.getElementById('lfwf-count') ||
      keyword;
    if (target) { target.focus(); }
  }

  /* ---------- Wiring ---------- */
  if (submit) {
    submit.hidden = true;
  }
  syncControls(location.href);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(typingTimer);
    update();
  });
  form.addEventListener('change', function (e) {
    if (e.target === keyword) {
      return;
    }
    update();
  });
  if (keyword) {
    keyword.addEventListener('input', function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(update, 400);
    });
  }

  window.addEventListener('popstate', function () {
    syncControls(location.href);
    go(location.href, { push: false });
  });
  history.replaceState({ lfwf: true }, '', location.href);

  /* Band menus: one open at a time, Escape closes, a click outside closes. */
  drops.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) {
        drops.forEach(function (o) { if (o !== d) { o.open = false; } });
      }
    });
    d.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && d.open) {
        d.open = false;
        d.querySelector('summary').focus();
      }
    });
  });
  document.addEventListener('click', function (e) {
    drops.forEach(function (d) { if (d.open && !d.contains(e.target)) { d.open = false; } });
  });
  document.addEventListener('focusin', function (e) {
    drops.forEach(function (d) { if (d.open && !d.contains(e.target)) { d.open = false; } });
  });

  /* Panel: folded on narrow screens, always open on wide ones. */
  if (panel && window.matchMedia) {
    const narrow = window.matchMedia('(max-width: 781px)');
    const hasFilters = /[?&][^=]+=./.test(location.search);
    if (narrow.matches && !hasFilters) {
      panel.open = false;
    }
    const onChange = function () { if (!narrow.matches) { panel.open = true; } };
    if (narrow.addEventListener) {
      narrow.addEventListener('change', onChange);
    }
  }

  bindChips();

  /* A static copy opened at a filtered address: apply it now. */
  if (staticMode && stateFromUrl(location.href).active) {
    applyStatic(location.href);
  }
})();
