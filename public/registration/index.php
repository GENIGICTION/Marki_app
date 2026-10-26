<?php

declare(strict_types=1);

$public = require __DIR__ . '/../../app/public_bootstrap.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$link = trim((string) ($_GET['link'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$basePath = rtrim((string) ($public['config']['app']['base_path'] ?? ''), '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/marki-app-192.png" type="image/png">
    <meta name="theme-color" content="#6d4aff">
    <meta name="csrf-token" content="<?= e($public['csrf_token']) ?>">
    <meta name="marki-base-path" content="<?= e($basePath) ?>">
    <title>MARKI — Inscription à la liste d’attente</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/design-system/marki-theme.css?v=20261017-dates1">
    <link rel="stylesheet" href="assets/registration.css?v=20261026-phase4">
    <script src="../assets/js/phone-input.js?v=20260830-ui1" defer></script>
    <script src="assets/registration.js?v=20261026-phase4" defer></script>
    <script src="../assets/js/marki-datepicker.js?v=20261017-dates1" defer></script>
</head>
<body>
    <?php require __DIR__ . '/../../app/partials/icons_sprite.php'; ?>

    <main
        class="public-registration-shell"
        id="public-registration-app"
        data-link="<?= e($link) ?>"
        data-token="<?= e($token) ?>"
    >
        <header class="public-registration-brand">
            <a class="public-registration-brand__logo" href="#" aria-label="MARKI">
                <span class="public-registration-brand__mark">M</span>
                <span>MARKI</span>
            </a>
            <div class="public-registration-brand__secure">
                <span class="public-registration-secure-dot" aria-hidden="true"></span>
                <svg class="mk-icon mk-icon--xs" aria-hidden="true"><use href="#mk-shield-check"></use></svg>
                <span>Inscription sécurisée & privée</span>
            </div>
        </header>

        <section class="public-registration-card" aria-labelledby="registration-title">
            <div class="public-registration-card__hero">
                <div class="public-registration-card__icon" aria-hidden="true">
                    <svg class="mk-icon mk-icon--md"><use href="#mk-user-plus"></use></svg>
                </div>
                <div class="public-registration-card__hero-text">
                    <p class="public-registration-eyebrow">Salle d’attente</p>
                    <h1 id="registration-title">Rejoindre la file</h1>
                    <p id="registration-introduction">
                        Prenez votre numéro de passage en quelques secondes.
                    </p>
                </div>
            </div>

            <div id="registration-loading" class="public-registration-loading" role="status">
                <span class="public-registration-spinner" aria-hidden="true"></span>
                <span>Vérification du lien et des disponibilités…</span>
            </div>

            <div id="registration-unavailable" class="public-registration-state" hidden>
                <div class="public-registration-state__icon" aria-hidden="true">
                    <svg class="mk-icon mk-icon--lg"><use href="#mk-info"></use></svg>
                </div>
                <h2 id="registration-unavailable-title">Inscription indisponible</h2>
                <p id="registration-unavailable-message"></p>
                <div class="public-registration-contact" id="registration-contact" hidden></div>
            </div>

            <div id="registration-content" hidden>
                <div class="public-registration-doctor">
                    <div class="public-registration-doctor__avatar" aria-hidden="true">
                        <svg class="mk-icon mk-icon--lg"><use href="#mk-user"></use></svg>
                    </div>
                    <div>
                        <strong id="registration-doctor-name">—</strong>
                        <span id="registration-doctor-specialty">—</span>
                        <small id="registration-clinic-name">—</small>
                    </div>
                </div>

                <div class="public-registration-notice public-registration-notice--success" id="registration-open-message"></div>

                <form id="public-registration-form" class="public-registration-form" novalidate>
                    <fieldset class="public-registration-field public-date-field" id="registration-date-field" hidden>
                        <legend>Jour de passage <span aria-hidden="true">*</span></legend>
                        <div class="public-date-options" id="registration-date-options"></div>
                        <small class="public-registration-error" data-error-for="queue_date"></small>
                    </fieldset>

                    <div class="public-registration-field">
                        <label for="registration-full-name">
                            Nom complet <span aria-hidden="true">*</span>
                        </label>
                        <div class="public-input-wrapper">
                            <input
                                type="text"
                                id="registration-full-name"
                                name="full_name"
                                autocomplete="name"
                                maxlength="191"
                                placeholder="Votre nom et prénom"
                                required
                            >
                            <svg class="mk-icon mk-icon--sm public-input-icon" aria-hidden="true"><use href="#mk-user"></use></svg>
                        </div>
                        <small class="public-registration-error" data-error-for="full_name"></small>
                    </div>

                    <div class="public-registration-field">
                        <label for="registration-phone">
                            Numéro de téléphone <span aria-hidden="true">*</span>
                        </label>
                        <div class="public-input-wrapper">
                            <input
                                type="tel"
                                id="registration-phone"
                                name="phone"
                                inputmode="numeric"
                                autocomplete="tel"
                                maxlength="13"
                                placeholder="05XX / 06XX / 07XX"
                                data-dz-mobile
                                required
                            >
                            <svg class="mk-icon mk-icon--sm public-input-icon" aria-hidden="true"><use href="#mk-phone"></use></svg>
                        </div>
                        <small class="public-registration-hint">Format : 0550 80 30 90</small>
                        <small class="public-registration-error" data-error-for="phone"></small>
                    </div>

                    <div class="public-registration-field" id="registration-birth-date-field">
                        <label for="registration-birth-date">
                            Date de naissance
                            <span id="registration-birth-date-required" aria-hidden="true" hidden>*</span>
                        </label>
                        <div class="public-input-wrapper">
                            <input
                                type="text"
                                id="registration-birth-date"
                                name="birth_date"
                                inputmode="numeric"
                                maxlength="10"
                                placeholder="JJ/MM/AAAA"
                                autocomplete="bday"
                            >
                            <svg class="mk-icon mk-icon--sm public-input-icon" aria-hidden="true"><use href="#mk-calendar"></use></svg>
                        </div>
                        <small class="public-registration-error" data-error-for="birth_date"></small>
                    </div>

                    <label class="public-registration-consent">
                        <input
                            type="checkbox"
                            id="registration-privacy-consent"
                            name="privacy_consent"
                            value="1"
                            required
                        >
                        <span>
                            J’accepte que mes informations soient utilisées uniquement par le cabinet médical pour gérer mon passage dans la liste d’attente.
                        </span>
                    </label>
                    <small class="public-registration-error" data-error-for="privacy_consent"></small>

                    <div id="registration-shared-phone" class="public-registration-notice public-registration-notice--warning" hidden>
                        <strong>Numéro familial détecté</strong>
                        <p id="registration-shared-phone-message"></p>
                        <button type="button" id="registration-confirm-shared-phone" class="public-registration-button public-registration-button--warning">
                            Confirmer et continuer
                        </button>
                    </div>

                    <div id="registration-form-message" class="public-registration-message" role="alert" aria-live="polite"></div>

                    <button type="submit" id="registration-submit" class="public-registration-button public-registration-button--primary">
                        <svg class="mk-icon" aria-hidden="true"><use href="#mk-user-plus"></use></svg>
                        <span>Rejoindre la liste d’attente</span>
                    </button>
                </form>

                <p class="public-registration-privacy-note">
                    <svg class="mk-icon mk-icon--xs" aria-hidden="true"><use href="#mk-lock"></use></svg>
                    <span>Votre numéro et votre suivi restent strictement confidentiels et privés.</span>
                </p>
            </div>

            <section id="registration-lookup" class="public-lookup" aria-label="Déjà inscrit" hidden>
                <div class="public-lookup__saved" id="registration-lookup-saved" hidden>
                    <span id="registration-lookup-saved-text"></span>
                    <a id="registration-lookup-saved-link" href="#" class="public-lookup__saved-link">Voir mon suivi</a>
                </div>

                <button type="button" class="public-lookup__toggle" id="registration-lookup-toggle" aria-expanded="false" aria-controls="registration-lookup-form">
                    <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-ticket"></use></svg>
                    <span><strong>Déjà inscrit ?</strong> Suivez votre tour</span>
                    <svg class="mk-icon mk-icon--sm public-lookup__chevron" aria-hidden="true"><use href="#mk-chevron-down"></use></svg>
                </button>

                <form id="registration-lookup-form" class="public-lookup__form" novalidate hidden>
                    <p class="public-lookup__hint">Inscrit en ligne ou au cabinet ? Entrez le numéro et le nom donnés à l’inscription.</p>

                    <div class="public-registration-field">
                        <label for="registration-lookup-phone">Numéro de téléphone</label>
                        <div class="public-input-wrapper">
                            <input type="tel" id="registration-lookup-phone" name="phone" inputmode="numeric" autocomplete="tel" maxlength="13" placeholder="05XX / 06XX / 07XX" data-dz-mobile required>
                            <svg class="mk-icon mk-icon--sm public-input-icon" aria-hidden="true"><use href="#mk-phone"></use></svg>
                        </div>
                        <small class="public-registration-error" data-lookup-error="phone"></small>
                    </div>

                    <div class="public-registration-field">
                        <label for="registration-lookup-name">Votre nom ou prénom</label>
                        <div class="public-input-wrapper">
                            <input type="text" id="registration-lookup-name" name="name" autocomplete="name" maxlength="191" placeholder="Ex. : Benali" required>
                            <svg class="mk-icon mk-icon--sm public-input-icon" aria-hidden="true"><use href="#mk-user"></use></svg>
                        </div>
                        <small class="public-registration-error" data-lookup-error="name"></small>
                    </div>

                    <div id="registration-lookup-message" class="public-registration-message" role="alert" aria-live="polite"></div>

                    <button type="submit" id="registration-lookup-submit" class="public-registration-button public-lookup__submit">
                        <span>Voir mon tour</span>
                    </button>
                </form>
            </section>

            <section id="registration-schedule" class="public-schedule" aria-labelledby="registration-schedule-title" hidden>
                <h2 id="registration-schedule-title" class="public-schedule__title">
                    <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-clock"></use></svg>
                    <span>Horaires du cabinet</span>
                </h2>

                <ul class="public-schedule__days">
                    <li class="public-schedule__day" data-schedule-day="today">
                        <span class="public-schedule__when">Aujourd’hui</span>
                        <span class="public-schedule__status" data-schedule-status></span>
                        <span class="public-schedule__hours" data-schedule-hours></span>
                    </li>
                    <li class="public-schedule__day" data-schedule-day="tomorrow">
                        <span class="public-schedule__when">Demain</span>
                        <span class="public-schedule__status" data-schedule-status></span>
                        <span class="public-schedule__hours" data-schedule-hours></span>
                    </li>
                </ul>

                <div class="public-schedule__notes-wrap" id="registration-schedule-notes-wrap">
                    <h3 class="public-schedule__subtitle">À noter</h3>
                    <ul class="public-schedule__notes" id="registration-schedule-notes"></ul>
                </div>

                <details class="public-schedule__week" id="registration-schedule-week" hidden>
                    <summary>Voir les horaires de la semaine</summary>
                    <dl id="registration-schedule-week-list"></dl>
                </details>
            </section>
        </section>

        <footer class="public-registration-footer">
            <span>Service propulsé par <strong>MARKI</strong></span>
        </footer>
    </main>
</body>
</html>
