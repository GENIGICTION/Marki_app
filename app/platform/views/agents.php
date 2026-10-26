<?php
/**
 * Administration MARKI > Agents.
 * Variables attendues : $platformAuditRepository, $platformAdmin, $fieldErrors, $csrfToken.
 */

declare(strict_types=1);

$platformAccounts = $platformAuditRepository->listAdmins();
$agentMinimum = max(10, (int) ($config['auth']['password_min_length'] ?? 12));
?>
<div class="platform-grid">
    <section class="platform-card" aria-labelledby="new-agent-title">
        <p class="platform-eyebrow">Nouveau compte</p>
        <h2 id="new-agent-title">Ajouter un agent</h2>

        <form method="post" class="platform-form" novalidate autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
            <input type="hidden" name="action" value="agent_create">
            <input type="hidden" name="tab" value="agents">

            <label>
                <span>Nom complet</span>
                <input type="text" name="full_name" value="<?= platformE(($_POST['action'] ?? '') === 'agent_create' ? (string) ($_POST['full_name'] ?? '') : '') ?>" placeholder="Ex. Yacine, agent Alger" required>
                <?php if (isset($fieldErrors['agent_full_name'])): ?><small class="platform-field-error"><?= platformE($fieldErrors['agent_full_name']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Courriel de connexion</span>
                <input type="email" name="email" value="<?= platformE(($_POST['action'] ?? '') === 'agent_create' ? (string) ($_POST['email'] ?? '') : '') ?>" placeholder="agent@marki.dz" required>
                <?php if (isset($fieldErrors['agent_email'])): ?><small class="platform-field-error"><?= platformE($fieldErrors['agent_email']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Mot de passe</span>
                <input type="password" name="password" autocomplete="new-password" required>
                <small class="platform-help"><?= (int) $agentMinimum ?> caractères minimum, avec majuscule, minuscule et chiffre.</small>
                <?php if (isset($fieldErrors['agent_password'])): ?><small class="platform-field-error"><?= platformE($fieldErrors['agent_password']) ?></small><?php endif; ?>
            </label>

            <button type="submit" class="platform-button">Créer l’agent</button>
        </form>
    </section>

    <section class="platform-card platform-card--explanation" aria-labelledby="agent-rights-title">
        <p class="platform-eyebrow">Droits</p>
        <h2 id="agent-rights-title">Ce que peut faire un agent</h2>
        <ul class="platform-steps">
            <li><strong>Renouveler un abonnement</strong> après avoir encaissé le paiement chez le médecin.</li>
            <li><strong>Créer une structure</strong> en générant une invitation d’activation.</li>
            <li>Il ne voit <strong>pas</strong> les avis, l’historique des paiements ni les agents, et ne peut pas suspendre une structure.</li>
            <li>Chacune de ses actions est enregistrée avec son nom.</li>
        </ul>
    </section>
</div>

<section class="platform-card" aria-labelledby="accounts-title">
    <p class="platform-eyebrow">Comptes</p>
    <h2 id="accounts-title">Équipe MARKI</h2>
    <div class="platform-table-wrap">
        <table class="platform-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Rôle</th>
                    <th>État</th>
                    <th>Dernière connexion</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($platformAccounts as $account): ?>
                    <tr>
                        <td>
                            <strong><?= platformE($account['full_name']) ?></strong>
                            <small><?= platformE($account['email']) ?></small>
                        </td>
                        <td><?= $account['role'] === 'agent' ? 'Agent' : 'Propriétaire' ?></td>
                        <td>
                            <span class="platform-status is-<?= $account['status'] === 'active' ? 'active' : 'revoked' ?>">
                                <?= $account['status'] === 'active' ? 'Actif' : 'Désactivé' ?>
                            </span>
                        </td>
                        <td><?= $account['last_login_at'] ? platformE(markiFormatFrenchDateTime((string) $account['last_login_at'])) : 'Jamais' ?></td>
                        <td>
                            <?php if ($account['role'] === 'agent'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                    <input type="hidden" name="action" value="agent_toggle">
                                    <input type="hidden" name="tab" value="agents">
                                    <input type="hidden" name="agent_id" value="<?= (int) $account['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $account['status'] === 'active' ? 'disabled' : 'active' ?>">
                                    <button type="submit" class="platform-button <?= $account['status'] === 'active' ? 'platform-button--danger' : '' ?>">
                                        <?= $account['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
