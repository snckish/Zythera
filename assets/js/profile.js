/* ── profile.php scripts ── */


document.addEventListener('DOMContentLoaded', function () {
                if (typeof showToast === 'function') showToast('Profile updated successfully.');
                // Drop ?updated=1 from the URL so refreshing doesn't re-show the toast
                const url = new URL(window.location.href);
                url.searchParams.delete('updated');
                window.history.replaceState({}, '', url.pathname + (url.search || '') + url.hash);
            });

// PROFILE_PROVINCE_CITIES, PROFILE_CITY_ZIP_CODES, PROFILE_CITY_BARANGAYS, PROFILE_ALL_CITIES
    // cartItemsJS, stockMapJS, PROFILE_USER_ROLE, PROFILE_IS_LOGGED_IN
    // — all seeded inline by profile.php before this file loads
        let savedProfileBarangay = '';

        function filterAddressCities() {
            const province = document.getElementById('addr_province')?.value || '';
            const citySelect = document.getElementById('addr_city');
            if (!citySelect) return;
            const previous = citySelect.value;
            const list = (PROFILE_PROVINCE_CITIES[province] && PROFILE_PROVINCE_CITIES[province].length)
                ? PROFILE_PROVINCE_CITIES[province]
                : PROFILE_ALL_CITIES;
            citySelect.innerHTML = '<option value="">Select City / Municipality</option>';
            list.forEach(function(city) {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if (city === previous) opt.selected = true;
                citySelect.appendChild(opt);
            });
            if (previous && !list.includes(previous)) citySelect.value = '';
            updateAddressZip();
            filterAddressBarangays();
        }

        function updateAddressZip() {
            const city = document.getElementById('addr_city')?.value || '';
            const zip = document.getElementById('addr_zip');
            if (zip) zip.value = PROFILE_CITY_ZIP_CODES[city] || '';
        }

        function filterAddressBarangays() {
            const city = document.getElementById('addr_city')?.value || '';
            const barangaySelect = document.getElementById('addr_barangay');
            if (!barangaySelect) return;
            const previous = barangaySelect.value || savedProfileBarangay;
            const list = (PROFILE_CITY_BARANGAYS[city] && PROFILE_CITY_BARANGAYS[city].length)
                ? [...PROFILE_CITY_BARANGAYS[city]].sort()
                : ['Poblacion', ...Array.from({length:30}, (_, i) => 'Barangay ' + (i + 1))];
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            list.forEach(function(barangay) {
                const opt = document.createElement('option');
                opt.value = barangay;
                opt.textContent = barangay;
                if (barangay === previous) opt.selected = true;
                barangaySelect.appendChild(opt);
            });
            if (previous && barangaySelect.value !== previous) {
                const opt = document.createElement('option');
                opt.value = previous;
                opt.textContent = previous;
                opt.selected = true;
                barangaySelect.appendChild(opt);
            }
        }

        function filterOrders(tab) {
            // Update active tab
            document.querySelectorAll('.order-tab').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });

            const items   = document.querySelectorAll('#orderList .order-link');
            const emptyMsg = document.getElementById('emptyTabMsg');
            const emptyTxt = document.getElementById('emptyTabText');
            const orderList = document.getElementById('orderList');

            if (!items.length) return;

            let visible = 0;
            items.forEach(function(link) {
                const status = (link.dataset.status || '').trim();
                const show   = tab === 'All' || status.toLowerCase() === tab.toLowerCase();
                link.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (emptyMsg && orderList) {
                if (visible === 0) {
                    orderList.style.display = 'none';
                    emptyMsg.style.display  = '';
                    if (emptyTxt) emptyTxt.textContent = 'No ' + tab + ' orders yet.';
                } else {
                    orderList.style.display = '';
                    emptyMsg.style.display  = 'none';
                }
            }
        }

        function showSettingsTab(tab) {
            document.querySelectorAll('.settings-tab-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.settingsTab === tab);
            });
            document.querySelectorAll('.settings-pane').forEach(function(pane) {
                pane.classList.toggle('active', pane.dataset.settingsPane === tab);
            });
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }

        document.querySelectorAll('.settings-tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                showSettingsTab(this.dataset.settingsTab);
            });
        });

        function openAddressForm() {
            const wrap = document.getElementById('addressFormWrap');
            const form = document.getElementById('addressForm');
            if (form) form.reset();
            savedProfileBarangay = '';
            document.getElementById('address_id').value = '';
            filterAddressCities();
            if (wrap) wrap.style.display = 'block';
            wrap?.scrollIntoView({behavior:'smooth', block:'start'});
        }

        function closeAddressForm() {
            const wrap = document.getElementById('addressFormWrap');
            if (wrap) wrap.style.display = 'none';
        }

        function editAddress(btn) {
            const data = JSON.parse(btn.dataset.address || '{}');
            document.getElementById('address_id').value = data.address_id || '';
            document.getElementById('address_label').value = data.address_label || 'Home';
            document.getElementById('addr_phone').value = data.phone_num || '';
            savedProfileBarangay = data.barangay || '';
            document.getElementById('addr_province').value = data.province || '';
            filterAddressCities();
            document.getElementById('addr_city').value = data.city_municipality || '';
            updateAddressZip();
            filterAddressBarangays();
            document.getElementById('addr_barangay').value = data.barangay || '';
            if (data.barangay && document.getElementById('addr_barangay').value !== data.barangay) {
                const opt = document.createElement('option');
                opt.value = data.barangay;
                opt.textContent = data.barangay;
                opt.selected = true;
                document.getElementById('addr_barangay').appendChild(opt);
            }
            document.getElementById('addr_zip').value = data.zip_code || PROFILE_CITY_ZIP_CODES[data.city_municipality] || '';
            document.getElementById('addr_street').value = data.st_address || '';
            document.getElementById('addr_default').checked = Number(data.is_default || 0) === 1;
            const wrap = document.getElementById('addressFormWrap');
            if (wrap) wrap.style.display = 'block';
            wrap?.scrollIntoView({behavior:'smooth', block:'start'});
        }

        function togglePw(inputId = 'newPwField', eyeId = 'newPwEye') {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(eyeId);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            if (eye) eye.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        }

        function updateProfileNavTime() {
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
        setInterval(updateProfileNavTime, 1000);
        updateProfileNavTime();

// ── Cart state seeded from PHP session + live DB via fetch ──
            // cartItemsJS — seeded inline by profile.php
            let selectedCartIds = new Set(JSON.parse(localStorage.getItem('zythera_selected_cart') || '[]').map(String));
            // Stock map from PHP inventory (inv_id => stock)
            // stockMapJS — seeded inline by profile.php

            // On page load, always sync cart from DB so badge is accurate
            (function syncCartOnLoad() {
                if (PROFILE_USER_ROLE !== 'admin') {
                fetch('getcart.php', { credentials: 'same-origin' })
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
                document.querySelectorAll('.cart-select-checkbox').forEach(cb => { cb.checked = checked; });
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

            // ── Re-render cart panel items + subtotal + header count ──────
            function renderCart() {
                const container = document.getElementById('cartItems');
                const footer = document.getElementById('cartFooter');
                const subEl = document.getElementById('cartSubtotal');
                const countEl = document.getElementById('cartItemCount');

                let subtotal = 0, totalQty = 0, distinctCount = cartItemsJS.length;

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
                    const stock = stockMapJS[item.inv_id] ?? 99;
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
                if (countEl) countEl.textContent = distinctCount === 1 ? '1 item in cart' : distinctCount + ' items in cart';
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.textContent = distinctCount;
                    badge.style.display = distinctCount > 0 ? '' : 'none';
                }
                updateSelectAllUI();
            }

            // ── Qty stepper in cart sidebar → update_cart.php ────────────
            function cartQty(itemId, action) {
                if (action === 'plus') {
                    const item = cartItemsJS.find(i => String(i.inv_id) === String(itemId));
                    const max = stockMapJS[itemId] ?? 9999;
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
                    cartItemsJS = data.cart || [];
                    renderCart();
                    try {
                        const bc = new BroadcastChannel('zythera_cart');
                        bc.postMessage({ type: 'cart_updated', cart: cartItemsJS });
                        bc.close();
                    } catch (e) { /* BroadcastChannel not supported — no-op */ }
                })
                .catch(() => showToast('Could not update cart. Try again.', 'error'));
            }

            function escHtml(s) {
                return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function showToast(msg, type = 'success') {
                const t = document.getElementById('toast-msg');
                if (!t) return;
                t.textContent = msg;
                t.className = 'toast-fixed show' + (type === 'error' ? ' error' : '');
                setTimeout(() => t.classList.remove('show'), 3500);
            }

            // Live-sync cart if changed in another tab (e.g. checkout page)
            try {
                const cartBc = new BroadcastChannel('zythera_cart');
                cartBc.onmessage = (e) => {
                    if (e.data && e.data.type === 'cart_updated') {
                        cartItemsJS = e.data.cart || [];
                        renderCart();
                    }
                };
            } catch (e) { /* BroadcastChannel not supported — no-op */ }

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
                if (e.key === 'Escape') closeLogoutModal();
            });

            // Close modal on outside click
            document.addEventListener('click', function(e) {
                const overlay = document.getElementById('logoutModalOverlay');
                if (overlay && e.target === overlay) closeLogoutModal(e);
            });
