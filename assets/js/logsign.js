/* ── logsign.php scripts ── */

document.addEventListener('DOMContentLoaded',()=>{ if(LOGSIGN_TOAST_MSG) showToast(LOGSIGN_TOAST_MSG, LOGSIGN_TOAST_TYPE); });

function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  const show  = input.type === 'password';
  input.type  = show ? 'text' : 'password';
  icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

function switchTab(tab) {
  document.getElementById('loginForm').classList.toggle('active', tab==='login');
  document.getElementById('signupForm').classList.toggle('active',tab==='signup');
}

function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast ' + type + ' show';
  setTimeout(()=>t.classList.remove('show'), 5000);
}

// ── Session active indicator ─────────────────────────────────
// Cookie lasts 12 hours of inactivity — just show it as full/active.
(function() {
  const fill = document.getElementById('sessionBarFill');
  if (!fill) return;

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  if (getCookie('zythera_user')) {
    fill.style.width = '100%';
    fill.style.background = '#2d5a2d';
  } else {
    fill.style.width = '0%';
  }
})();
