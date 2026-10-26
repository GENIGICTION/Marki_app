(function () {
  'use strict';

  /*
  | Bouton « Donner mon avis ».
  | L'envoi passe par api/feedback_submit.php. Tant que cette route n'existe
  | pas (phase 5), le message est gardé sur l'appareil puis renvoyé
  | automatiquement au prochain chargement : aucun avis n'est perdu.
  */

  const ENDPOINT = 'api/feedback_submit.php';
  const OUTBOX_KEY = 'marki:feedback-outbox';
  const MAX_LENGTH = 2000;

  let lastFocused = null;

  function element(id) {
    return document.getElementById(id);
  }

  function readOutbox() {
    try {
      const items = JSON.parse(window.localStorage.getItem(OUTBOX_KEY) || '[]');
      return Array.isArray(items) ? items : [];
    } catch (error) {
      return [];
    }
  }

  function writeOutbox(items) {
    try {
      if (items.length === 0) {
        window.localStorage.removeItem(OUTBOX_KEY);
      } else {
        window.localStorage.setItem(OUTBOX_KEY, JSON.stringify(items.slice(-20)));
      }
      return true;
    } catch (error) {
      return false;
    }
  }

  function currentPage() {
    return document.querySelector('.sidebar__item.active, .mobile-bottom-nav__item.active')
      ?.dataset.page || '';
  }

  function buildPayload(kind, message) {
    return {
      kind,
      message,
      page: currentPage(),
      doctor_id: Number(window.MARKI_CONTEXT?.doctor_id || 0) || null,
      viewport: `${window.innerWidth}x${window.innerHeight}`,
      created_at: new Date().toISOString()
    };
  }

  // Retourne 'sent', 'unavailable' (route absente) ou lève une erreur.
  async function postFeedback(payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (response.status === 404) {
      return 'unavailable';
    }

    if (!response.ok) {
      let message = 'Impossible d’envoyer votre message.';
      try {
        message = (await response.json())?.message || message;
      } catch (error) {
        // Réponse non JSON : on garde le message générique.
      }
      throw new Error(message);
    }

    return 'sent';
  }

  async function flushOutbox() {
    const pending = readOutbox();
    if (pending.length === 0) return;

    const remaining = [];
    for (const item of pending) {
      try {
        if (await postFeedback(item) !== 'sent') {
          remaining.push(item);
        }
      } catch (error) {
        remaining.push(item);
      }
    }

    writeOutbox(remaining);
  }

  function setStatus(text = '', type = '') {
    const status = element('feedback-status');
    if (!status) return;

    status.textContent = text;
    status.className = `v1-message${type ? ` is-${type}` : ''}`;
  }

  function updateCounter() {
    const textarea = element('feedback-message');
    const counter = element('feedback-counter');
    if (textarea && counter) {
      counter.textContent = `${textarea.value.length} / ${MAX_LENGTH}`;
    }
  }

  function openFeedback() {
    const modal = element('feedback-modal');
    if (!modal) return;

    document.querySelector('.account-menu[open]')?.removeAttribute('open');
    lastFocused = document.activeElement;
    setStatus('');
    element('feedback-error').textContent = '';
    updateCounter();

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('team-modal-open');
    window.setTimeout(() => element('feedback-message')?.focus(), 0);
  }

  function closeFeedback() {
    const modal = element('feedback-modal');
    if (!modal || modal.hidden) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('team-modal-open');
    lastFocused?.focus?.();
  }

  async function submitFeedback(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const textarea = element('feedback-message');
    const button = element('feedback-submit');
    const error = element('feedback-error');
    const message = (textarea?.value || '').trim();
    const kind = form.querySelector('[name="kind"]:checked')?.value || 'other';

    if (message.length < 3) {
      error.textContent = 'Écrivez quelques mots avant d’envoyer.';
      textarea?.focus();
      return;
    }

    error.textContent = '';
    setStatus('');
    button.disabled = true;
    button.textContent = 'Envoi…';

    const payload = buildPayload(kind, message);

    try {
      const result = await postFeedback(payload);

      if (result === 'unavailable') {
        const saved = writeOutbox([...readOutbox(), payload]);
        if (!saved) {
          throw new Error('L’envoi n’est pas encore disponible et votre message n’a pas pu être gardé.');
        }
      }

      form.reset();
      updateCounter();
      closeFeedback();
      window.showToast?.(
        result === 'sent'
          ? 'Merci ! Votre message a bien été envoyé à l’équipe MARKI.'
          : 'Merci ! Votre message est enregistré et sera transmis à l’équipe MARKI.',
        'success'
      );
    } catch (failure) {
      setStatus(failure.message, 'error');
    } finally {
      button.disabled = false;
      button.textContent = 'Envoyer';
    }
  }

  function init() {
    const form = element('feedback-form');
    if (!form) return;

    form.addEventListener('submit', submitFeedback);
    element('feedback-message')?.addEventListener('input', updateCounter);

    document.addEventListener('click', event => {
      if (event.target.closest('[data-open-feedback]')) {
        event.preventDefault();
        openFeedback();
        return;
      }

      if (event.target.closest('[data-close-feedback]')) {
        closeFeedback();
      }
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeFeedback();
    });

    flushOutbox();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
