(() => {
  const base = () => (window.KRAVED?.baseUrl || '').replace(/\/$/, '');
  const csrf = () => window.KRAVED?.csrf || '';

  let lastId = window.KRAVED_LAST_ORDER_ID || 0;

  async function poll() {
    if (typeof window.KRAVED_LAST_ORDER_ID === 'undefined') return;
    try {
      const res = await fetch(base() + '/admin/api/orders/poll?after=' + lastId, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        credentials: 'same-origin',
      });
      const data = await res.json();
      if (data.pending != null) {
        const badge = document.getElementById('pending-badge');
        if (badge) badge.textContent = data.pending > 0 ? String(data.pending) : '';
      }
      if (data.orders?.length) {
        lastId = data.latest || lastId;
        document.getElementById('order-chime')?.play().catch(() => {});
        const status = document.getElementById('poll-status');
        if (status) status.textContent = `${data.orders.length} new order(s) — refreshing…`;
        setTimeout(() => location.reload(), 1200);
      }
    } catch (e) { /* ignore */ }
  }

  if (typeof window.KRAVED_LAST_ORDER_ID !== 'undefined') {
    setInterval(poll, 10000);
  }
})();
