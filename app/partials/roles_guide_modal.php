<?php
/**
 * Fenêtre « Qui fait quoi ? » : explication des rôles en 4 étapes.
 * Ouverte par tout élément [data-open-roles-guide] (voir assets/js/roles-guide.js).
 */

declare(strict_types=1);
?>
<div id="roles-modal" class="roles-modal" hidden aria-hidden="true">
    <button type="button" class="roles-modal__backdrop" data-close-roles-guide aria-label="Fermer" tabindex="-1"></button>

    <div class="roles-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="roles-modal-title">
        <header class="roles-modal__header">
            <div>
                <p class="roles-modal__eyebrow">Qui fait quoi ? · <span data-roles-step-label>1 / 4</span></p>
                <h2 id="roles-modal-title" data-roles-title>Comment ça marche ?</h2>
            </div>
            <button type="button" class="roles-modal__close" data-close-roles-guide aria-label="Fermer">
                <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-close"></use></svg>
            </button>
        </header>

        <div class="roles-modal__body">
            <!-- Étape 1 : l'organisation -->
            <section class="roles-step" data-roles-step data-title="Comment ça marche ?">
                <p class="roles-step__lead">Une clinique peut avoir <strong>plusieurs médecins</strong>. Chaque médecin a <strong>sa propre liste d’attente</strong> et <strong>son propre QR code</strong>.</p>

                <div class="roles-flow" aria-hidden="true">
                    <div class="roles-flow__node roles-flow__node--clinic">
                        <svg class="mk-icon" aria-hidden="true"><use href="#mk-building"></use></svg>
                        <span>Votre clinique</span>
                    </div>
                    <span class="roles-flow__line"></span>
                    <div class="roles-flow__row">
                        <div class="roles-flow__node roles-flow__node--doctor">
                            <svg class="mk-icon" aria-hidden="true"><use href="#mk-stethoscope"></use></svg>
                            <span>Dr Amrani</span>
                            <small>sa liste · son QR</small>
                        </div>
                        <div class="roles-flow__node roles-flow__node--doctor">
                            <svg class="mk-icon" aria-hidden="true"><use href="#mk-stethoscope"></use></svg>
                            <span>Dr Benali</span>
                            <small>sa liste · son QR</small>
                        </div>
                    </div>
                    <span class="roles-flow__line"></span>
                    <div class="roles-flow__node roles-flow__node--staff">
                        <svg class="mk-icon" aria-hidden="true"><use href="#mk-user"></use></svg>
                        <span>Secrétaire</span>
                        <small>peut aider les deux médecins</small>
                    </div>
                </div>

                <p class="roles-step__note">
                    <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-info"></use></svg>
                    <span>Une secrétaire peut travailler pour <strong>un seul</strong> médecin ou pour <strong>plusieurs</strong>. Elle passe de l’un à l’autre avec le menu en haut de l’écran.</span>
                </p>
            </section>

            <!-- Étape 2 : les 3 rôles -->
            <section class="roles-step" data-roles-step data-title="Les 3 types de compte" hidden>
                <p class="roles-step__lead">Chaque personne a son propre compte. Il existe 3 types de compte :</p>

                <ul class="roles-cards">
                    <li class="roles-card roles-card--admin">
                        <span class="roles-card__icon" aria-hidden="true"><svg class="mk-icon"><use href="#mk-shield-check"></use></svg></span>
                        <div>
                            <strong>Administrateur</strong>
                            <p>Le responsable de la clinique, souvent le médecin principal. <b>C’est le seul</b> qui peut ajouter des comptes et choisir les accès de chacun.</p>
                        </div>
                    </li>
                    <li class="roles-card roles-card--doctor">
                        <span class="roles-card__icon" aria-hidden="true"><svg class="mk-icon"><use href="#mk-stethoscope"></use></svg></span>
                        <div>
                            <strong>Médecin</strong>
                            <p>Gère sa liste, ses patients et son QR code. Il ne voit que <b>son propre espace</b>.</p>
                        </div>
                    </li>
                    <li class="roles-card roles-card--staff">
                        <span class="roles-card__icon" aria-hidden="true"><svg class="mk-icon"><use href="#mk-user"></use></svg></span>
                        <div>
                            <strong>Secrétaire</strong>
                            <p>Gère la liste des médecins qu’on lui a attribués. Ce qu’elle peut voir est choisi par l’administrateur.</p>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Étape 3 : les niveaux d'accès du secrétariat -->
            <section class="roles-step" data-roles-step data-title="Ce que voit une secrétaire" hidden>
                <p class="roles-step__lead">Quand l’administrateur crée une secrétaire, il choisit <strong>un niveau d’accès</strong> :</p>

                <ol class="roles-levels">
                    <li class="roles-level">
                        <span class="roles-level__num">1</span>
                        <div>
                            <strong>Liste du jour seulement</strong>
                            <p>Ajouter des patients, les appeler, les marquer terminés.</p>
                        </div>
                    </li>
                    <li class="roles-level">
                        <span class="roles-level__num">2</span>
                        <div>
                            <strong>Liste, patients et historique</strong>
                            <p>Tout le niveau 1, <b>plus</b> les fiches patients et les anciennes listes.</p>
                        </div>
                    </li>
                    <li class="roles-level">
                        <span class="roles-level__num">3</span>
                        <div>
                            <strong>Accès étendu</strong>
                            <p>Tout le niveau 2, <b>plus</b> la consultation des paramètres.</p>
                        </div>
                    </li>
                </ol>

                <p class="roles-step__note">
                    <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-lock"></use></svg>
                    <span>Une secrétaire ne peut jamais modifier le QR code, le profil du médecin ni les comptes.</span>
                </p>
            </section>

            <!-- Étape 4 : le compte connecté -->
            <section class="roles-step" data-roles-step data-title="Et vous ?" hidden>
                <p class="roles-step__lead">Vous êtes connecté en tant que <strong class="roles-me__role" data-roles-my-role>—</strong>. Voici ce que vous pouvez faire :</p>

                <ul class="roles-abilities" data-roles-my-abilities></ul>

                <div class="roles-me__actions" data-roles-admin-only hidden>
                    <button type="button" class="roles-action roles-action--doctor" data-roles-new-account="doctor">
                        <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-stethoscope"></use></svg>
                        <span>Ajouter un médecin</span>
                    </button>
                    <button type="button" class="roles-action roles-action--staff" data-roles-new-account="secretary">
                        <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-user-plus"></use></svg>
                        <span>Ajouter une secrétaire</span>
                    </button>
                </div>
                <p class="roles-step__note" data-roles-non-admin-only hidden>
                    Pour ajouter un compte ou changer un accès, demandez à l’administrateur de votre clinique.
                </p>
            </section>
        </div>

        <footer class="roles-modal__footer">
            <button type="button" class="roles-nav roles-nav--prev" data-roles-prev>Précédent</button>
            <div class="roles-dots" data-roles-dots aria-hidden="true"></div>
            <button type="button" class="roles-nav roles-nav--next" data-roles-next>Suivant</button>
        </footer>
    </div>
</div>
