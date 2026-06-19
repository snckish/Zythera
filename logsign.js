/* ── logsign.php scripts ── */

function toggleDark(){
  var dark = !document.body.classList.contains('dark');
  document.documentElement.classList.toggle('zd', dark);
  document.body.classList.toggle('dark', dark);
  localStorage.setItem('zythera_dark', dark ? '1' : '0');
  var age = dark ? 60*60*24*365 : 0;
  document.cookie = 'zythera_dark=' + (dark ? '1' : '0') + ';path=/;max-age=' + age;
  document.documentElement.style.background = dark ? '#111e11' : '#ffffff';
  if (!dark) document.documentElement.style.background = '';
  var btn = document.getElementById('darkToggle');
  if(btn) btn.textContent = dark ? 'Light Mode' : 'Dark Mode';
}

document.addEventListener('DOMContentLoaded',()=>{ if(LOGSIGN_TOAST_MSG) showToast(LOGSIGN_TOAST_MSG, LOGSIGN_TOAST_TYPE); });

function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  const show  = input.type === 'password';
  input.type  = show ? 'text' : 'password';
  icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Dark mode handled by inline script above
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
