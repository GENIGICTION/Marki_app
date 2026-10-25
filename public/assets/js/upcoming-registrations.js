(function () {
  'use strict';

  /*
  | Liste du jour > « Inscriptions à venir ».
  | Patients inscrits avec le QR pour les prochains jours (API queues_upcoming.php).
  */

  const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
  const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
    'août', 'septembre', 'octobre', 'novembre', 'décembre'];

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function addDays(isoDate, days) {
    const [year, month, day] = isoDate.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
  }

  function dayLabel(isoDate, today) {
    const [year, month, day] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    const label = `${DAYS[date.getUTCDay()]} ${day} ${MONTHS[month - 1]}`;
    return isoDate === addDays(today, 1)
      ? `Demain · ${label}`
      : label.charAt(0).toUpperCase() + label.slice(1);
  }

  function patientCount(count) {
    return `${count} patient${count > 1 ? 's' : ''}`;
  }

  function formatPhone(phone) {
    let digits = String(phone || '').replace(/\D/g, '');
    // Numéros enregistrés au format international (+213…) : affichage local.
    if (digits.length === 12 && digits.startsWith('213')) digits = `0${digits.slice(3)}`;
    return digits.length === 10
      ? `${digits.slice(0, 4)} ${digits.slice(4, 6)} ${digits.slice(6, 8)} ${digits.slice(8)}`
      : (phone || '');
  }

  function render(container, data) {
    const days = data.days || [];
    container.hidden = days.length === 0;
    if (days.length === 0) return;

    const total = days.reduce((sum, day) => sum + day.patients.length, 0);
    container.querySelector('#upcoming-registrations-summary').textContent =
      `${patientCount(total)} sur ${days.length} jour${days.length > 1 ? 's' : ''}`;

    container.querySelector('#upcoming-registrations-body').innerHTML = days.map(day => `
      <section class="upcoming-day">
        <header class="upcoming-day__head">
          <strong>${escapeHtml(dayLabel(day.date, data.today))}</strong>
          <span>${escapeHtml(patientCount(day.patients.length))}</span>
        </header>
        <ol class="upcoming-day__list">
          ${day.patients.map(patient => `
            <li>
              <span class="upcoming-day__num">${patient.position_number}</span>
              <span class="upcoming-day__name">${escapeHtml(patient.name)}</span>
              <span class="upcoming-day__phone">${escapeHtml(formatPhone(patient.phone))}</span>
            </li>
          `).join('')}
        </ol>
      </section>
    `).join('');
  }

  window.initMarkiUpcomingRegistrations = async function () {
    const container = document.getElementById('upcoming-registrations');
    if (!container) return;

    try {
      const response = await fetch('api/queues_upcoming.php', { cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.message || '');
      render(container, data.data);
    } catch (error) {
      container.hidden = true;
    }
  };
})();
