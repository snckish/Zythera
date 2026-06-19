/* ── about.php scripts ── */


function openLogoutModal() {
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeLogoutModal(event) {
      if (event && event.target.id !== 'logoutModalOverlay') return;
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    function performLogout() {
      const confirmBtn = document.querySelector('.logout-btn-confirm');
      if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Logging out...';
      }
      window.location.href = 'logout.php';
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeLogoutModal();
    });

    document.addEventListener('click', function(e) {
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay && e.target === overlay) closeLogoutModal(e);
    });

    // ── Live time update for nav-user-capsule ──
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
