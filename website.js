/* ── website.php scripts ── */

    // Drag-to-scroll for review track
    (function() {
      const track = document.getElementById('reviewTrack');
      if (!track) return;
      let isDown = false,
        startX, scrollLeft;
      track.addEventListener('mousedown', e => {
        isDown = true;
        track.classList.add('dragging');
        startX = e.pageX - track.offsetLeft;
        scrollLeft = track.scrollLeft;
      });
      track.addEventListener('mouseleave', () => {
        isDown = false;
        track.classList.remove('dragging');
      });
      track.addEventListener('mouseup', () => {
        isDown = false;
        track.classList.remove('dragging');
      });
      track.addEventListener('mousemove', e => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - track.offsetLeft;
        track.scrollLeft = scrollLeft - (x - startX) * 1.4;
      });
    })();


    // Contact form functionality that needs the DOM to be ready first
    window.addEventListener('DOMContentLoaded', function() {
      // If server-rendered success alert exists (PRG path), fade and remove it after a short delay
      (function() {
        const alert = document.getElementById('contactSuccessAlert');
        if (!alert) return;
        // start fading after 1 second, then remove after the transition
        setTimeout(() => {
          alert.style.opacity = '0';
          setTimeout(() => {
            if (alert.parentNode) alert.parentNode.removeChild(alert);
          }, 400);
        }, 1000);
      })();

      // AJAX submit for contact form: clears only the message textarea and shows a toast
      (function() {
        const form = document.getElementById('contactForm');
        const toast = document.getElementById('contactToast');
        if (!form || !toast) return;

        function showToast(msg, isError) {
          toast.textContent = msg;
          toast.classList.toggle('error', !!isError);
          toast.classList.add('show');
          setTimeout(() => toast.classList.remove('show'), 3500);
        }

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          const btn = this.querySelector('button[type=submit]');
          if (btn) {
            btn.disabled = true;
          }

          const data = new FormData(this);
          if (!data.has('send_message')) data.append('send_message', '1');

          try {
            const res = await fetch('website.php', {
              method: 'POST',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              },
              body: data
            });
            const json = await res.json();
            if (json && json.success) {
              const ta = form.querySelector('textarea[name=c_message]');
              const subject = form.querySelector('input[name=c_subject]');
              if (ta) ta.value = '';
              if (subject) subject.value = '';
              showToast("Message sent! We'll get back to you soon.", false);
            } else {
              showToast(json.error || 'Could not send message. Please try again.', true);
            }
          } catch (err) {
            showToast('Network error. Please try again later.', true);
          } finally {
            if (btn) {
              btn.disabled = false;
            }
          }
        });
      })();
    });


    // ── Cart state seeded from PHP session + live DB via fetch ──
    // cartItemsJS, selectedCartIds, stockMap, IS_LOGGED_IN, USER_ROLE
    // are seeded inline by website.php before this file loads

    // On page load, always sync cart from DB so badge is accurate
    (function syncCartOnLoad() {
      if (IS_LOGGED_IN && USER_ROLE !== 'admin') {
        fetch('getcart.php', {
            credentials: 'same-origin'
          })
          .then(r => r.json())
          .then(data => {
            if (data.cart) {
              cartItemsJS = data.cart;
              const badge = document.getElementById('cart-badge');
              if (badge) {
                badge.textContent = data.total_items;
                badge.style.display = data.total_items > 0 ? '' : 'none';
              }
              renderCart();
            }
          }).catch(() => {});
      }
    })();

    // ── Open / Close cart panel ───────────────────────────────────
    function openCart() {
      document.getElementById('cartPanel').style.right = '0';
      document.getElementById('cartBackdrop').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeCart() {
      document.getElementById('cartPanel').style.right = '-110vw';
      document.getElementById('cartBackdrop').style.display = 'none';
      document.body.style.overflow = '';
    }

    function syncSelectedCartIds() {
      const currentIds = new Set(cartItemsJS.map(item => String(item.inv_id)));
      selectedCartIds = new Set([...selectedCartIds].filter(id => currentIds.has(id)));
      localStorage.setItem('zythera_selected_cart', JSON.stringify([...selectedCartIds]));
    }

    function toggleCartSelection(itemId, checked) {
      itemId = String(itemId);
      if (checked) selectedCartIds.add(itemId);
      else selectedCartIds.delete(itemId);
      syncSelectedCartIds();
      updateSelectAllUI();
      const err = document.getElementById('cartSelectionError');
      if (err && selectedCartIds.size > 0) err.style.display = 'none';
    }

    // ── Select-All toolbar: check/uncheck every item at once ──────
    function toggleSelectAll(checked) {
      document.querySelectorAll('.cart-select-checkbox').forEach(cb => {
        cb.checked = checked;
      });
      cartItemsJS.forEach(item => {
        const id = String(item.inv_id);
        if (checked) selectedCartIds.add(id);
        else selectedCartIds.delete(id);
      });
      syncSelectedCartIds();
      updateSelectAllUI();
      const err = document.getElementById('cartSelectionError');
      if (err && selectedCartIds.size > 0) err.style.display = 'none';
    }

    // Keeps the Select-All checkbox (checked/indeterminate), its visibility,
    // and the "x of y selected" label in sync with the current selection.
    function updateSelectAllUI() {
      const bar = document.getElementById('cartSelectAllBar');
      const cb = document.getElementById('cartSelectAllCheckbox');
      const countEl = document.getElementById('cartSelectAllCount');
      if (!bar || !cb) return;

      const total = cartItemsJS.length;
      if (total < 2) {
        bar.style.display = 'none';
        return;
      }
      bar.style.display = 'flex';

      const selectedCount = cartItemsJS.filter(i => selectedCartIds.has(String(i.inv_id))).length;
      cb.checked = total > 0 && selectedCount === total;
      cb.indeterminate = selectedCount > 0 && selectedCount < total;
      if (countEl) countEl.textContent = selectedCount + ' of ' + total + ' selected';
    }

    function goToSelectedCheckout(event) {
      syncSelectedCartIds();
      if (selectedCartIds.size === 0) {
        if (event) event.preventDefault();
        const err = document.getElementById('cartSelectionError');
        if (err) {
          err.textContent = 'Please select products first.';
          err.style.display = 'block';
        }
        openCart();
        return false;
      }
      const selected = encodeURIComponent([...selectedCartIds].join(','));
      window.location.href = 'checkout.php?selected=' + selected;
      if (event) event.preventDefault();
      return false;
    }

    document.addEventListener('DOMContentLoaded', function () {
      if (new URLSearchParams(window.location.search).get('cart_error') === 'select_items') {
        const err = document.getElementById('cartSelectionError');
        if (err) {
          err.textContent = 'Please select products first.';
          err.style.display = 'block';
        }
        openCart();
      }
    });

    // ── Re-render cart panel items + subtotal + header count ──────
    function renderCart() {
      const container = document.getElementById('cartItems');
      const footer = document.getElementById('cartFooter');
      const subEl = document.getElementById('cartSubtotal');
      const countEl = document.getElementById('cartItemCount');

      let subtotal = 0,
        totalQty = 0,
        distinctCount = cartItemsJS.length;

      syncSelectedCartIds();

      if (cartItemsJS.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:60px 20px;color:#bbb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
              stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round"
              style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
          </div>`;
        if (footer) footer.style.display = 'none';
        if (countEl) countEl.textContent = 'Your cart is empty';
        const badge = document.getElementById('cart-badge');
        if (badge) {
          badge.textContent = '0';
          badge.style.display = 'none';
        }
        updateSelectAllUI();
        return;
      }

      let html = '';
      cartItemsJS.forEach(item => {
        const price = Number(item.price) || 0;
        const qty = Number(item.qty) || 1;
        const lineTotal = price * qty;
        const stock = stockMap[item.inv_id] ?? 99;
        subtotal += lineTotal;
        totalQty += qty;
        const imgSrc = item.image || 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';
        const itemId = String(item.inv_id);
        const checked = selectedCartIds.has(itemId) ? 'checked' : '';

        const stockLabel = stock === 0 ? 'Out of Stock' :
          stock <= 5 ? 'Low stock: ' + stock + ' left' :
          'In stock: ' + stock;
        const stockColor = stock === 0 ? '#dc2626' : stock <= 5 ? '#f59e0b' : '#16a34a';

        html += `
          <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <input type="checkbox" class="cart-select-checkbox" value="${escHtml(itemId)}" ${checked}
                onchange="toggleCartSelection('${escHtml(itemId)}', this.checked)"
                style="width:18px;height:18px;accent-color:var(--green);flex-shrink:0;cursor:pointer;"
                aria-label="Select ${escHtml(String(item.name || 'item'))} for checkout">
              <img src="${escHtml(imgSrc)}" alt=""
                style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  ${escHtml(String(item.name))}
                </div>
                <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱${price.toLocaleString('en-PH')} each</div>
                <div style="font-size:.68rem;color:${stockColor};font-weight:600;margin-top:2px;">${stockLabel}</div>
              </div>
              <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                ₱${lineTotal.toLocaleString('en-PH')}
              </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
              <div style="display:flex;align-items:center;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                <button onclick="cartQty('${item.inv_id}','minus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                <span id="panel-qty-${item.inv_id}"
                  style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;">${qty}</span>
                <button onclick="cartQty('${item.inv_id}','plus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
              </div>
              <button onclick="cartQty('${item.inv_id}','remove')"
                style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
              </button>
            </div>
          </div>`;
      });

      container.innerHTML = html;
      if (subEl) subEl.textContent = '₱' + subtotal.toLocaleString('en-PH');
      if (footer) footer.style.display = 'block';
      if (countEl) {
        countEl.textContent = distinctCount === 1 ? '1 item in cart' : distinctCount + ' items in cart';
      }
      const badge = document.getElementById('cart-badge');
      if (badge) {
        badge.textContent = distinctCount;
        badge.style.display = distinctCount > 0 ? '' : 'none';
      }
      updateSelectAllUI();
    }

    // ── Qty stepper in cart sidebar → update_cart.php ────────────
    function cartQty(itemId, action) {
      // Client-side stock cap before even hitting server
      if (action === 'plus') {
        const item = cartItemsJS.find(i => String(i.inv_id) === String(itemId));
        const max = stockMap[itemId] ?? 9999;
        if (item && Number(item.qty) >= max) {
          showToast('Maximum stock (' + max + ') already in cart.', 'error');
          return;
        }
      }

      fetch('update_cart.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'item_id=' + encodeURIComponent(itemId) + '&qty_action=' + encodeURIComponent(action)
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showToast(data.message || 'Could not update cart.', 'error');
          return;
        }
        // Replace local cart state with authoritative server response
        cartItemsJS = data.cart || [];
        renderCart();
        // If checkout is open in another tab/frame, notify it via BroadcastChannel
        try {
          const bc = new BroadcastChannel('zythera_cart');
          bc.postMessage({ type: 'cart_updated', cart: cartItemsJS });
          bc.close();
        } catch (e) { /* BroadcastChannel not supported — no-op */ }
      })
      .catch(() => showToast('Could not update cart. Try again.', 'error'));
    }

    // ── Add item to local JS state then re-render ─────────────────
    function updateCartPanel(newItem) {
      const existing = cartItemsJS.find(i => String(i.inv_id) === String(newItem.inv_id));
      if (existing) {
        existing.qty = Number(existing.qty) + Number(newItem.qty);
        if (!existing.image && newItem.image) existing.image = newItem.image;
      } else {
        cartItemsJS.push({
          inv_id: newItem.inv_id,
          name: newItem.name,
          price: Number(newItem.price),
          qty: Number(newItem.qty),
          image: newItem.image || ''
        });
      }
      renderCart();
    }

    function escHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Time display ──────────────────────────────────────────────
    function updateTime() {
      const el = document.getElementById('liveTime');
      if (el) el.textContent = new Date().toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }
    setInterval(updateTime, 1000);
    updateTime();

    function toggleDesc(btn) {
      const p = btn.parentElement;
      const s = p.querySelector('.desc-short');
      const f = p.querySelector('.desc-full');
      const hidden = f.classList.contains('d-none');
      f.classList.toggle('d-none', !hidden);
      s.classList.toggle('d-none', hidden);
      btn.textContent = hidden ? 'See Less' : 'See More';
    }

    function showToast(msg, type = 'success') {
      const t = document.getElementById('toast-msg');
      t.textContent = msg;
      t.className = 'toast-fixed show' + (type === 'error' ? ' error' : '');
      setTimeout(() => t.classList.remove('show'), 3500);
    }

    function addToCart(id, name, price, image) {
      if (!IS_LOGGED_IN) {
        window.location.href = 'logsign.php';
        return;
      }

      const qtyEl = document.getElementById('qty-' + id);
      const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;

      const btn = document.getElementById('btn-' + id);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adding...';
      }

      fetch('addcart.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'inv_id=' + encodeURIComponent(id) +
            '&name=' + encodeURIComponent(name) +
            '&price=' + encodeURIComponent(price) +
            '&qty=' + encodeURIComponent(qty) +
            '&image=' + encodeURIComponent(image || '')
        })
        .then(r => r.text())
        .then(raw => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          let data;
          try {
            data = JSON.parse(raw);
          } catch (e) {
            showToast('Server error — check PHP logs.', 'error');
            console.error('Non-JSON response:', raw);
            return;
          }

          if (data.success) {
            // Update full cart state from server response
            if (data.cart) {
              cartItemsJS = data.cart;
            }
            const badge = document.getElementById('cart-badge');
            if (badge) {
              badge.textContent = data.total_items;
              badge.style.display = data.total_items > 0 ? '' : 'none';
            }
            renderCart();
            showToast('✓ ' + name + ' added to cart!');
          } else if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            showToast(data.message || 'Error adding to cart.', 'error');
          }
        })
        .catch(err => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          showToast('Connection error. Try again.', 'error');
          console.error(err);
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) {
          e.preventDefault();
          t.scrollIntoView({
            behavior: 'smooth'
          });
        }
      });
    });

  // ── Three-dot menu toggle ──
  function toggleReviewMenu(cardId) {
    var menu = document.getElementById('menu-' + cardId);
    if (!menu) return;
    var isOpen = menu.classList.contains('open');
    // Close all open menus first
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) {
      m.classList.remove('open');
    });
    if (!isOpen) menu.classList.add('open');
  }

  // Close menus when clicking outside
  document.addEventListener('click', function() {
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) {
      m.classList.remove('open');
    });
  });

  // ── Delete own review ──
  function deleteMyReview(reviewId) {
    if (!reviewId) return;
    // Close any open dropdown
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) { m.classList.remove('open'); });
    if (!confirm('Delete your review? This cannot be undone.')) return;
    fetch('admin_action.php?delete_review=' + encodeURIComponent(reviewId))
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Review deleted.');
          setTimeout(() => location.reload(), 900);
        } else {
          alert(data.message || 'Could not delete review.');
        }
      }).catch(() => alert('Network error. Please try again.'));
  }

  // ── Edit review modal ──
  var _editReviewId   = 0;
  var _editRating     = 5;

  function openEditReview(reviewId, currentRating, currentComment) {
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) { m.classList.remove('open'); });
    _editReviewId = reviewId;
    _editRating   = currentRating || 5;

    var ta = document.getElementById('editReviewText');
    if (ta) {
      ta.value = currentComment || '';
      updateEditCharCount();
    }
    setEditStars(_editRating);
    document.getElementById('editReviewModalBg').classList.add('open');
    if (ta) setTimeout(function() { ta.focus(); }, 80);
  }

  function closeEditReview() {
    document.getElementById('editReviewModalBg').classList.remove('open');
  }

  function setEditStars(val) {
    _editRating = val;
    document.querySelectorAll('#editStars span').forEach(function(s) {
      s.classList.toggle('lit', parseInt(s.dataset.val) <= val);
    });
  }

  document.querySelectorAll('#editStars span').forEach(function(s) {
    s.addEventListener('click', function() { setEditStars(parseInt(this.dataset.val)); });
    s.addEventListener('mouseover', function() {
      document.querySelectorAll('#editStars span').forEach(function(x) {
        x.classList.toggle('lit', parseInt(x.dataset.val) <= parseInt(s.dataset.val));
      });
    });
  });
  var editStarsEl = document.getElementById('editStars');
  if (editStarsEl) {
    editStarsEl.addEventListener('mouseleave', function() { setEditStars(_editRating); });
  }

  function updateEditCharCount() {
    var ta    = document.getElementById('editReviewText');
    var cc    = document.getElementById('editCharCount');
    if (!ta || !cc) return;
    var len   = ta.value.length;
    cc.textContent = len + ' / 500';
    cc.classList.toggle('over', len > 500);
  }

  function saveEditReview() {
    var ta  = document.getElementById('editReviewText');
    var btn = document.getElementById('saveReviewBtn');
    if (!ta) return;
    var comment = ta.value.trim();
    if (!comment) { showToast('Review text cannot be empty.', 'error'); return; }
    if (comment.length > 500) { showToast('Review must be 500 characters or fewer.', 'error'); return; }

    btn.disabled = true;
    btn.textContent = 'Saving…';

    var body = 'edit_review=1&review_id=' + encodeURIComponent(_editReviewId) +
               '&rating='    + encodeURIComponent(_editRating) +
               '&comment='   + encodeURIComponent(comment);

    fetch('admin_action.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        if (data.success) {
          showToast('Review updated!');
          closeEditReview();
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message || 'Could not save review.', 'error');
        }
      }).catch(() => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        showToast('Network error. Try again.', 'error');
      });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditReview();
  });

  // ── Expand / collapse long review text ──
  function toggleReview(cardId) {
    var body = document.getElementById('body-' + cardId);
    var btn  = document.getElementById('btn-'  + cardId);
    if (!body || !btn) return;
    var isCollapsed = body.classList.contains('clamped');
    body.classList.toggle('clamped', !isCollapsed);
    btn.textContent = isCollapsed ? 'Show less' : 'Read more';
  }

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

// ── Review Edit Functions ──
let currentEditingReviewId = null;
let currentEditingRating = 0;

function openEditReview(reviewId, currentRating, currentComment) {
  currentEditingReviewId = reviewId;
  currentEditingRating = parseInt(currentRating) || 5;
  
  const modal = document.getElementById('editReviewModalBg');
  const textarea = document.getElementById('editReviewText');
  
  if (modal && textarea) {
    textarea.value = currentComment || '';
    updateEditStars(currentEditingRating);
    updateEditCharCount();
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
      textarea.focus();
      textarea.select();
    }, 100);
  }
}

function closeEditReview() {
  const modal = document.getElementById('editReviewModalBg');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentEditingReviewId = null;
    currentEditingRating = 0;
  }
}

function updateEditStars(rating) {
  currentEditingRating = rating;
  const stars = document.querySelectorAll('.review-edit-stars span');
  stars.forEach((star, index) => {
    if (index < rating) {
      star.classList.add('selected');
    } else {
      star.classList.remove('selected');
    }
  });
}

function updateEditCharCount() {
  const textarea = document.getElementById('editReviewText');
  const charCount = document.getElementById('editCharCount');
  
  if (textarea && charCount) {
    const length = textarea.value.length;
    charCount.textContent = length + ' / 500';
    
    if (length > 450) {
      charCount.style.color = '#dc2626';
    } else if (length > 400) {
      charCount.style.color = '#f59e0b';
    } else {
      charCount.style.color = '#999';
    }
  }
}

function saveEditReview() {
  if (!currentEditingReviewId) {
    alert('Error: No review selected');
    return;
  }
  
  const textarea = document.getElementById('editReviewText');
  const comment = textarea.value.trim();
  
  if (!comment || comment.length < 1 || comment.length > 500) {
    alert('Comment must be between 1 and 500 characters');
    return;
  }
  
  if (currentEditingRating < 1 || currentEditingRating > 5) {
    alert('Please select a rating between 1 and 5 stars');
    return;
  }
  
  const saveBtn = document.getElementById('saveReviewBtn');
  const originalText = saveBtn.textContent;
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';
  
  fetch('admin_action.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      edit_review: '1',
      review_id: currentEditingReviewId,
      rating: currentEditingRating,
      comment: comment
    }),
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Review updated successfully');
      closeEditReview();
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Could not update review'));
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error updating review. Please try again.');
    saveBtn.disabled = false;
    saveBtn.textContent = originalText;
  });
}

// Setup edit stars click handlers
document.addEventListener('DOMContentLoaded', function() {
  const editStars = document.querySelectorAll('.review-edit-stars span');
  editStars.forEach(star => {
    star.addEventListener('click', function() {
      const rating = this.getAttribute('data-val');
      updateEditStars(rating);
    });
  });
  
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeEditReview();
    }
  });
  
  const editModalBg = document.getElementById('editReviewModalBg');
  if (editModalBg) {
    editModalBg.addEventListener('click', function(e) {
      if (e.target === editModalBg) {
        closeEditReview();
      }
    });
  }
});

