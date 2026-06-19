/* ── order.php scripts ── */


/* ── Star rating hover effect ── */
(function () {
  const group = document.getElementById('starGroup');
  if (!group) return;
  const labels = [...group.querySelectorAll('label')].reverse(); // re-reverse for display order
  labels.forEach((lbl, i) => {
    lbl.addEventListener('mouseenter', () => {
      labels.forEach((l, j) => l.style.color = j <= i ? '#2d5a2d' : '#d1d5db');
    });
    lbl.addEventListener('mouseleave', () => {
      labels.forEach(l => l.style.color = '');
    });
  });
})();

/* ── Live status polling ── */
function pollOrderStatus() {
  fetch('get_order.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.orders) return;
      data.orders.forEach(o => {
        const badge = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-badge');
        if (badge) {
          badge.textContent = o.status;
          badge.className = 'order-status dyn-status-badge ' + statusClass(o.status);
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
    'Processing': 'We\'re preparing your furniture for shipment.',
    'Shipped':    'Your order is on its way! Estimated arrival in 3–7 business days.',
    'Delivered':  'Your order has been delivered. Enjoy your new furniture!',
    'Completed':  'Order completed. Thank you for shopping with us!',
    'Cancelled':  'This order was cancelled.',
  };
  return m[s] || 'Your order is being processed.';
}

function downloadReceipt() {
  const data = window.orderReceiptData || {};
  const receiptHTML = `
  <div style="font-family: var(--ui-font); color:#1a3a2e; padding:40px 30px; max-width:620px; background:#ffffff;">
    <!-- Header with logo and brand -->
    <div style="text-align:center;margin-bottom:30px;border-bottom:3px solid #2d5a2d;padding-bottom:20px;">
      <div style="font-size:32px;font-weight:800;font-family:'Playfair Display',serif;color:#1a2e1a;letter-spacing:2px;margin-bottom:4px;"> ZYTHERA </div>
      <div style="font-size:13px;color:#666;letter-spacing:1px;font-weight:500;">FURNITURE • OFFICIAL RECEIPT</div>
    </div>

    <!-- Order Details Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;font-size:13px;">
      <div style="background:#f5f5f5;padding:12px 14px;border-radius:6px;border-left:4px solid #2d5a2d;">
        <div style="color:#666;font-size:11px;font-weight:600;margin-bottom:4px;text-transform:uppercase;">Order Number</div>
        <div style="font-weight:700;color:#1a3a2e;font-size:14px;">${data.orderId || 'N/A'}</div>
      </div>
      <div style="background:#f5f5f5;padding:12px 14px;border-radius:6px;border-left:4px solid #2d5a2d;">
        <div style="color:#666;font-size:11px;font-weight:600;margin-bottom:4px;text-transform:uppercase;">Order Date</div>
        <div style="font-weight:700;color:#1a3a2e;font-size:14px;">${data.date || 'N/A'}</div>
      </div>
    </div>

    <!-- Delivery & Payment Info -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;font-size:13px;">
      <div>
        <div style="color:#2d5a2d;font-weight:700;font-size:12px;margin-bottom:10px;text-transform:uppercase;">Delivery Information</div>
        <div style="background:#fafafa;padding:12px;border-radius:6px;line-height:1.6;font-size:13px;color:#1a3a2e;">
          <div style="font-weight:600;">${data.fullName || ''}</div>
          <div>${data.address || ''}${data.barangay ? ', ' + data.barangay : ''}${data.city ? ', ' + data.city : ''}${data.province ? ', ' + data.province : ''}${data.zip ? ' ' + data.zip : ''}</div>
          <div style="margin-top:6px;color:#666;">${data.phone || ''}</div>
        </div>
      </div>
      <div>
        <div style="color:#2d5a2d;font-weight:700;font-size:12px;margin-bottom:10px;text-transform:uppercase;">Payment Method</div>
        <div style="background:#f0fdf4;padding:12px;border-radius:6px;border-left:3px solid #2d5a2d;font-size:13px;color:#1a3a2e;font-weight:600;">${data.payMethod || 'N/A'}</div>
      </div>
    </div>

    <!-- Items Table -->
    <div style="margin-bottom:24px;">
      <div style="color:#2d5a2d;font-weight:700;font-size:12px;margin-bottom:12px;text-transform:uppercase;">Order Items</div>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#2d5a2d;color:#fff;border:none;">
            <th style="padding:10px 12px;text-align:left;font-weight:600;border:none;">Item</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;border:none;width:60px;">Qty</th>
            <th style="padding:10px 12px;text-align:right;font-weight:600;border:none;width:100px;">Unit Price</th>
            <th style="padding:10px 12px;text-align:right;font-weight:600;border:none;width:100px;">Amount</th>
          </tr>
        </thead>
        <tbody>
          ${(data.items || []).map(function(item){ return `
            <tr style="border-bottom:1px solid #eee;">
              <td style="padding:10px 12px;color:#1a3a2e;">${item.name}</td>
              <td style="padding:10px 12px;text-align:center;color:#1a3a2e;">${item.qty}</td>
              <td style="padding:10px 12px;text-align:right;color:#1a3a2e;">₱${Number(item.price).toFixed(2)}</td>
              <td style="padding:10px 12px;text-align:right;font-weight:600;color:#2d5a2d;">₱${Number(item.subtotal).toFixed(2)}</td>
            </tr>`; }).join('')}
        </tbody>
      </table>
    </div>

    <!-- Totals Section -->
    <div style="background:#f9f9f9;padding:16px 14px;border-radius:6px;margin-bottom:20px;border:1px solid #e0e0e0;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;color:#1a3a2e;">
        <div>Subtotal</div>
        <div>₱${Number(data.subtotal || 0).toFixed(2)}</div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:13px;color:#1a3a2e;border-bottom:1px solid #e0e0e0;padding-bottom:12px;">
        <div>Shipping & Handling</div>
        <div>₱${Number(data.shipping || 0).toFixed(2)}</div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;color:#2d5a2d;">
        <div>TOTAL PAID</div>
        <div>₱${Number(data.total || 0).toFixed(2)}</div>
      </div>
    </div>

    <!-- Special Notes -->
    ${ (data.payMethod === 'Cash on Delivery (COD)' && !data.isDelivered) ? `
      <div style="background:#fff3cd;padding:12px;border-left:4px solid #ffc107;border-radius:4px;margin-bottom:16px;font-size:12px;color:#856404;">
        <strong>NOTE:</strong> Cash on Delivery — payment will be collected upon delivery.
      </div>
    ` : '' }

    <!-- Footer -->
    <div style="text-align:center;padding-top:16px;border-top:1px solid #eee;color:#666;font-size:12px;line-height:1.6;">
      <p style="margin:0;">Thank you for your purchase!</p>
      <p style="margin:6px 0 0;">For inquiries, message us at: <strong>zythera@gmail.com</strong></p>
      <p style="margin:4px 0;font-size:11px;color:#999;">Order printed on ${new Date().toLocaleString()}</p>
    </div>
  </div>
  `;

  // create a temporary container to render HTML
  const container = document.createElement('div');
  container.style.width = '750px';
  container.style.background = '#fff';
  container.style.padding = '6px';
  container.innerHTML = receiptHTML;
  document.body.appendChild(container);

  // if jspdf + html2canvas loaded, use them
  if (window.jspdf && window.html2canvas) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const contentWidthPt = 750 * 0.75; // px -> pt approx at scale 1.2
    const xOffset = Math.max(20, (pageWidth - contentWidthPt) / 2);
    doc.html(container, {
      callback: function (doc) {
        doc.save('ZYTHERA_receipt_' + (data.orderId || 'order') + '.pdf');
        if (container.parentNode) container.parentNode.removeChild(container);
      },
      x: xOffset,
      y: 20,
      html2canvas: { scale: 1.2 }
    });
  } else {
    // fallback: open printable window (user can Save as PDF), centered on screen
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Receipt</title><style>body{margin:0;padding:24px;min-height:100vh;display:flex;justify-content:center;align-items:flex-start;background:#f0f0f0;box-sizing:border-box;}</style></head><body>' + receiptHTML + '</body></html>');
    w.document.close();
    w.focus();
    w.print();
    if (container.parentNode) container.parentNode.removeChild(container);
  }
}

window.orderReceiptData = ORDER_DATA; // seeded inline by order.php

function toggleReviewEditForm(show) {
  const form = document.getElementById('review-edit-form');
  if (!form) return;
  form.style.display = show ? '' : 'none';
  if (show) {
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

setInterval(pollOrderStatus, 30000);
