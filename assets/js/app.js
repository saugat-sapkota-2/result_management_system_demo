/* =====================================================================
   RMS -- Vanilla JS helpers
   ===================================================================== */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Sidebar toggle (mobile)
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    function closeSidebar() {
      if (!sidebar) return;
      sidebar.classList.remove('open');
      if (backdrop) backdrop.classList.remove('show');
    }
    if (toggle) toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show');
    });
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function (al) {
      setTimeout(function () {
        var bs = window.bootstrap && bootstrap.Alert;
        if (bs && bs.getOrCreateInstance) bs.getOrCreateInstance(al).close();
      }, 6000);
    });

    // Count characters in textareas
    document.querySelectorAll('textarea[data-maxlength]').forEach(function (ta) {
      var counter = document.createElement('small');
      counter.className = 'form-text';
      ta.parentNode.appendChild(counter);
      function upd() { counter.textContent = ta.value.length + ' / ' + ta.dataset.maxlength; }
      ta.addEventListener('input', upd); upd();
    });

    // Bikram Sambat date inputs (YYYY/MM/DD mask)
    document.querySelectorAll('.bs-date').forEach(function (inp) {
      inp.addEventListener('input', function () {
        var v = inp.value.replace(/[^\d\/]/g, '');
        v = v.replace(/\//g, '').replace(/^(\d{4})(\d{0,2})(\d{0,2})/, function (m, y, mo, d) {
          return d ? y + '/' + mo + '/' + d : (mo ? y + '/' + mo : y);
        });
        if (v.length > 10) v = v.substring(0, 10);
        inp.value = v;
      });
    });
  });

  // Mark entry page: compute totals from component rows
  window.markTotals = function () {
    var internal = 0, external = 0, failed = 0;
    document.querySelectorAll('tr[data-row]').forEach(function (tr) {
      var cat = tr.dataset.cat;
      var inputs = tr.querySelectorAll('.marks-input');
      inputs.forEach(function (inp) {
        if (!inp.value) { return; }
        var v = parseFloat(inp.value);
        var max = parseFloat(inp.dataset.max || '0');
        if (isNaN(v) || v < 0 || v > max) { failed++; return; }
        if (cat === 'internal') internal += v; else external += v;
      });
    });
    if (failed > 0) { return null; }
    return { internal: internal, external: external, total: internal + external };
  };
})();

window.confirmDelete = function (msg) {
  return window.confirm(msg || 'Are you sure you want to delete this record? This action cannot be undone.');
};