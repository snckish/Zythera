/**
 * ZYTHERA — Shared Dark Mode
 * Single source of truth. Loaded on every page (non-defer).
 * logsign.php is the only page with a toggle button.
 * All other pages just read the saved preference and apply it.
 */
(function () {

  function isDark() {
    return localStorage.getItem('zythera_dark') === '1';
  }

  function savePref(dark) {
    var val = dark ? '1' : '0';
    localStorage.setItem('zythera_dark', val);
    // Also write a cookie so PHP can read it server-side if needed
    var age = dark ? 60 * 60 * 24 * 365 : 0;
    document.cookie = 'zythera_dark=' + val + ';path=/;max-age=' + age;
  }

  function applyDark(dark) {
    // Toggle body.dark for CSS targeting
    document.body && document.body.classList.toggle('dark', dark);
    // Toggle html.zd for anti-flash (set before body exists)
    document.documentElement.classList.toggle('zd', dark);
    // Update the toggle button text (only exists on logsign.php)
    var btn = document.getElementById('darkToggle');
    if (btn) btn.textContent = dark ? 'Light Mode' : 'Dark Mode';
  }

  // Apply IMMEDIATELY (before paint) to avoid flash of light mode
  // This runs synchronously as the script loads
  var currentDark = isDark();
  if (currentDark && document.body) {
    document.body.classList.add('dark');
  }

  // Also apply on DOMContentLoaded to catch cases where body wasn't ready
  document.addEventListener('DOMContentLoaded', function () {
    applyDark(isDark());
  });

  // Public toggle function — called by logsign.php button onclick
  window.toggleDark = function () {
    var dark = !isDark();
    savePref(dark);
    applyDark(dark);
  };

  // Expose for external use
  window.__zythera_setDark = function (dark) {
    savePref(dark);
    applyDark(dark);
  };

})();