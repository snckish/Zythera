/* ── orders.php scripts ── */

function pollOrderStatuses() {
  fetch('get_order.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.orders) return;
      data.orders.forEach(o => {
        const badge = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-badge');
        if (badge) {
          badge.textContent = o.status;
          badge.className   = 'order-status dyn-status-badge ' + statusClass(o.status);
        }
        const msg = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-msg');
        if (msg) msg.textContent = statusMsg(o.status);
      });
    }).catch(() => {});
}
function statusClass(s) {
  const m = { pending:'st-pending', processing:'st-processing', shipped:'st-shipped', delivered:'st-delivered', completed:'st-delivered', cancelled:'st-cancelled' };
  return m[s.toLowerCase()] || 'st-pending';
}
function statusMsg(s) {
  const m = {
    'Pending':    'Your order has been received and is awaiting confirmation.',
    'Processing': 'We re preparing your furniture for shipment.',
    'Shipped':    'Your order is on its way! Estimated arrival in 3–7 business days.',
    'Delivered':  'Your order has been delivered. Enjoy your new furniture!',
    'Completed':  'Order completed. Thank you for shopping with us!',
    'Cancelled':  'This order was cancelled.',
  };
  return m[s] || 'Your order is being processed.';
}
setInterval(pollOrderStatuses, 30000);
