/* ── admin.php scripts ── */


// ── Date / Time ──────────────────────────────────────────────
function updateDateTime() {
    const now = new Date();
    const d = now.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
    const t = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    document.getElementById('datetime').textContent = d + ', ' + t;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ── Section switching (all sidebar nav items) ─────────────────
const sectionTitles = {
    inventory:  'Product Inventory',
    addproduct: 'Add Product',
    analytics:  'Analytics Dashboard',
    orders:     'Order History',
    users:      'User Summary',
    reviews:    'User Reviews'
};

function showSection(name) {
    ['inventory','addproduct','analytics','orders','users','reviews'].forEach(s => {
        document.getElementById('section-' + s).style.display = s === name ? '' : 'none';
    });
    document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
    const nav = document.getElementById('nav-' + name);
    if (nav) nav.classList.add('active');
    const titleEl = document.getElementById('sectionTitle');
    if (titleEl) titleEl.textContent = sectionTitles[name] || '';
    if (name === 'addproduct') resetForm();
    // Auto-close the off-canvas sidebar after choosing a section on mobile/tablet
    if (window.innerWidth <= 991.98) closeSidebar();
}

// ── Mobile sidebar toggle ──────────────────────────────────────
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('sidebarBackdrop').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('adminSidebar').classList.remove('show');
    document.getElementById('sidebarBackdrop').classList.remove('show');
}


// ── Edit product: switch to Add Product section then fill form ─
function editProduct(id, name, size, color, price, desc, stock, category, image) {
    showSection('addproduct');
    document.getElementById('pid').value    = id;
    document.getElementById('pname').value  = name;
    document.getElementById('psize').value  = size;
    document.getElementById('pcolor').value = color;
    document.getElementById('pprice').value = price;
    document.getElementById('pdesc').value  = desc;
    document.getElementById('pstock').value = stock;
    document.getElementById('pcat').value   = category;
    document.getElementById('pimage').value = image;
    document.getElementById('sectionTitle').textContent = 'Edit Product';
    window.scrollTo({top:0, behavior:'smooth'});
}

// ── Reset form to blank (new product) ────────────────────────
function resetForm() {
    ['pid','pname','psize','pcolor','pprice','pdesc','pstock','pimage'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const cat = document.getElementById('pcat');
    if (cat) cat.value = 'Sofa';
}

// ── Search ────────────────────────────────────────────────────
function searchProducts(query) {
    const q         = query.trim().toLowerCase();
    const rows      = document.querySelectorAll('.product-row');
    const clearBtn  = document.getElementById('clearBtn');
    const noResults = document.getElementById('noResults');
    const countEl   = document.getElementById('result-count');

    clearBtn.style.display = q ? 'block' : 'none';
    let visible = 0;

    rows.forEach(row => {
        const haystack = [row.dataset.name, row.dataset.color, row.dataset.category,
                          row.dataset.description, row.dataset.size].join(' ');
        const match = q === '' || haystack.indexOf(q) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) { visible++; q ? highlightRow(row, q) : clearHighlight(row); }
        else clearHighlight(row);
    });

    noResults.style.display = (visible === 0 && q) ? '' : 'none';
    document.getElementById('noResultsQuery').textContent = query;
    const total = rows.length;
    countEl.textContent = q ? `(${visible} of ${total} shown)` : `(${total} total)`;
}

function highlightRow(row, q) {
    const targets = row.querySelectorAll('.s-name,.s-size,.s-color,.s-desc,.s-category');
    const regex   = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    targets.forEach(cell => {
        if (!cell.dataset.original) cell.dataset.original = cell.textContent;
        cell.innerHTML = cell.dataset.original.replace(regex, '<mark>$1</mark>');
    });
}

function clearHighlight(row) {
    row.querySelectorAll('[data-original]').forEach(cell => {
        cell.textContent = cell.dataset.original;
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchProducts('');
}

// ── Update Order Status ───────────────────────────────────────
function updateOrderStatus(email, orderId, newStatus) {
    if (!newStatus) return;
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus),
        { credentials: 'same-origin' })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            showToast('Order #' + orderId + ' → ' + newStatus);
            updatePendingBadge();
            const sel = document.getElementById('status-sel-' + orderId);
            if (sel) sel.value = '';
        } else {
            alert(data.message || 'Could not update status.');
        }
    })
    .catch(err => alert('Request failed: ' + err.message));
}

// ── Update Payment Status ─────────────────────────────────────
function updatePayment(orderId, payStatus) {
    const refInput = document.getElementById('pay-ref-input-' + orderId);
    const refNo    = refInput ? refInput.value.trim() : '';

    const body = new URLSearchParams({
        update_payment: '1',
        order_id:       orderId,
        pay_status:     payStatus,
        reference_no:   refNo,
    });

    fetch('admin_action.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        body.toString(),
    })
    .then(r => {
        if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
        return r.text().then(t => {
            try { return JSON.parse(t); }
            catch(e) { throw new Error('Bad JSON from server: ' + t.substring(0, 200)); }
        });
    })
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Could not update payment.');
            return;
        }
        // Update status badge colour
        const badge = document.getElementById('pay-status-badge-' + orderId);
        const refText = document.getElementById('pay-ref-text-' + orderId);
        const statusColors = {
            pending:  { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
            verified: { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
            rejected: { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
        };
        const sc = statusColors[payStatus] || statusColors['pending'];
        if (badge) {
            badge.textContent     = payStatus;
            badge.style.background = sc.bg;
            badge.style.color      = sc.color;
            badge.style.border     = '1px solid ' + sc.border;
        }
        if (refText) {
            refText.textContent = data.reference_no || '—';
        }
        const labels = { verified: '✓ Payment verified', rejected: '✗ Payment rejected', pending: 'Payment set to pending' };
        showToast(labels[payStatus] || 'Payment updated');
        updatePendingBadge();
    })
    .catch(err => alert('Request failed: ' + err.message));
}

// ── Delete User ───────────────────────────────────────────────
function deleteUser(email, name) {
    if (!confirm('Delete user "' + name + '" (' + email + ')?\nThis will also remove their cart and orders.')) return;
    fetch('admin_action.php?delete_user=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('User ' + name + ' deleted.');
                // Remove the card from DOM
                document.querySelectorAll('.user-email-tag').forEach(el => {
                    if (el.dataset.email === email) {
                        el.closest('.col-md-6').remove();
                    }
                });
                // Reload section after short delay
                setTimeout(() => window.location.reload(), 800);
            } else {
                alert(data.message || 'Could not delete user.');
            }
        })
        .catch(() => alert('Request failed.'));
}

function showToast(msg, isError = false) {
    let t = document.getElementById('admin-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'admin-toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;color:#fff;padding:14px 22px;border-radius:12px;font-size:.86rem;z-index:9999;box-shadow:0 6px 24px rgba(0,0,0,.2);transition:.3s;';
        document.body.appendChild(t);
    }
    t.style.background = isError ? '#b91c1c' : '#166534';
    t.style.color = '#ffffff';
    t.textContent = msg;
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3500);
}

// ── Toggle order detail panel ─────────────────────────────────
function toggleOrderDetail(orderId) {
    const panel = document.getElementById('detail-' + orderId);
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
}

// ── Quick status button (in detail panel) ─────────────────────
function quickStatus(email, orderId, newStatus) {
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus),
        { credentials: 'same-origin' })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            const sel = document.getElementById('status-sel-' + orderId);
            if (sel) sel.value = '';
            // Update pending badge in sidebar
            updatePendingBadge();
            showToast('✓ Order #' + orderId + ' → ' + newStatus);
        } else {
            showToast(data.message || 'Could not update status.', true);
        }
    }).catch(err => showToast('Request failed: ' + err.message, true));
}

// ── Refresh pending count on sidebar ─────────────────────────
function updatePendingBadge() {
    fetch('get_pending.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        // Order-status pending badge (next to Orders nav item)
        let badge = document.getElementById('pending-badge');
        if (d.count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'pending-badge';
                badge.style.cssText = 'background:#dc2626;color:#fff;border-radius:50px;font-size:.6rem;font-weight:700;padding:2px 7px;';
                const navOrders = document.getElementById('nav-orders');
                if (navOrders) navOrders.appendChild(badge);
            }
            badge.textContent = d.count;
        } else if (badge) {
            badge.remove();
        }
    }).catch(() => {});
}

window.addEventListener('DOMContentLoaded', () => {
    const total = document.querySelectorAll('.product-row').length;
    const countEl = document.getElementById('result-count');
    if (countEl) countEl.textContent = `(${total} total)`;
});

// ── Logout modal functions ──
function openLogoutModal() {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeLogoutModal(event) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function performLogout() {
  const confirmBtn = document.querySelector('.logout-confirm-btn');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Logging out...';
  }
  window.location.href = 'logout.php';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutModal();
  }
});

// Close modal on outside click
document.addEventListener('click', function(e) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay && e.target === overlay) {
    closeLogoutModal(e);
  }
});

// ── Proof of Payment Lightbox ─────────────────────────────────
function openProofLightbox(src, e) {
  if (e) e.preventDefault();
  const lb = document.getElementById('proofLightbox');
  const img = document.getElementById('proofLightboxImg');
  if (lb && img) {
    img.src = src;
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}
function closeProofLightbox() {
  const lb = document.getElementById('proofLightbox');
  if (lb) { lb.style.display = 'none'; document.body.style.overflow = ''; }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProofLightbox(); });
