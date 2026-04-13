/**
 * HackConcursos - app.js
 * Utilitários globais de JavaScript
 */

// ---- Sidebar mobile toggle ----
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
        sidebar.classList.remove('open');
      }
    });
  }

  // ---- Auto-dismiss flash messages ----
  const flash = document.getElementById('flash-msg');
  if (flash) {
    setTimeout(() => {
      flash.style.transition = 'opacity 0.4s';
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 400);
    }, 4000);
  }

  // ---- Animação de números (KPIs) ----
  const counterEls = document.querySelectorAll('[data-count]');
  counterEls.forEach(el => {
    const target = parseFloat(el.dataset.count);
    const decim  = el.dataset.decimals ? parseInt(el.dataset.decimals) : 0;
    const suffix = el.dataset.suffix || '';
    let start = 0;
    const duration = 1200;
    const step = (timestamp) => {
      if (!start) start = timestamp;
      const progress = Math.min((timestamp - start) / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = (target * ease).toFixed(decim) + suffix;
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });

  // ---- Tooltips ----
  const tooltipEls = document.querySelectorAll('[data-tooltip]');
  tooltipEls.forEach(el => {
    el.addEventListener('mouseenter', () => {
      // handled by CSS only
    });
  });

  // ---- Drag-over upload zone ----
  const uploadZone = document.querySelector('.upload-zone');
  if (uploadZone) {
    uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
    uploadZone.addEventListener('dragleave', ()=> uploadZone.classList.remove('drag-over'));
    uploadZone.addEventListener('drop', e => {
      e.preventDefault();
      uploadZone.classList.remove('drag-over');
      const files = e.dataTransfer.files;
      const inp = document.getElementById('pdf_file');
      if (inp && files.length) {
        inp.files = files;
        inp.dispatchEvent(new Event('change'));
      }
    });
    uploadZone.addEventListener('click', () => {
      const inp = document.getElementById('pdf_file');
      if (inp) inp.click();
    });
  }
});

// ---- Helpers globais ----
function showLoader(msg = 'Processando...') {
  let overlay = document.createElement('div');
  overlay.className = 'loader-overlay';
  overlay.id = 'global-loader';
  overlay.innerHTML = `<div style="text-align:center">
    <div class="loader-spinner"></div>
    <p style="margin-top:1rem;color:var(--text-secondary);font-size:0.9rem;">${msg}</p>
  </div>`;
  document.body.appendChild(overlay);
}
function hideLoader() {
  const el = document.getElementById('global-loader');
  if (el) el.remove();
}

function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
}
window.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
  }
});
