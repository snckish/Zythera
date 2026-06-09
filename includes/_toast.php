<?php
// Usage: set $toastMessage (string) and optional $toastType ('success'|'error') before including this file.
if (empty($toastMessage)) return;
$toastType = $toastType ?? 'success';
$autoMs = isset($toastAutoMs) ? (int)$toastAutoMs : 3500;
$escMsg = htmlspecialchars($toastMessage, ENT_QUOTES | ENT_SUBSTITUTE);
?>
<style>
.toast-fixed{position:fixed;bottom:24px;right:24px;background:var(--green,#2d5a2d);color:#fff;padding:14px 22px;border-radius:12px;font-size:.86rem;z-index:9999;opacity:0;transform:translateY(10px);transition:.3s;pointer-events:none;max-width:320px;box-shadow:0 6px 24px rgba(0,0,0,.2)}
.toast-fixed.show{opacity:1;transform:translateY(0);} 
.toast-fixed.error{background:#dc2626}
</style>
<script>
(function(){
  var msg = <?= json_encode($escMsg) ?>;
  var type = <?= json_encode($toastType) ?>;
  var autoMs = <?= $autoMs ?>;
  // prefer an existing toast container with id `toast-msg` (used by website.js), else create one
  var t = document.getElementById('toast-msg');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast-msg';
    t.className = 'toast-fixed';
    t.setAttribute('role','status');
    t.setAttribute('aria-live','polite');
    document.body.appendChild(t);
  }
  if (type === 'error') t.classList.add('error'); else t.classList.remove('error');
  t.innerHTML = msg;
  requestAnimationFrame(function(){ t.classList.add('show'); });
  setTimeout(function(){ t.classList.remove('show'); }, autoMs);
  setTimeout(function(){ if(t && t.parentNode) t.parentNode.removeChild(t); }, autoMs + 500);
})();
</script>
