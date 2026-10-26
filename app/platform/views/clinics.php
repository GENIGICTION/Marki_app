<?php
/**
 * Administration MARKI > Structures et abonnements.
 * Variables attendues : $subscriptionRepository, $platformAdmin, $platformToday,
 * $platformGraceDays, $csrfToken.
 */

declare(strict_types=1);

$clinicSearch = trim((string) ($_GET['q'] ?? ''));
$clinics = $subscriptionRepository->listClinics($platformToday, $platformGraceDays, $clinicSearch);
$canManageSubscriptions = PlatformAuth::can($platformAdmin, 'subscriptions.manage');
$canSuspend = PlatformAuth::can($platformAdmin, 'clinics.suspend');
$canSeePayments = PlatformAuth::can($platformAdmin, 'subscriptions.manage');

$subscriptionLabels = [
    'none' => 'Sans date de fin',
    'active' => 'Actif',
    'ending' => 'Bientôt fini',
    'grace' => 'Délai de grâce',
    'expired' => 'Expiré — bloqué',
];

$counts = array_fill_keys(array_keys($subscriptionLabels), 0);
foreach ($clinics as $clinic) {
    $counts[$clinic['subscription']['status']]++;
}
?>
<section class="platform-card" aria-labelledby="clinics-title">
    <div class="platform-card-header platform-clinics-head">
        <div>
            <p class="platform-eyebrow">Abonnement par structure · <?= (int) $platformGraceDays ?> jours de grâce</p>
            <h2 id="clinics-title">Structures (<?= count($clinics) ?>)</h2>
        </div>
        <form method="get" class="platform-search">
            <input type="hidden" name="tab" value="clinics">
            <input type="search" name="q" value="<?= platformE($clinicSearch) ?>" placeholder="Nom, ville, wilaya…" aria-label="Rechercher une structure">
            <button type="submit" class="platform-button platform-button--ghost">Rechercher</button>
        </form>
    </div>

    <ul class="platform-sub-summary" aria-label="Répartition des abonnements">
        <?php foreach (['expired', 'grace', 'ending', 'active', 'none'] as $statusKey): ?>
            <li class="platform-sub is-<?= $statusKey ?>">
                <strong><?= (int) $counts[$statusKey] ?></strong>
                <span><?= platformE($subscriptionLabels[$statusKey]) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="platform-table-wrap">
        <table class="platform-table platform-clinics-table">
            <thead>
                <tr>
                    <th>Structure</th>
                    <th>Équipe</th>
                    <th>Abonnement</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($clinics === []): ?>
                    <tr><td colspan="5">Aucune structure.</td></tr>
                <?php endif; ?>
                <?php foreach ($clinics as $clinic): ?>
                    <?php
                    $sub = $clinic['subscription'];
                    $isSuspended = $clinic['status'] !== 'active';
                    ?>
                    <tr class="<?= $isSuspended ? 'is-suspended' : '' ?>">
                        <td>
                            <strong><?= platformE($clinic['name']) ?></strong>
                            <small><?= platformE(trim(($clinic['city'] ?: '') . ($clinic['wilaya'] ? ' · ' . $clinic['wilaya'] : ''), ' ·')) ?: '—' ?></small>
                            <small>Identifiant : <?= platformE($clinic['slug']) ?></small>
                            <?php if ($isSuspended): ?>
                                <span class="platform-status is-revoked">Suspendue</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= (int) $clinic['doctors_count'] ?> médecin<?= (int) $clinic['doctors_count'] > 1 ? 's' : '' ?>
                            <small><?= (int) $clinic['users_count'] ?> compte<?= (int) $clinic['users_count'] > 1 ? 's' : '' ?> actif<?= (int) $clinic['users_count'] > 1 ? 's' : '' ?></small>
                        </td>
                        <td>
                            <span class="platform-sub-badge is-<?= platformE($sub['status']) ?>"><?= platformE($subscriptionLabels[$sub['status']]) ?></span>
                            <?php if ($sub['ends_on'] !== null): ?>
                                <small>Fin : <?= platformE(markiFormatFrenchDate($sub['ends_on'])) ?></small>
                                <?php if ($sub['status'] === 'ending' || $sub['status'] === 'active'): ?>
                                    <small><?= (int) $sub['days_left'] ?> jour<?= (int) $sub['days_left'] > 1 ? 's' : '' ?> restant<?= (int) $sub['days_left'] > 1 ? 's' : '' ?></small>
                                <?php elseif ($sub['status'] === 'grace'): ?>
                                    <small>Bloquée le <?= platformE(markiFormatFrenchDate($sub['blocked_on'])) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $clinic['last_login_at'] ? platformE(markiFormatFrenchDateTime((string) $clinic['last_login_at'])) : '—' ?></td>
                        <td class="platform-clinic-actions">
                            <details class="platform-inline-form">
                                <summary class="platform-button">Renouveler</summary>
                                <form method="post" class="platform-form platform-form--compact">
                                    <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                    <input type="hidden" name="action" value="renew">
                                    <input type="hidden" name="tab" value="clinics">
                                    <input type="hidden" name="clinic_id" value="<?= (int) $clinic['id'] ?>">
                                    <label>
                                        <span>Durée</span>
                                        <select name="months">
                                            <?php foreach (ClinicSubscriptionRepository::DURATIONS as $months): ?>
                                                <option value="<?= $months ?>" <?= $months === 12 ? 'selected' : '' ?>><?= $months ?> mois</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Montant reçu (DA)</span>
                                        <input type="text" name="amount" inputmode="decimal" placeholder="Ex. 12000">
                                    </label>
                                    <label>
                                        <span>Paiement</span>
                                        <select name="payment_method">
                                            <?php foreach (ClinicSubscriptionRepository::PAYMENT_METHODS as $methodKey => $methodLabel): ?>
                                                <option value="<?= platformE($methodKey) ?>"><?= platformE($methodLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Note</span>
                                        <input type="text" name="note" maxlength="255" placeholder="Ex. reçu n° 42, payé au cabinet">
                                    </label>
                                    <p class="platform-help">
                                        <?php if ($sub['ends_on'] !== null && $sub['ends_on'] >= $platformToday): ?>
                                            Le renouvellement s’ajoute après le <?= platformE(markiFormatFrenchDate($sub['ends_on'])) ?>.
                                        <?php else: ?>
                                            Le renouvellement commence aujourd’hui.
                                        <?php endif; ?>
                                    </p>
                                    <button type="submit" class="platform-button">Enregistrer le paiement</button>
                                </form>
                            </details>

                            <?php if ($canManageSubscriptions || $canSuspend): ?>
                                <details class="platform-inline-form">
                                    <summary class="platform-button platform-button--ghost">Plus</summary>
                                    <?php if ($canManageSubscriptions): ?>
                                        <form method="post" class="platform-form platform-form--compact">
                                            <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                            <input type="hidden" name="action" value="clinic_set_end">
                                            <input type="hidden" name="tab" value="clinics">
                                            <input type="hidden" name="clinic_id" value="<?= (int) $clinic['id'] ?>">
                                            <label>
                                                <span>Corriger la date de fin</span>
                                                <input type="date" name="ends_on" value="<?= platformE($sub['ends_on'] ?? '') ?>">
                                            </label>
                                            <p class="platform-help">Vide = structure sans date de fin (jamais bloquée).</p>
                                            <button type="submit" class="platform-button platform-button--ghost">Enregistrer la date</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canSuspend): ?>
                                        <form method="post" class="platform-form platform-form--compact">
                                            <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                            <input type="hidden" name="action" value="clinic_toggle_status">
                                            <input type="hidden" name="tab" value="clinics">
                                            <input type="hidden" name="clinic_id" value="<?= (int) $clinic['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $isSuspended ? 'active' : 'suspended' ?>">
                                            <button type="submit" class="platform-button <?= $isSuspended ? '' : 'platform-button--danger' ?>">
                                                <?= $isSuspended ? 'Réactiver la structure' : 'Suspendre la structure' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canSeePayments): ?>
    <?php $payments = $subscriptionRepository->recentPayments(30); ?>
    <section class="platform-card" aria-labelledby="payments-title">
        <p class="platform-eyebrow">Historique</p>
        <h2 id="payments-title">Derniers paiements enregistrés</h2>
        <div class="platform-table-wrap">
            <table class="platform-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Structure</th>
                        <th>Période</th>
                        <th>Montant</th>
                        <th>Enregistré par</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments === []): ?>
                        <tr><td colspan="5">Aucun paiement enregistré.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= platformE(markiFormatFrenchDateTime((string) $payment['created_at'])) ?></td>
                            <td>
                                <strong><?= platformE($payment['clinic_name']) ?></strong>
                                <?php if ($payment['note']): ?><small><?= platformE($payment['note']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?= (int) $payment['months'] ?> mois
                                <small><?= platformE(markiFormatFrenchDate($payment['starts_on'])) ?> → <?= platformE(markiFormatFrenchDate($payment['ends_on'])) ?></small>
                            </td>
                            <td>
                                <?= $payment['amount'] !== null ? platformE(number_format((float) $payment['amount'], 0, ',', ' ')) . ' DA' : '—' ?>
                                <small><?= platformE(ClinicSubscriptionRepository::PAYMENT_METHODS[$payment['payment_method']] ?? $payment['payment_method']) ?></small>
                            </td>
                            <td>
                                <?= platformE($payment['admin_name'] ?: '—') ?>
                                <small><?= ($payment['admin_role'] ?? '') === 'agent' ? 'Agent' : 'Propriétaire' ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
