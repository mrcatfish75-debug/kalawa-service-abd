/* Kalawa Fase 1 R02: arahkan fokus ke input bermasalah; tidak menyimpan data di browser. */
(() => {
  'use strict';
  const forms = document.querySelectorAll('form');
  const scrollFocus = (field) => {
    if (!field || typeof field.focus !== 'function') return;
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    field.focus({ preventScroll: true });
  };
  // Kesalahan dari validasi backend: fokus pada kolom pertama, dengan nilai input tetap.
  const firstServerError = document.querySelector('[data-server-error="true"]');
  if (firstServerError) {
    window.addEventListener('load', () => scrollFocus(firstServerError), { once: true });
  }
  const phone = document.querySelector('#pelanggan-form #no_telepon');
  if (phone) {
    const checkPhone = () => {
      const value = phone.value.trim();
      const digitCount = (value.match(/[0-9]/g) || []).length;
      phone.setCustomValidity(value && digitCount < 7 ? 'Nomor telepon harus memiliki minimal 7 angka.' : '');
    };
    phone.addEventListener('input', checkPhone);
    checkPhone();
  }
  for (const form of forms) {
    let focusScheduled = false;
    form.addEventListener('invalid', (event) => {
      const field = event.target;
      if (!(field instanceof HTMLElement)) return;
      field.setAttribute('aria-invalid', 'true');
      if (!focusScheduled) {
        focusScheduled = true;
        window.requestAnimationFrame(() => {
          scrollFocus(field);
          focusScheduled = false;
        });
      }
    }, true);
    form.addEventListener('input', (event) => {
      const field = event.target;
      if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
        if (field.validity.valid && !field.dataset.serverError) {
          field.removeAttribute('aria-invalid');
        }
      }
    });
  }
})();
