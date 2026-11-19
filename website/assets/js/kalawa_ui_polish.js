/* Buka rincian saat cetak, lalu kembalikan ke keadaan sebelumnya. */
(() => {
  'use strict';
  const openedForPrint = new Set();
  window.addEventListener('beforeprint', () => {
    document.querySelectorAll('details[data-ks-print-details]').forEach((detail) => {
      if (!detail.open) {
        openedForPrint.add(detail);
        detail.open = true;
      }
    });
  });
  window.addEventListener('afterprint', () => {
    openedForPrint.forEach((detail) => { detail.open = false; });
    openedForPrint.clear();
  });
})();
