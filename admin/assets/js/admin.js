/**
 * Smart Auto QR Safety System — Admin Panel JS
 */

// Mobile sidebar overlay close
document.addEventListener('DOMContentLoaded', function () {

  // Add overlay div
  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  overlay.addEventListener('click', function () {
    document.getElementById('sidebar').classList.remove('open');
  });

  // Auto-dismiss alerts after 4 seconds
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity    = '0';
      setTimeout(function () { el.remove(); }, 500);
    }, 4000);
  });

  // Confirm dangerous actions
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });
});
