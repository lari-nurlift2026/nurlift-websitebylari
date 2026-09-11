/* Contact forms: in-memory retries only; confirmation language belongs to the server. */
(() => {
  'use strict';
  const copy = {
    pt: {
      pending: 'Enviando…',
      technical: 'Não foi possível enviar agora. Tente novamente em alguns instantes.',
      required: 'Preencha este campo.',
      name: 'Informe um nome entre 2 e 100 caracteres.',
      email: 'Informe um e-mail válido, com até 254 caracteres.',
      phone: 'Informe um telefone com 7 a 15 dígitos, incluindo o código do país quando necessário.',
      subject: 'Informe um assunto entre 2 e 200 caracteres, em uma única linha.',
      controls: 'Use texto em uma única linha, sem caracteres de controle.',
      invalid: 'Confira os campos indicados antes de enviar.'
    },
    en: {
      pending: 'Sending…',
      technical: 'We couldn’t send your request right now. Please try again in a moment.',
      required: 'Please complete this field.',
      name: 'Enter a name between 2 and 100 characters.',
      email: 'Enter a valid email address of up to 254 characters.',
      phone: 'Enter a phone number with 7 to 15 digits, including the country code where needed.',
      subject: 'Enter a subject between 2 and 200 characters on a single line.',
      controls: 'Use a single line without control characters.',
      invalid: 'Please check the indicated fields before submitting.'
    }
  };

  function newKey() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    if (!globalThis.crypto?.getRandomValues) throw new Error('Secure randomness unavailable');
    return Array.from(globalThis.crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
  }

  document.querySelectorAll('.contact-form').forEach(form => {
    const messages = copy[form.dataset.language] || copy.pt;
    const fields = ['name', 'email', 'phone', 'subject'].map(name => form.elements.namedItem(name));
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('.contact-form-status');
    const buttonLabel = button.textContent;
    let pending = false;
    let fingerprint = '';
    let key = '';

    function validate(field) {
      field.setCustomValidity('');
      const value = field.value.trim();
      let message = '';
      if (/[\p{Cc}\p{Cf}]/u.test(field.value)) message = messages.controls;
      else if (!value) message = messages.required;
      else if (field.name === 'name' && (Array.from(value).length < 2 || Array.from(value).length > 100)) message = messages.name;
      else if (field.name === 'subject' && (Array.from(value).length < 2 || Array.from(value).length > 200)) message = messages.subject;
      else if (field.name === 'email' && (field.validity.typeMismatch || value.length > 254 || !/^[^\s@]+@[^\s@]+$/.test(value))) message = messages.email;
      else if (field.name === 'phone') {
        const digits = value.replace(/[^0-9]/g, '');
        if (!/^[0-9 +().-]+$/.test(value) || value.length > 32 || digits.length < 7 || digits.length > 15) message = messages.phone;
      }
      field.setCustomValidity(message);
      field.setAttribute('aria-invalid', message ? 'true' : 'false');
      form.querySelector(`#${field.id}-error`).textContent = message;
      return !message;
    }

    // Use the browser validity API, but own the accessible error presentation.
    form.noValidate = true;
    button.disabled = false;
    fields.forEach(field => {
      field.addEventListener('blur', () => validate(field));
      field.addEventListener('input', () => {
        if (field.getAttribute('aria-invalid') === 'true') validate(field);
      });
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (pending) return;
      const valid = fields.map(validate).every(Boolean);
      if (!valid || !form.checkValidity()) {
        status.removeAttribute('lang');
        status.textContent = messages.invalid;
        fields.find(field => !field.validity.valid)?.focus();
        return;
      }
      const data = Object.fromEntries(new FormData(form));
      fields.forEach(field => { data[field.name] = field.value.trim(); });
      const at = data.email.lastIndexOf('@');
      data.email = data.email.slice(0, at + 1) + data.email.slice(at + 1).toLowerCase();
      const nextFingerprint = JSON.stringify(data);
      let timeout;
      pending = true;
      button.disabled = true;
      button.textContent = messages.pending;
      form.setAttribute('aria-busy', 'true');
      fields.forEach(field => { field.readOnly = true; });
      status.removeAttribute('lang');
      status.textContent = messages.pending;
      try {
        if (!key || nextFingerprint !== fingerprint) {
          key = newKey();
          fingerprint = nextFingerprint;
        }
        data.idempotency_key = key;
        const controller = new AbortController();
        timeout = setTimeout(() => controller.abort(), 45000);
        const response = await fetch('/api/contact.php', {
          method: 'POST',
          credentials: 'omit',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(data),
          signal: controller.signal
        });
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Invalid response');
        const result = await response.json();
        if (typeof result?.message !== 'string' || !result.message || result.message.length > 1000) throw new Error('Invalid message');
        if (response.ok && result.ok === true && ['pt', 'en'].includes(result.language)) {
          // Display exactly what the backend returned, even when it differs from page language.
          status.lang = result.language;
          status.textContent = result.message;
          form.reset();
          fields.forEach(field => {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            form.querySelector(`#${field.id}-error`).textContent = '';
          });
          key = '';
          fingerprint = '';
        } else if (!response.ok && result.ok === false && ['VALIDATION_ERROR', 'RATE_LIMITED', 'SERVER_ERROR'].includes(result.code)) {
          status.removeAttribute('lang');
          status.textContent = result.message;
        } else {
          throw new Error('Unexpected response');
        }
      } catch {
        status.removeAttribute('lang');
        status.textContent = messages.technical;
        // Keep values and key: a timed-out request may already have reached SMTP.
      } finally {
        clearTimeout(timeout);
        pending = false;
        button.disabled = false;
        button.textContent = buttonLabel;
        fields.forEach(field => { field.readOnly = false; });
        form.removeAttribute('aria-busy');
      }
    });
  });
})();
