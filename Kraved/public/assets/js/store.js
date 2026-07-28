(() => {
  const base = () => (window.KRAVED?.baseUrl || '').replace(/\/$/, '');
  const csrf = () => window.KRAVED?.csrf || '';
  const money = (n) => (window.KRAVED?.currency || '£') + Number(n).toFixed(2);

  async function api(path, opts = {}) {
    const headers = Object.assign({
      'X-CSRF-TOKEN': csrf(),
      'Accept': 'application/json',
    }, opts.headers || {});
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const res = await fetch(base() + path, { ...opts, headers, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.error || data.message || 'Request failed'), { data, status: res.status });
    return data;
  }

  /* ---- Cart drawer ---- */
  const drawer = document.getElementById('cart-drawer');
  const backdrop = document.getElementById('cart-backdrop');

  function openCart() {
    drawer?.classList.add('open');
    backdrop?.classList.add('show');
    drawer?.setAttribute('aria-hidden', 'false');
    refreshCart();
  }
  function closeCart() {
    drawer?.classList.remove('open');
    backdrop?.classList.remove('show');
    drawer?.setAttribute('aria-hidden', 'true');
  }

  document.getElementById('open-cart')?.addEventListener('click', openCart);
  document.getElementById('close-cart')?.addEventListener('click', closeCart);
  backdrop?.addEventListener('click', closeCart);

  function renderCart(cart) {
    const badge = document.getElementById('cart-badge');
    if (badge) badge.textContent = cart.count || 0;

    const wrap = document.getElementById('cart-items');
    if (!wrap) return;

    if (!cart.items?.length) {
      wrap.innerHTML = '<p class="text-muted">Your basket is empty.</p>';
    } else {
      wrap.innerHTML = cart.items.map(item => {
        const addons = (item.addons || []).map(a => a.name).concat(
          (item.box_picks || []).map(b => b.name)
        ).join(', ');
        return `<div class="cart-line" data-key="${item.key}">
          <div class="cart-line-top">
            <strong>${item.quantity}× ${escapeHtml(item.name)}</strong>
            <span>${money(item.line_total)}</span>
          </div>
          ${addons ? `<div class="cart-line-addons">${escapeHtml(addons)}</div>` : ''}
          <div class="d-flex gap-2 mt-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-light qty-btn" data-delta="-1">−</button>
            <span>${item.quantity}</span>
            <button type="button" class="btn btn-sm btn-outline-light qty-btn" data-delta="1">+</button>
            <button type="button" class="btn btn-sm btn-link text-danger remove-btn ms-auto">Remove</button>
          </div>
        </div>`;
      }).join('');
    }

    document.getElementById('cart-subtotal').textContent = money(cart.subtotal);
    document.getElementById('cart-delivery').textContent = money(cart.delivery_fee);
    document.getElementById('cart-total').textContent = money(cart.total);

    const threshold = cart.free_delivery_at || window.KRAVED.freeDelivery || 25;
    const pct = Math.min(100, (cart.subtotal / threshold) * 100);
    const fill = document.getElementById('meter-fill');
    const text = document.getElementById('meter-text');
    if (fill) fill.style.width = pct + '%';
    if (text) {
      text.textContent = cart.free_delivery_remaining > 0
        ? `Spend ${money(cart.free_delivery_remaining)} more for free delivery`
        : 'You\'ve unlocked free delivery!';
    }

    const checkout = document.getElementById('cart-checkout');
    if (checkout) checkout.href = base() + '/checkout';
  }

  async function refreshCart() {
    try {
      const cart = await api('/api/cart');
      renderCart(cart);
    } catch (e) { /* ignore */ }
  }

  document.getElementById('cart-items')?.addEventListener('click', async (e) => {
    const line = e.target.closest('.cart-line');
    if (!line) return;
    const key = line.dataset.key;
    if (e.target.classList.contains('remove-btn')) {
      const cart = (await api('/api/cart/remove', { method: 'POST', body: { key } })).cart;
      renderCart(cart);
      return;
    }
    const btn = e.target.closest('.qty-btn');
    if (btn) {
      const qtyEl = line.querySelector('span');
      let qty = parseInt(qtyEl?.textContent || '1', 10) + parseInt(btn.dataset.delta, 10);
      const cart = (await api('/api/cart/update', { method: 'POST', body: { key, quantity: qty } })).cart;
      renderCart(cart);
    }
  });

  /* ---- Fulfillment ---- */
  let ffType = window.KRAVED?.fulfillment?.type || 'collection';

  document.querySelectorAll('[data-ff-type]').forEach(btn => {
    btn.addEventListener('click', () => {
      ffType = btn.dataset.ffType;
      document.querySelectorAll('[data-ff-type]').forEach(b => b.classList.toggle('active', b === btn));
      document.getElementById('ff-delivery-fields')?.classList.toggle('d-none', ffType !== 'delivery');
      document.getElementById('ff-collection-note')?.classList.toggle('d-none', ffType !== 'collection');
    });
  });

  document.getElementById('ff-save')?.addEventListener('click', async () => {
    const msg = document.getElementById('ff-zone-msg');
    try {
      const data = await api('/api/fulfillment', {
        method: 'POST',
        body: {
          type: ffType,
          postcode: document.getElementById('ff-postcode')?.value || '',
          time_slot: document.getElementById('ff-slot')?.value || 'ASAP',
        },
      });
      if (msg) { msg.textContent = data.message || 'Saved'; msg.className = 'small mb-3 text-success'; }
      document.getElementById('ff-type-label').textContent = ffType === 'delivery' ? 'Delivery' : 'Collection';
      document.getElementById('ff-slot-label').textContent = data.fulfillment?.time_slot || 'ASAP';
      document.getElementById('ff-detail-label').textContent =
        ffType === 'delivery' ? (data.fulfillment?.postcode || '') : 'Baker St, London';
      if (data.cart) renderCart(data.cart);
      setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('fulfillmentModal'))?.hide();
      }, 400);
    } catch (err) {
      if (msg) { msg.textContent = err.data?.message || err.message; msg.className = 'small mb-3 text-danger'; }
    }
  });

  /* ---- Product modal ---- */
  let pmState = { product: null, groups: [], boxChoices: [], qty: 1 };

  const pmModalEl = document.getElementById('productModal');
  const pmModal = pmModalEl ? new bootstrap.Modal(pmModalEl) : null;

  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-add');
    if (!btn) return;
    const id = btn.dataset.productId;
    const body = document.getElementById('pm-body');
    body.innerHTML = '<div class="text-center py-4 text-muted">Loading…</div>';
    pmState.qty = 1;
    document.getElementById('pm-qty').textContent = '1';
    pmModal?.show();
    try {
      const data = await api('/api/product/' + id + '/customise');
      pmState.product = data.product;
      pmState.groups = data.groups || [];
      pmState.boxChoices = data.box_choices || [];
      document.getElementById('pm-title').textContent = data.product.title;
      body.innerHTML = buildCustomiseHtml(data);
      updatePmPrice();
    } catch (err) {
      body.innerHTML = '<p class="text-danger">Could not load product.</p>';
    }
  });

  function buildCustomiseHtml(data) {
    let html = `<p class="opacity-75">${escapeHtml(data.product.short_description || '')}</p>`;
    if (Number(data.product.is_box_deal) && data.box_choices?.length) {
      const max = Number(data.product.box_max_items) || 4;
      html += `<div class="addon-group" data-box-max="${max}">
        <h3>Pick ${max} cookies</h3>
        ${data.box_choices.map(c => `
          <div class="addon-option">
            <label><input type="checkbox" class="box-pick form-check-input me-2" value="${c.id}"> ${escapeHtml(c.title)}</label>
          </div>`).join('')}
      </div>`;
    }
    (data.groups || []).forEach(g => {
      const multi = Number(g.max_selection) > 1;
      const type = multi || Number(g.min_selection) === 0 ? 'checkbox' : 'radio';
      html += `<div class="addon-group" data-group-id="${g.id}" data-min="${g.min_selection}" data-max="${g.max_selection}" data-required="${g.is_required}">
        <h3>${escapeHtml(g.title)}${Number(g.is_required) ? ' *' : ''}</h3>
        ${(g.addons || []).map(a => `
          <div class="addon-option">
            <label>
              <input type="${type}" class="addon-input form-check-input me-2" name="g${g.id}" value="${a.id}" data-price="${a.price}">
              ${escapeHtml(a.name)}
            </label>
            <span>${Number(a.price) > 0 ? '+' + money(a.price) : 'Free'}</span>
          </div>`).join('')}
      </div>`;
    });
    if (!Number(data.product.is_box_deal) && !(data.groups || []).length) {
      html += '<p class="small opacity-75">No extras — ready to add.</p>';
    }
    return html;
  }

  function selectedAddonIds() {
    return [...document.querySelectorAll('#pm-body .addon-input:checked')].map(el => Number(el.value));
  }

  function selectedBoxPicks() {
    return [...document.querySelectorAll('#pm-body .box-pick:checked')].map(el => Number(el.value));
  }

  function updatePmPrice() {
    if (!pmState.product) return;
    let extra = 0;
    document.querySelectorAll('#pm-body .addon-input:checked').forEach(el => {
      extra += Number(el.dataset.price || 0);
    });
    const unit = Number(pmState.product.base_price) + extra;
    const total = unit * pmState.qty;
    document.getElementById('pm-add').textContent = `Add · ${money(total)}`;
  }

  document.getElementById('pm-body')?.addEventListener('change', (e) => {
    if (e.target.classList.contains('box-pick')) {
      const max = Number(e.target.closest('[data-box-max]')?.dataset.boxMax || 4);
      const checked = document.querySelectorAll('#pm-body .box-pick:checked');
      if (checked.length > max) {
        e.target.checked = false;
      }
    }
    // Enforce max per addon group
    const group = e.target.closest('.addon-group');
    if (group && e.target.classList.contains('addon-input') && e.target.type === 'checkbox') {
      const max = Number(group.dataset.max || 99);
      const checked = group.querySelectorAll('.addon-input:checked');
      if (checked.length > max) e.target.checked = false;
    }
    updatePmPrice();
  });

  document.getElementById('pm-minus')?.addEventListener('click', () => {
    pmState.qty = Math.max(1, pmState.qty - 1);
    document.getElementById('pm-qty').textContent = String(pmState.qty);
    updatePmPrice();
  });
  document.getElementById('pm-plus')?.addEventListener('click', () => {
    pmState.qty += 1;
    document.getElementById('pm-qty').textContent = String(pmState.qty);
    updatePmPrice();
  });

  document.getElementById('pm-add')?.addEventListener('click', async () => {
    if (!pmState.product) return;

    // Validate required groups
    for (const g of pmState.groups) {
      if (!Number(g.is_required) && Number(g.min_selection) === 0) continue;
      const wrap = document.querySelector(`.addon-group[data-group-id="${g.id}"]`);
      const n = wrap ? wrap.querySelectorAll('.addon-input:checked').length : 0;
      if (n < Number(g.min_selection || (g.is_required ? 1 : 0))) {
        alert(`Please complete: ${g.title}`);
        return;
      }
    }
    if (Number(pmState.product.is_box_deal)) {
      const max = Number(pmState.product.box_max_items) || 4;
      if (selectedBoxPicks().length !== max) {
        alert(`Please select exactly ${max} cookies for this box.`);
        return;
      }
    }

    try {
      const data = await api('/api/cart/add', {
        method: 'POST',
        body: {
          product_id: Number(pmState.product.id),
          quantity: pmState.qty,
          addon_ids: selectedAddonIds(),
          box_picks: selectedBoxPicks(),
        },
      });
      renderCart(data.cart);
      pmModal?.hide();
      openCart();
    } catch (err) {
      alert(err.data?.error || err.message);
    }
  });

  /* ---- Smooth category nav ---- */
  document.querySelectorAll('.cat-link').forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href?.startsWith('#')) {
        e.preventDefault();
        document.querySelector(href)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelectorAll('.cat-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
      }
    });
  });

  /* ---- Order status polling ---- */
  const tracker = document.getElementById('status-tracker');
  if (tracker) {
    const orderNo = tracker.dataset.order;
    setInterval(async () => {
      try {
        const data = await api('/api/order/' + encodeURIComponent(orderNo) + '/status');
        tracker.querySelectorAll('.status-step').forEach(el => {
          el.classList.remove('done', 'current', 'upcoming');
          const step = (data.steps || []).find(s => s.key === el.dataset.key);
          el.classList.add(step?.state || 'upcoming');
        });
      } catch (e) { /* ignore */ }
    }, 8000);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Auto-open fulfillment on first visit
  if (!sessionStorage.getItem('kraved_ff_seen')) {
    const modalEl = document.getElementById('fulfillmentModal');
    if (modalEl && window.bootstrap) {
      new bootstrap.Modal(modalEl).show();
      sessionStorage.setItem('kraved_ff_seen', '1');
    }
  }

  refreshCart();
})();
