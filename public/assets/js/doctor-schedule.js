(function () {
  'use strict';

  /*
  | Paramètres > Jours et horaires de travail.
  | API : api/doctor_schedule_get.php et api/doctor_schedule_save.php.
  */

  const API = {
    get: 'api/doctor_schedule_get.php',
    save: 'api/doctor_schedule_save.php'
  };

  const MODE_LABELS = {
    closed: 'Fermé',
    custom_hours: 'Horaires spéciaux',
    note: 'Message'
  };

  const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
    'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  const DAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

  const state = {
    data: null,
    editingDate: null,
    pendingDelete: null
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function notify(message, type = 'info') {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
    }
  }

  function setMessage(text = '', type = '') {
    const box = byId('schedule-message');
    if (!box) return;
    box.textContent = text;
    box.className = `v1-message${type ? ` is-${type}` : ''}`;
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, { cache: 'no-store', ...options });
    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      data = null;
    }

    if (!response.ok || !data?.ok) {
      const error = new Error(data?.message || 'Une erreur est survenue.');
      error.fieldErrors = data?.errors || {};
      throw error;
    }

    return data;
  }

  function addDays(isoDate, days) {
    const [year, month, day] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + days));
    return date.toISOString().slice(0, 10);
  }

  function dateLabel(isoDate) {
    const [year, month, day] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    const today = state.data?.today;
    const prefix = isoDate === today
      ? 'Aujourd’hui · '
      : isoDate === addDays(today, 1) ? 'Demain · ' : '';
    return `${prefix}${DAYS[date.getUTCDay()]} ${day} ${MONTHS[month - 1]}${year !== Number(today?.slice(0, 4)) ? ` ${year}` : ''}`;
  }

  function slotsText(slots = []) {
    return slots.map(slot => `${slot.open} – ${slot.close}`).join(' · ');
  }

  /* =======================================================
     SEMAINE HABITUELLE
     ======================================================= */

  function slotHtml(day, index, slot, removable) {
    return `
      <div class="schedule-slot" data-slot-index="${index}">
        <input type="text" inputmode="numeric" maxlength="5" placeholder="HH:MM" class="schedule-time" value="${escapeHtml(slot.open)}" data-slot-open aria-label="${day.label} : début du créneau ${index + 1}">
        <span aria-hidden="true">–</span>
        <input type="text" inputmode="numeric" maxlength="5" placeholder="HH:MM" class="schedule-time" value="${escapeHtml(slot.close)}" data-slot-close aria-label="${day.label} : fin du créneau ${index + 1}">
        ${removable ? `<button type="button" class="schedule-slot__remove" data-slot-remove aria-label="Retirer ce créneau"><svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-close"></use></svg></button>` : ''}
      </div>
    `;
  }

  function renderDay(row, day) {
    const isOpen = day.slots.length > 0;
    row.classList.toggle('is-closed', !isOpen);
    row.querySelector('[data-day-open]').checked = isOpen;

    const slots = row.querySelector('[data-day-slots]');
    if (!isOpen) {
      slots.innerHTML = '<span class="schedule-day__closed">Fermé</span>';
      return;
    }

    slots.innerHTML = day.slots
      .map((slot, index) => slotHtml(day, index, slot, index > 0))
      .join('')
      + (day.slots.length < 2
        ? '<button type="button" class="schedule-link-button" data-slot-add>+ Après-midi</button>'
        : '');
  }

  function readDay(row) {
    return [...row.querySelectorAll('.schedule-slot')].map(slot => ({
      open: slot.querySelector('[data-slot-open]').value,
      close: slot.querySelector('[data-slot-close]').value
    }));
  }

  function renderWeek() {
    const container = byId('schedule-week');
    if (!container || !state.data) return;

    container.innerHTML = state.data.weekly.map(day => `
      <div class="schedule-day" data-day="${day.day}">
        <label class="schedule-day__toggle">
          <input type="checkbox" data-day-open>
          <span>${escapeHtml(day.label)}</span>
        </label>
        <div class="schedule-day__slots" data-day-slots></div>
      </div>
    `).join('');

    state.data.weekly.forEach(day => {
      renderDay(container.querySelector(`[data-day="${day.day}"]`), day);
    });

    byId('schedule-general-note').value = state.data.general_note || '';
    byId('schedule-enforce-hours').checked = Boolean(state.data.enforce_hours);
  }

  function dayFromRow(row) {
    return {
      day: Number(row.dataset.day),
      label: row.querySelector('.schedule-day__toggle span').textContent,
      slots: readDay(row)
    };
  }

  function handleWeekClick(event) {
    const row = event.target.closest('.schedule-day');
    if (!row) return;

    const day = dayFromRow(row);

    if (event.target.closest('[data-slot-add]')) {
      day.slots.push({ open: '14:00', close: '17:00' });
      renderDay(row, day);
      return;
    }

    const remove = event.target.closest('[data-slot-remove]');
    if (remove) {
      const index = Number(remove.closest('.schedule-slot').dataset.slotIndex);
      day.slots.splice(index, 1);
      renderDay(row, day);
    }
  }

  function handleWeekChange(event) {
    const toggle = event.target.closest('[data-day-open]');
    if (!toggle) return;

    const row = toggle.closest('.schedule-day');
    const day = dayFromRow(row);
    day.slots = toggle.checked ? [{ open: '08:00', close: '12:00' }] : [];
    renderDay(row, day);
  }

  function copyFirstDay() {
    const rows = [...document.querySelectorAll('#schedule-week .schedule-day')];
    const openRows = rows.filter(row => row.querySelector('[data-day-open]').checked);

    if (openRows.length < 2) {
      notify('Cochez au moins deux jours travaillés.', 'info');
      return;
    }

    const model = readDay(openRows[0]);
    openRows.slice(1).forEach(row => {
      renderDay(row, { ...dayFromRow(row), slots: model.map(slot => ({ ...slot })) });
    });
    notify('Horaires copiés. Pensez à enregistrer.', 'success');
  }

  async function saveWeek(event) {
    event.preventDefault();
    const button = byId('schedule-save-week');
    const rows = [...document.querySelectorAll('#schedule-week .schedule-day')];

    const payload = {
      action: 'weekly',
      days: rows.map(row => ({ day: Number(row.dataset.day), slots: readDay(row) })),
      general_note: byId('schedule-general-note').value,
      enforce_hours: byId('schedule-enforce-hours').checked
    };

    button.disabled = true;
    setMessage('');

    try {
      const response = await requestJson(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      state.data = response.data;
      renderWeek();
      notify(response.message, 'success');
    } catch (error) {
      setMessage(error.message, 'error');
    } finally {
      button.disabled = false;
    }
  }

  /* =======================================================
     JOURS PARTICULIERS
     ======================================================= */

  function renderDays() {
    const list = byId('schedule-days');
    if (!list || !state.data) return;

    const days = state.data.exceptions || [];
    if (days.length === 0) {
      list.innerHTML = '<li class="schedule-empty">Aucune date particulière à venir.</li>';
      return;
    }

    list.innerHTML = days.map(day => {
      const detail = day.mode === 'custom_hours' ? slotsText(day.slots) : '';
      const confirming = state.pendingDelete === day.date;

      return `
        <li class="schedule-days__item schedule-days__item--${escapeHtml(day.mode)}">
          <div class="schedule-days__main">
            <strong>${escapeHtml(dateLabel(day.date))}</strong>
            <span class="schedule-badge schedule-badge--${escapeHtml(day.mode)}">${escapeHtml(MODE_LABELS[day.mode] || day.mode)}${detail ? ` · ${escapeHtml(detail)}` : ''}</span>
            ${day.note ? `<p>${escapeHtml(day.note)}</p>` : ''}
          </div>
          <div class="schedule-days__actions">
            <button type="button" class="schedule-day-action" data-day-edit="${day.date}">Modifier</button>
            <button type="button" class="schedule-day-action schedule-day-action--danger${confirming ? ' is-confirming' : ''}" data-day-delete="${day.date}">${confirming ? 'Confirmer ?' : 'Supprimer'}</button>
          </div>
        </li>
      `;
    }).join('');
  }

  function clearDayErrors() {
    document.querySelectorAll('[data-schedule-error]').forEach(element => {
      element.textContent = '';
    });
  }

  function currentMode() {
    return document.querySelector('[name="schedule_mode"]:checked')?.value || 'closed';
  }

  function updateDayModeFields() {
    const mode = currentMode();
    byId('schedule-day-slots').hidden = mode !== 'custom_hours';
    byId('schedule-day-note-optional').hidden = mode === 'note';
  }

  function setDaySlots(slots) {
    const rows = document.querySelectorAll('#schedule-day-slots [data-day-slot]');
    const defaults = [{ open: '08:00', close: '12:00' }, { open: '14:00', close: '17:00' }];

    rows.forEach((row, index) => {
      const slot = slots[index] || defaults[index];
      row.querySelector('[data-slot-open]').value = slot.open;
      row.querySelector('[data-slot-close]').value = slot.close;
      row.hidden = index > 0 && !slots[index];
    });

    document.querySelector('[data-day-slot-add]').hidden = slots.length > 1;
  }

  function openDayModal({ date = null, mode = 'closed', slots = [], note = '', editing = false } = {}) {
    const modal = byId('schedule-day-modal');
    if (!modal || !state.data) return;

    clearDayErrors();
    state.editingDate = editing ? date : null;

    const dateInput = byId('schedule-day-date');
    dateInput.min = state.data.today;
    dateInput.max = addDays(state.data.today, 365);
    dateInput.value = date || addDays(state.data.today, 1);

    document.querySelectorAll('[name="schedule_mode"]').forEach(input => {
      input.checked = input.value === mode;
    });
    setDaySlots(slots);
    byId('schedule-day-note').value = note;
    byId('schedule-day-title').textContent = editing ? 'Modifier la date' : 'Ajouter une date';
    updateDayModeFields();

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('team-modal-open');
    window.setTimeout(() => dateInput.focus(), 0);
  }

  function closeDayModal() {
    const modal = byId('schedule-day-modal');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('team-modal-open');
  }

  async function postDay(payload) {
    const response = await requestJson(API.save, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    state.data = response.data;
    renderDays();
    return response;
  }

  async function saveDay(event) {
    event.preventDefault();
    clearDayErrors();

    const mode = currentMode();
    const date = byId('schedule-day-date').value;
    const slots = [...document.querySelectorAll('#schedule-day-slots [data-day-slot]')]
      .filter(row => !row.hidden)
      .map(row => ({
        open: row.querySelector('[data-slot-open]').value,
        close: row.querySelector('[data-slot-close]').value
      }));

    const button = byId('schedule-day-save');
    button.disabled = true;

    try {
      // Changer la date d'une entrée existante : on retire l'ancienne.
      if (state.editingDate && state.editingDate !== date) {
        await postDay({ action: 'exception_delete', date: state.editingDate });
      }

      const response = await postDay({
        action: 'exception_save',
        date,
        mode,
        slots: mode === 'custom_hours' ? slots : [],
        note: byId('schedule-day-note').value
      });
      closeDayModal();
      notify(response.message, 'success');
    } catch (error) {
      const errors = error.fieldErrors || {};
      Object.entries(errors).forEach(([field, message]) => {
        const target = document.querySelector(`[data-schedule-error="${field}"]`);
        if (target) target.textContent = message;
      });
      if (Object.keys(errors).length === 0) {
        notify(error.message, 'error');
      }
    } finally {
      button.disabled = false;
    }
  }

  async function handleDaysClick(event) {
    const edit = event.target.closest('[data-day-edit]');
    if (edit) {
      const day = state.data.exceptions.find(item => item.date === edit.dataset.dayEdit);
      if (day) openDayModal({ ...day, editing: true });
      return;
    }

    const remove = event.target.closest('[data-day-delete]');
    if (!remove) return;

    const date = remove.dataset.dayDelete;
    if (state.pendingDelete !== date) {
      state.pendingDelete = date;
      renderDays();
      return;
    }

    state.pendingDelete = null;
    try {
      const response = await postDay({ action: 'exception_delete', date });
      notify(response.message, 'success');
    } catch (error) {
      renderDays();
      notify(error.message, 'error');
    }
  }

  function handleQuickAction(event) {
    const button = event.target.closest('[data-schedule-quick]');
    if (!button || !state.data) return;

    const kind = button.dataset.scheduleQuick;
    if (kind === 'new') {
      openDayModal();
      return;
    }

    const date = kind === 'today' ? state.data.today : addDays(state.data.today, 1);
    const existing = state.data.exceptions.find(item => item.date === date);
    openDayModal(existing
      ? { ...existing, mode: 'closed', editing: true }
      : { date, mode: 'closed' });
  }

  // Saisie « 0830 » → « 08:30 » (format 24 h, indépendant de la langue du navigateur).
  function formatTimeInput(event) {
    const input = event.target.closest('.schedule-time');
    if (!input) return;

    const digits = input.value.replace(/\D/g, '').slice(0, 4);
    input.value = digits.length > 2 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits;
  }

  function padTimeInput(event) {
    const input = event.target.closest('.schedule-time');
    if (!input) return;

    const match = input.value.match(/^(\d{1,2})(?::(\d{0,2}))?$/);
    if (!match) return;
    input.value = `${match[1].padStart(2, '0')}:${(match[2] || '00').padEnd(2, '0')}`;
  }

  function handleSuggestion(event) {
    const chip = event.target.closest('.schedule-suggestions button');
    if (!chip) return;

    const target = byId(chip.closest('.schedule-suggestions').dataset.suggestionsFor);
    if (!target) return;

    target.value = chip.textContent.trim();
    target.focus();
  }

  /* =======================================================
     INITIALISATION
     ======================================================= */

  function bind(section) {
    if (section.dataset.scheduleBound === '1') return;
    section.dataset.scheduleBound = '1';

    byId('schedule-week')?.addEventListener('click', handleWeekClick);
    byId('schedule-week')?.addEventListener('change', handleWeekChange);
    byId('schedule-copy-first-day')?.addEventListener('click', copyFirstDay);
    byId('schedule-week-form')?.addEventListener('submit', saveWeek);
    byId('schedule-days')?.addEventListener('click', handleDaysClick);
    section.querySelector('.schedule-quick')?.addEventListener('click', handleQuickAction);
    section.addEventListener('click', handleSuggestion);

    const modal = byId('schedule-day-modal');
    [section, modal].forEach(root => {
      root?.addEventListener('input', formatTimeInput);
      root?.addEventListener('focusout', padTimeInput);
    });
    modal?.addEventListener('click', event => {
      if (event.target.closest('[data-close-schedule-day]')) closeDayModal();
      handleSuggestion(event);

      if (event.target.closest('[data-day-slot-add]')) {
        modal.querySelector('[data-day-slot="1"]').hidden = false;
        event.target.closest('[data-day-slot-add]').hidden = true;
      }
      if (event.target.closest('[data-day-slot-remove]')) {
        modal.querySelector('[data-day-slot="1"]').hidden = true;
        modal.querySelector('[data-day-slot-add]').hidden = false;
      }
    });
    modal?.addEventListener('change', event => {
      if (event.target.name === 'schedule_mode') updateDayModeFields();
    });
    modal?.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeDayModal();
    });
    byId('schedule-day-form')?.addEventListener('submit', saveDay);
  }

  window.initMarkiDoctorSchedule = async function () {
    const section = byId('doctor-schedule-section');
    if (!section) return;

    bind(section);

    try {
      const response = await requestJson(API.get);
      state.data = response.data;
      state.pendingDelete = null;
      renderWeek();
      renderDays();
    } catch (error) {
      setMessage(error.message, 'error');
    }
  };
})();
