/**
 * ZYTHERA — Shared Dark Mode
 * Single source of truth. Loaded on every page (non-defer).
 * website.php and logsign.php both have a toggle button.
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

  function updateToggleUI(dark) {
    // logsign.php text-only toggle
    var btn = document.getElementById('darkToggle');
    if (btn && !btn.querySelector('#iconSun')) {
      // text-only version (logsign)
      btn.textContent = dark ? 'Light Mode' : 'Dark Mode';
    }
    // website.php icon toggle
    var sun  = document.getElementById('iconSun');
    var moon = document.getElementById('iconMoon');
    var lbl  = document.getElementById('darkToggleLabel');
    if (sun)  sun.style.display  = dark ? 'block' : 'none';
    if (moon) moon.style.display = dark ? 'none'  : 'block';
    if (lbl)  lbl.textContent    = dark ? 'Light'  : 'Dark';
  }

  function applyDark(dark) {
    // Toggle html.zd for anti-flash and CSS targeting
    document.documentElement.classList.toggle('zd', dark);
    // Toggle body.dark for page theme
    document.body && document.body.classList.toggle('dark', dark);
    updateToggleUI(dark);
    // Fix inline-colored nav links: remove inline green color in dark mode
    try {
      var navs = document.querySelectorAll('.nav-link');
      navs.forEach(function (n) {
        var s = n.getAttribute('style');
        if (!s) return;
        if (s.indexOf('var(--green)') !== -1 || s.indexOf('#1a2e1a') !== -1) {
          if (dark) {
            // store original style so we can restore later
            if (n.dataset.__origStyle === undefined) n.dataset.__origStyle = s;
            // remove only the color property from inline styles
            n.style.removeProperty('color');
          } else {
            // restore original inline style if present
            if (n.dataset.__origStyle !== undefined) {
              n.setAttribute('style', n.dataset.__origStyle);
              delete n.dataset.__origStyle;
            }
          }
        }
      });
    } catch (e) {
      // swallow errors to avoid breaking pages
      console && console.warn && console.warn('dark-mode nav fix failed', e);
    }
  }

  // Apply IMMEDIATELY (before paint) to avoid flash of light mode
  // This runs synchronously as the script loads
  var currentDark = isDark();
  if (currentDark) {
    document.documentElement.classList.add('zd');
    document.documentElement.style.background = '#111e11';
    if (document.body) document.body.classList.add('dark');
  }

  // Also apply on DOMContentLoaded to catch cases where body wasn't ready
  document.addEventListener('DOMContentLoaded', function () {
    applyDark(isDark());
    if (!isDark()) {
      document.documentElement.style.background = '';
    }
  });

  // Public toggle function — called by toggle buttons
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