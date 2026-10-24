(function () {
  'use strict';

  /*
  | Fenêtre « Qui fait quoi ? » (app/partials/roles_guide_modal.php).
  | Quatre étapes courtes, ouvertes par tout élément [data-open-roles-guide].
  */

  const ABILITIES = [
    ['queue.manage', 'Gérer la liste du jour'],
    ['patients.view', 'Voir les fiches patients'],
    ['lists.view', 'Voir les anciennes listes'],
    ['settings.view', 'Voir les paramètres'],
    ['settings.manage_doctor', 'Modifier le profil et le QR code du médecin'],
    ['settings.manage_clinic', 'Modifier les informations de la clinique'],
    ['team.manage', 'Ajouter des comptes et choisir les accès']
  ];

  let currentStep = 0;
  let lastFocused = null;

  function modal() {
    return document.getElementById('roles-modal');
  }

  function steps() {
    return [...(modal()?.querySelectorAll('[data-roles-step]') || [])];
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function fillAccountStep(root) {
    const context = window.MARKI_CONTEXT || {};
    const capabilities = context.capabilities || {};
    const canManageTeam = Boolean(capabilities['team.manage']);

    const role = root.querySelector('[data-roles-my-role]');
    if (role) {
      role.textContent = context.role_label || 'utilisateur';
    }

    const list = root.querySelector('[data-roles-my-abilities]');
    if (list) {
      list.innerHTML = ABILITIES.map(([key, label]) => {
        const allowed = Boolean(capabilities[key]);
        return `
          <li class="roles-ability ${allowed ? 'is-allowed' : 'is-denied'}">
            <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#${allowed ? 'mk-check-circle' : 'mk-slash-circle'}"></use></svg>
            <span>${escapeHtml(label)}</span>
            <span class="sr-only">${allowed ? 'autorisé' : 'non autorisé'}</span>
          </li>
        `;
      }).join('');
    }

    root.querySelectorAll('[data-roles-admin-only]').forEach(element => {
      element.hidden = !canManageTeam;
    });
    root.querySelectorAll('[data-roles-non-admin-only]').forEach(element => {
      element.hidden = canManageTeam;
    });
  }

  function showStep(index) {
    const root = modal();
    const all = steps();
    if (!root || all.length === 0) return;

    currentStep = Math.max(0, Math.min(index, all.length - 1));
    const isLast = currentStep === all.length - 1;

    all.forEach((step, stepIndex) => {
      step.hidden = stepIndex !== currentStep;
    });

    root.querySelector('[data-roles-title]').textContent =
      all[currentStep].dataset.title || '';
    root.querySelector('[data-roles-step-label]').textContent =
      `${currentStep + 1} / ${all.length}`;

    root.querySelector('[data-roles-prev]').hidden = currentStep === 0;
    root.querySelector('[data-roles-next]').textContent = isLast ? 'Terminé' : 'Suivant';

    root.querySelectorAll('.roles-dots__dot').forEach((dot, dotIndex) => {
      dot.classList.toggle('is-active', dotIndex === currentStep);
    });

    root.querySelector('.roles-modal__body').scrollTop = 0;
  }

  function openRolesGuide() {
    const root = modal();
    if (!root) return;

    document.querySelector('.account-menu[open]')?.removeAttribute('open');
    lastFocused = document.activeElement;

    const dots = root.querySelector('[data-roles-dots]');
    if (dots && dots.childElementCount === 0) {
      dots.innerHTML = steps()
        .map(() => '<span class="roles-dots__dot"></span>')
        .join('');
    }

    fillAccountStep(root);
    showStep(0);

    root.hidden = false;
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('team-modal-open');
    window.setTimeout(() => root.querySelector('[data-roles-next]')?.focus(), 0);
  }

  function closeRolesGuide() {
    const root = modal();
    if (!root || root.hidden) return;

    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('team-modal-open');
    lastFocused?.focus?.();
  }

  function startNewAccount(type) {
    closeRolesGuide();

    // Déjà sur les paramètres : on ouvre le formulaire directement.
    const button = document.querySelector(`[data-team-new-account="${type}"]`);
    if (button) {
      button.scrollIntoView({ behavior: 'smooth', block: 'center' });
      button.click();
      return;
    }

    window.MARKI_PENDING_TEAM_ACTION = type;
    window.loadPage?.('settings');
    window.setActiveMenuItem?.('settings');
  }

  document.addEventListener('click', event => {
    if (event.target.closest('[data-open-roles-guide]')) {
      event.preventDefault();
      openRolesGuide();
      return;
    }

    if (!modal() || modal().hidden) return;

    if (event.target.closest('[data-close-roles-guide]')) {
      closeRolesGuide();
      return;
    }

    if (event.target.closest('[data-roles-prev]')) {
      showStep(currentStep - 1);
      return;
    }

    if (event.target.closest('[data-roles-next]')) {
      if (currentStep >= steps().length - 1) {
        closeRolesGuide();
      } else {
        showStep(currentStep + 1);
      }
      return;
    }

    const newAccount = event.target.closest('[data-roles-new-account]');
    if (newAccount) {
      startNewAccount(newAccount.dataset.rolesNewAccount);
    }
  });

  document.addEventListener('keydown', event => {
    if (!modal() || modal().hidden) return;

    if (event.key === 'Escape') closeRolesGuide();
    if (event.key === 'ArrowRight') showStep(currentStep + 1);
    if (event.key === 'ArrowLeft') showStep(currentStep - 1);
  });

  window.openMarkiRolesGuide = openRolesGuide;
})();
