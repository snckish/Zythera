/* ── checkout.php scripts ── */

// ── Data from PHP ─────────────────────────────────────────────
// PROVINCE_CITIES, CITY_ZIP_CODES, CITY_BARANGAYS, ALL_CITIES, SAVED_BARANGAY — seeded inline by checkout.php

// ── Province → City filter ────────────────────────────────────
function filterCities() {
  const provinceEl = document.getElementById('province');
  const citySelect = document.getElementById('city');
  if (!provinceEl || !citySelect || citySelect.tagName !== 'SELECT') return;
  const province   = provinceEl.value;
  const savedCity  = citySelect.value;

  const list = (PROVINCE_CITIES[province] && PROVINCE_CITIES[province].length)
    ? PROVINCE_CITIES[province]
    : ALL_CITIES;

  citySelect.innerHTML = '<option value="">Select City / Municipality</option>';
  list.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c; opt.textContent = c;
    if (c === savedCity) opt.selected = true;
    citySelect.appendChild(opt);
  });

  if (savedCity && !list.includes(savedCity)) citySelect.value = '';
  updateZipCode();
  filterBarangays();
}

// ── City → ZIP auto-fill ──────────────────────────────────────
function updateZipCode() {
  const city = document.getElementById('city')?.value || '';
  const zip = document.getElementById('zip');
  if (zip) zip.value = CITY_ZIP_CODES[city] || zip.value || '';
}

// ── City → Barangay filter ────────────────────────────────────
function filterBarangays() {
  const city     = document.getElementById('city')?.value || '';
  const sel      = document.getElementById('barangay');
  if (!sel || sel.tagName !== 'SELECT') return;
  const previous = sel.value;

  let list = (CITY_BARANGAYS[city] && CITY_BARANGAYS[city].length)
    ? [...CITY_BARANGAYS[city]].sort()
    : ['Poblacion', ...Array.from({length:30}, (_,i) => 'Barangay ' + (i+1))];

  sel.innerHTML = '<option value="">Select Barangay</option>';
  list.forEach(b => {
    const opt = document.createElement('option');
    opt.value = b; opt.textContent = b;
    if (b === previous || b === SAVED_BARANGAY) opt.selected = true;
    sel.appendChild(opt);
  });
}

document.querySelectorAll('.saved-address-option').forEach(label => {
  label.addEventListener('click', () => {
    document.querySelectorAll('.saved-address-option').forEach(el => el.classList.remove('selected'));
    label.classList.add('selected');
    const radio = label.querySelector('input[type=radio]');
    if (radio) radio.checked = true;
    const data = JSON.parse(label.dataset.address || '{}');
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('province').value = data.province || '';
    filterCities();
    document.getElementById('city').value = data.city || '';
    updateZipCode();
    filterBarangays();
    const brgy = document.getElementById('barangay');
    if (brgy) {
      brgy.value = data.barangay || '';
      if (data.barangay && brgy.value !== data.barangay) {
        const opt = document.createElement('option');
        opt.value = data.barangay;
        opt.textContent = data.barangay;
        opt.selected = true;
        brgy.appendChild(opt);
      }
    }
    document.getElementById('address').value = data.address || '';
    document.getElementById('zip').value = data.zip || CITY_ZIP_CODES[data.city] || '';
  });
});

// ── Payment panel toggle ──────────────────────────────────────
const PAY_GROUPS = ['gcash','maya','bank'];

function showPay(group) {
  PAY_GROUPS.forEach(g => {
    document.getElementById('lbl-' + g)?.classList.toggle('selected', g === group);
    document.getElementById('panel-' + g)?.classList.toggle('show',   g === group);
  });
  const proofBlock = document.getElementById('proof-of-payment-block');
  const slot       = document.getElementById('proof-slot-' + group);
  if (proofBlock && slot) {
    slot.appendChild(proofBlock);
    proofBlock.style.display = 'block';
  } else if (proofBlock) {
    proofBlock.style.display = 'none';
  }
}

function togglePay(group) {
  const radio = document.getElementById('radio-' + group);
  if (radio) { radio.checked = true; showPay(group); }
}

function handleProofFile(input) {
  const nameEl = document.getElementById('proof-file-name');
  const areaEl = document.getElementById('proof-upload-area');
  if (input.files && input.files[0]) {
    if (nameEl) nameEl.textContent = '✓ ' + input.files[0].name;
    if (areaEl) { areaEl.style.borderColor = 'var(--green)'; areaEl.style.background = '#f0f7f0'; }
  } else {
    if (nameEl) nameEl.textContent = '';
    if (areaEl) { areaEl.style.borderColor = '#a7c7a7'; areaEl.style.background = '#fff'; }
  }
}

// ── Card formatters ───────────────────────────────────────────
function fmtCard(el)   { let v=el.value.replace(/\D/g,'').slice(0,16); el.value=v.replace(/(\d{4})(?=\d)/g,'$1 '); }
function fmtExpiry(el) { let v=el.value.replace(/\D/g,'').slice(0,4); if(v.length>=3) v=v.slice(0,2)+'/'+v.slice(2); el.value=v; }

function setError(input, errorEl, message) {
  if (input) input.classList.toggle('is-invalid', !!message);
  if (errorEl) { errorEl.textContent = message || '\u00A0'; errorEl.style.display = message ? 'block' : 'none'; }
}

function resetCardErrors() {
  ['card_name','card_number','card_expiry','card_cvv'].forEach(id => {
    setError(document.getElementById(id), document.getElementById(id.replace('card_','card')+'Error'), '');
  });
  setError(document.getElementById('card_name'),   document.getElementById('cardNameError'),   '');
  setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), '');
  setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), '');
  setError(document.getElementById('card_cvv'),    document.getElementById('cardCvvError'),    '');
}

// ── Live validation ───────────────────────────────────────────
(function(){
  const rules = [
    { id:'full_name', errId:'fullNameError', validate: v => {
      if (!v) return ''; if (!/^[\p{L} .'\-]*$/u.test(v)) return 'Invalid characters.';
      if (v.length<2) return 'Name too short.'; return '';
    }},
    { id:'phone', errId:'phoneError', validate: v => {
      if (!v) return ''; if (!/^[0-9]*$/.test(v)) return 'Digits only.';
      if (v.length>11) return 'Max 11 digits.'; if (v.length<10) return 'Min 10 digits.'; return '';
    }},
  ];
  rules.forEach(({id, errId, validate}) => {
    const inp = document.getElementById(id);
    const err = document.getElementById(errId);
    if (inp && err) inp.addEventListener('input', function(){
      const msg = validate((this.value||'').trim());
      err.textContent = msg || '\u00A0'; err.style.display = msg ? 'block' : 'none';
      this.classList.toggle('is-invalid', !!msg);
    });
  });
})();

// ── Submit validation ─────────────────────────────────────────
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
  const btn    = this.querySelector('.btn-place');
  const errs   = [];

  // Check if a saved address radio is selected (has a value)
  const savedAddrRadio = this.querySelector('input[name=saved_address_id]:checked');
  const usingSavedAddress = savedAddrRadio && savedAddrRadio.value !== '';

  const phone  = (document.getElementById('phone')?.value||'').trim();
  const prov   = (document.getElementById('province')?.value||'').trim();
  const city   = (document.getElementById('city')?.value||'').trim();
  const brgy   = (document.getElementById('barangay')?.value||'').trim();
  const addr   = (document.getElementById('address')?.value||'').trim();
  const zip    = (document.getElementById('zip')?.value||'').trim();
  const payVal = this.querySelector('input[name=pay_method]:checked')?.value||'';

  // Only validate address fields if NOT using a saved address
  if (!usingSavedAddress) {
    if (!prov)  errs.push('Please select a province.');
    if (!city)  errs.push('Please select a city.');
    if (!brgy)  errs.push('Please select a barangay.');
    if (!addr)  errs.push('Please enter your house / street address.');
    if (!zip)   errs.push('Postal code could not be auto-filled. Please select a valid city.');
    if (!/^[0-9]{10,11}$/.test(phone)) errs.push('Phone must be 10–11 digits.');
  }
  if (!payVal) errs.push('Please select a payment method.');

  const eWalletMethods = ['GCash','Maya','Bank Transfer'];
  if (eWalletMethods.includes(payVal)) {
    const proofInput = document.getElementById('pay_proof');
    const refInput   = document.getElementById('ref_no');
    if (!proofInput?.files?.length) errs.push('Please attach your proof of payment.');
    if (!refInput?.value.trim())    errs.push('Please enter your reference / transaction number.');
  }

  if (payVal === 'Bank Transfer') {
    const cardName = (document.getElementById('card_name')?.value||'').trim();
    const cardNum  = (document.getElementById('card_number')?.value||'').replace(/\s/g,'');
    const expiry   = (document.getElementById('card_expiry')?.value||'').trim();
    const cvv      = (document.getElementById('card_cvv')?.value||'').trim();
    resetCardErrors();
    if (!cardName) { setError(document.getElementById('card_name'),   document.getElementById('cardNameError'),   'Please enter the name on card.'); errs.push('Please enter the name on card.'); }
    if (!/^\d{13,16}$/.test(cardNum)) { setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), 'Please enter a valid card number.'); errs.push('Please enter a valid card number.'); }
    if (!/^\d{2}\/\d{2}$/.test(expiry)||Number(expiry.slice(0,2))<1||Number(expiry.slice(0,2))>12) { setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), 'Please enter a valid expiry (MM/YY).'); errs.push('Please enter a valid expiry (MM/YY).'); }
    if (!/^\d{3,4}$/.test(cvv)) { setError(document.getElementById('card_cvv'), document.getElementById('cardCvvError'), 'Please enter a valid CVV.'); errs.push('Please enter a valid CVV.'); }
  } else {
    resetCardErrors();
  }

  if (errs.length) { e.preventDefault(); if (btn) btn.disabled=false; alert(errs.join('\n')); return false; }
  if (btn) { btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-2"></i>Placing Order...'; }
});

// ── Init on DOM ready ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // Restore province → city → barangay chain on page reload (after POST error)
  filterCities();

  // Restore payment panel
  PAY_GROUPS.forEach(g => {
    if (document.getElementById('radio-' + g)?.checked) showPay(g);
  });

  // Restore saved barangay after filterBarangays populates the list
  if (SAVED_BARANGAY) {
    const sel = document.getElementById('barangay');
    if (sel && !sel.value) {
      const opt = document.createElement('option');
      opt.value = SAVED_BARANGAY; opt.textContent = SAVED_BARANGAY; opt.selected = true;
      sel.appendChild(opt);
    }
  }
});

// ── Cart live sync (BroadcastChannel + polling) ───────────────
// checkoutCart, CHECKOUT_SELECTED_IDS — seeded inline by checkout.php
const SHIPPING_FEE = 150;

function numFmt(n)    { return Number(n).toLocaleString('en-PH',{minimumFractionDigits:0,maximumFractionDigits:0}); }
function escHtml(s)   { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function rebuildOrderSummary(cart) {
  cart = (cart || []).filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id)));
  const container  = document.querySelector('.checkout-card div[style*="overflow-y:auto"]');
  const subtotalEl = document.querySelector('.order-total-row:nth-child(1) span:last-child');
  const shippingEl = document.querySelector('.order-total-row:nth-child(2) span:last-child');
  const totalEl    = document.querySelector('.order-total-row.grand span:last-child');
  if (!container) return;
  if (!cart || !cart.length) { window.location.href='website.php'; return; }
  let html='', subtotal=0;
  cart.forEach(item => {
    const qty=Number(item.qty)||1, price=Number(item.price)||0, line=price*qty;
    subtotal+=line;
    const img=item.image||'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';
    html+=`<div class="order-item"><img src="${escHtml(img)}" alt="" onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'"><div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:.85rem;color:var(--deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.name||'')}</div><div style="font-size:.76rem;color:#999;">₱${numFmt(price)} × ${qty}</div></div><div style="font-weight:700;color:var(--green);font-size:.88rem;white-space:nowrap;">₱${numFmt(line)}</div></div>`;
  });
  container.innerHTML=html;
  const shipping=subtotal>0?SHIPPING_FEE:0, total=subtotal+shipping;
  if(subtotalEl) subtotalEl.textContent='₱'+numFmt(subtotal);
  if(shippingEl) shippingEl.textContent='₱'+numFmt(shipping);
  if(totalEl)    totalEl.textContent='₱'+numFmt(total);
}

try {
  const bc=new BroadcastChannel('zythera_cart');
  bc.addEventListener('message',e=>{ if(e.data?.type==='cart_updated'&&Array.isArray(e.data.cart)){ checkoutCart=e.data.cart.filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id))); rebuildOrderSummary(checkoutCart); } });
} catch(_){}

setInterval(()=>{
  if(document.hidden) return;
  fetch('getcart.php',{credentials:'same-origin'}).then(r=>r.json()).then(data=>{
    if(data.success&&Array.isArray(data.cart)){
      const sig=a=>a.map(i=>i.inv_id+':'+i.qty).join(',');
      const selectedCart = data.cart.filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id)));
      if(sig(selectedCart)!==sig(checkoutCart)){ checkoutCart=selectedCart; rebuildOrderSummary(checkoutCart); }
    }
  }).catch(()=>{});
},5000);

// ── Logout modal ──────────────────────────────────────────────
function openLogoutModal()  { const o=document.getElementById('logoutModalOverlay'); if(o){o.classList.add('active');document.body.style.overflow='hidden';} }
function closeLogoutModal() { const o=document.getElementById('logoutModalOverlay'); if(o){o.classList.remove('active');document.body.style.overflow='';} }
function performLogout()    { const b=document.querySelector('.logout-confirm-btn'); if(b){b.disabled=true;b.textContent='Logging out...';} window.location.href='logout.php'; }
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLogoutModal(); });
document.getElementById('logoutModalOverlay')?.addEventListener('click',e=>{ if(e.target.id==='logoutModalOverlay') closeLogoutModal(); });
