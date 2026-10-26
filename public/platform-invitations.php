<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];
markiEnforcePlatformIpAllowlist($config);

require_once __DIR__ . '/../app/platform/StructureInvitationRepository.php';
require_once __DIR__ . '/../app/platform/PlatformAuth.php';
require_once __DIR__ . '/../app/platform/ClinicSubscriptionRepository.php';
require_once __DIR__ . '/../app/platform/PlatformFeedbackRepository.php';
require_once __DIR__ . '/../app/support.php';

function platformE(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function platformShortcutIcon(): string
{
    return '<svg viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="M12 3v11m0 0 4-4m-4 4-4-4"/>'
        . '<path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/>'
        . '</svg>';
}

function platformAbsoluteBaseUrl(array $config): string
{
    $basePath = rtrim(
        (string) ($config['app']['base_path'] ?? ''),
        '/'
    );

    return markiApplicationOrigin($config) . $basePath;
}

$platformLoginError = '';
$platformAdmin = null;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'platform_login'
) {
    try {
        Auth::validateCsrf((string) ($_POST['csrf_token'] ?? ''));
        PlatformAuth::attemptLogin(
            $config,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            !empty($_POST['remember'])
        );

        header(
            'Location: '
            . rtrim((string) $config['app']['base_path'], '/')
            . '/platform-invitations.php'
        );
        exit;
    } catch (PlatformAuthException | AuthException $exception) {
        $platformLoginError = $exception->getMessage();
    } catch (PDOException $exception) {
        $platformLoginError = str_contains($exception->getMessage(), 'platform_admins')
            ? 'La migration des comptes administrateurs MARKI n’est pas encore installée.'
            : 'Impossible de joindre la base de données. Vérifiez le fichier .env et le compte MySQL marki_app.';
    } catch (Throwable $exception) {
        $platformLoginError = (bool) ($config['app']['debug'] ?? false)
            ? $exception->getMessage()
            : 'Impossible d’ouvrir l’administration MARKI.';
    }
}

try {
    $platformAdmin = PlatformAuth::current($config);
} catch (Throwable $exception) {
    $platformLoginError = (bool) ($config['app']['debug'] ?? false)
        ? $exception->getMessage()
        : 'Impossible de vérifier la session administrateur.';
}

if ($platformAdmin === null) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="assets/icons/marki-app-192.png" type="image/png">
        <title>Administration MARKI</title>
        <link rel="stylesheet" href="assets/css/auth.css?v=20260802-platform4">
        <link rel="stylesheet" href="assets/css/password-toggle.css?v=20260802-platform4">
        <link rel="stylesheet" href="assets/css/platform-setup.css?v=20260802-platform4">
        <link rel="stylesheet" href="assets/css/desktop-density.css?v=20260803-final2">
        <link rel="stylesheet" href="assets/design-system/marki-theme.css?v=20260803-design-ready1">
</head>
    <body class="platform-page">
        <main class="platform-shell platform-shell--login">
            <section class="platform-card platform-login-card">
                <a
                    class="platform-icon-button platform-login-shortcut"
                    href="download-shortcut.php?type=platform"
                    aria-label="Créer le raccourci de l’administration MARKI"
                    title="Créer le raccourci MARKI sur le Bureau"
                    download
                >
                    <?= platformShortcutIcon() ?>
                </a>
                <p class="platform-eyebrow">Administration interne MARKI</p>
                <h1>Connexion à la plateforme</h1>
                <p>Utilisez votre compte administrateur MARKI pour créer et suivre les invitations des structures.</p>

                <?php if ($platformLoginError !== ''): ?>
                    <div class="platform-message is-error" role="alert">
                        <?= platformE($platformLoginError) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="platform-form">
                    <input type="hidden" name="csrf_token" value="<?= platformE($public['csrf_token']) ?>">
                    <input type="hidden" name="action" value="platform_login">

                    <label>
                        <span>Adresse courriel</span>
                        <input
                            type="email"
                            name="email"
                            autocomplete="username"
                            value="<?= platformE((string) ($_POST['email'] ?? '')) ?>"
                            required
                            autofocus
                        >
                    </label>

                    <label>
                        <span>Mot de passe</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <label class="platform-checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Se souvenir de cet appareil pendant 30 jours</span>
                    </label>

                    <button type="submit" class="platform-button">Ouvrir l’administration</button>
                </form>

                <p class="platform-help">Ces identifiants sont réservés à l’équipe MARKI. Les médecins et les secrétaires utilisent le lien de leur structure.</p>
            </section>
        </main>
        <script src="assets/js/password-toggle.js?v=20260802-platform4" defer></script>
    </body>
    </html>
    <?php
    exit;
}

$repository = new StructureInvitationRepository($config);
$platformAuditRepository = new PlatformAdminRepository();
$subscriptionRepository = new ClinicSubscriptionRepository();
$feedbackRepository = new PlatformFeedbackRepository();
$message = '';
$messageType = '';
$generatedLink = '';
$fieldErrors = [];

/*
| Onglets de l'administration. Un agent ne voit que les invitations et
| les structures (pour renouveler un abonnement).
*/
$platformTabs = [
    'invitations' => ['label' => 'Invitations', 'permission' => 'invitations'],
    'clinics' => ['label' => 'Structures et abonnements', 'permission' => 'clinics.view'],
    'feedback' => ['label' => 'Avis des médecins', 'permission' => 'feedback'],
    'agents' => ['label' => 'Agents', 'permission' => 'agents'],
];
$platformTabs = array_filter(
    $platformTabs,
    static fn(array $tab): bool => PlatformAuth::can($platformAdmin, $tab['permission'])
);
$tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'invitations');
if (!isset($platformTabs[$tab])) {
    $tab = array_key_first($platformTabs) ?? 'invitations';
}

$platformToday = (new DateTimeImmutable(
    'now',
    new DateTimeZone((string) ($config['app']['timezone'] ?? 'Africa/Algiers'))
))->format('Y-m-d');
$platformGraceDays = markiSubscriptionGraceDays($config);

$requirePlatformPermission = static function (string $permission) use ($platformAdmin): void {
    if (!PlatformAuth::can($platformAdmin, $permission)) {
        throw new AuthException('Votre compte n’a pas le droit de faire cette action.');
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::validateCsrf(
            (string) ($_POST['csrf_token'] ?? '')
        );

        $action = trim((string) ($_POST['action'] ?? 'create'));

        if ($action === 'logout_platform') {
            PlatformAuth::logout($config);
            header(
                'Location: '
                . rtrim((string) $config['app']['base_path'], '/')
                . '/platform-invitations.php'
            );
            exit;
        }

        if ($action === 'renew') {
            $requirePlatformPermission('subscriptions.renew');
            $clinicId = (int) ($_POST['clinic_id'] ?? 0);
            $amountRaw = str_replace([' ', ','], ['', '.'], trim((string) ($_POST['amount'] ?? '')));
            $amount = $amountRaw === '' ? null : (float) $amountRaw;
            $result = $subscriptionRepository->renew(
                $clinicId,
                (int) ($_POST['months'] ?? 0),
                $amount,
                (string) ($_POST['payment_method'] ?? 'cash'),
                trim((string) ($_POST['note'] ?? '')),
                (int) $platformAdmin['id'],
                $platformToday
            );
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                'SUBSCRIPTION_RENEWED',
                ['clinic_id' => $clinicId, 'months' => (int) ($_POST['months'] ?? 0), 'amount' => $amount] + $result
            );
            $message = 'Abonnement renouvelé jusqu’au ' . markiFormatFrenchDate($result['ends_on']) . '.';
            $messageType = 'success';
        } elseif ($action === 'clinic_set_end') {
            $requirePlatformPermission('subscriptions.manage');
            $clinicId = (int) ($_POST['clinic_id'] ?? 0);
            $endsOn = trim((string) ($_POST['ends_on'] ?? ''));
            if ($endsOn !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $endsOn) === false) {
                throw new InvalidArgumentException('Date de fin invalide.');
            }
            $subscriptionRepository->setEndDate($clinicId, $endsOn !== '' ? $endsOn : null);
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                'SUBSCRIPTION_END_DATE_SET',
                ['clinic_id' => $clinicId, 'ends_on' => $endsOn !== '' ? $endsOn : null]
            );
            $message = $endsOn !== ''
                ? 'Date de fin fixée au ' . markiFormatFrenchDate($endsOn) . '.'
                : 'Date de fin retirée : cette structure n’est plus soumise à abonnement.';
            $messageType = 'success';
        } elseif ($action === 'clinic_toggle_status') {
            $requirePlatformPermission('clinics.suspend');
            $clinicId = (int) ($_POST['clinic_id'] ?? 0);
            $activate = (string) ($_POST['status'] ?? '') === 'active';
            $subscriptionRepository->setStatus($clinicId, $activate);
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                $activate ? 'CLINIC_REACTIVATED' : 'CLINIC_SUSPENDED',
                ['clinic_id' => $clinicId]
            );
            $message = $activate ? 'Structure réactivée.' : 'Structure suspendue : plus de connexion ni de QR.';
            $messageType = 'success';
        } elseif ($action === 'feedback_status') {
            $requirePlatformPermission('feedback');
            $feedbackRepository->setStatus(
                (int) ($_POST['feedback_id'] ?? 0),
                (string) ($_POST['status'] ?? '')
            );
            $message = 'Avis mis à jour.';
            $messageType = 'success';
        } elseif ($action === 'agent_create') {
            $requirePlatformPermission('agents');
            $agentName = trim((string) ($_POST['full_name'] ?? ''));
            $agentEmail = trim((string) ($_POST['email'] ?? ''));
            $agentPassword = (string) ($_POST['password'] ?? '');
            $minimum = max(10, (int) ($config['auth']['password_min_length'] ?? 12));

            if ($agentName === '') {
                $fieldErrors['agent_full_name'] = 'Le nom est obligatoire.';
            }
            if (filter_var($agentEmail, FILTER_VALIDATE_EMAIL) === false) {
                $fieldErrors['agent_email'] = 'Adresse e-mail invalide.';
            }
            if (
                mb_strlen($agentPassword, 'UTF-8') < $minimum
                || !preg_match('/[A-Z]/', $agentPassword)
                || !preg_match('/[a-z]/', $agentPassword)
                || !preg_match('/\d/', $agentPassword)
            ) {
                $fieldErrors['agent_password'] = sprintf(
                    '%d caractères minimum, avec majuscule, minuscule et chiffre.',
                    $minimum
                );
            }
            if ($fieldErrors !== []) {
                throw new InvalidArgumentException('Vérifiez le formulaire de l’agent.');
            }

            $agentId = $platformAuditRepository->createAgent(
                $agentEmail,
                password_hash($agentPassword, PASSWORD_DEFAULT),
                $agentName
            );
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                'PLATFORM_AGENT_CREATED',
                ['agent_id' => $agentId, 'email' => mb_strtolower($agentEmail, 'UTF-8')]
            );
            $message = 'Agent créé. Transmettez-lui son adresse et son mot de passe : il se connecte sur cette même page.';
            $messageType = 'success';
        } elseif ($action === 'agent_toggle') {
            $requirePlatformPermission('agents');
            $agentId = (int) ($_POST['agent_id'] ?? 0);
            $activate = (string) ($_POST['status'] ?? '') === 'active';
            $platformAuditRepository->setAgentStatus($agentId, $activate);
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                $activate ? 'PLATFORM_AGENT_REACTIVATED' : 'PLATFORM_AGENT_DISABLED',
                ['agent_id' => $agentId]
            );
            $message = $activate ? 'Agent réactivé.' : 'Agent désactivé : il est déconnecté.';
            $messageType = 'success';
        } elseif ($action === 'revoke') {
            $requirePlatformPermission('invitations');
            $invitationId = (int) ($_POST['invitation_id'] ?? 0);
            $repository->revokeInvitation($invitationId);
            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                'STRUCTURE_INVITATION_REVOKED',
                ['invitation_id' => $invitationId]
            );
            $message = 'Invitation révoquée.';
            $messageType = 'success';
        } else {
            $requirePlatformPermission('invitations');
            $invitation = $repository->createInvitation(
                $_POST['recipient_label'] ?? null,
                $_POST['recipient_email'] ?? null,
                (int) (
                    $_POST['expiry_hours']
                    ?? $config['platform']['invitation_expiry_hours']
                    ?? 72
                )
            );

            $generatedLink = platformAbsoluteBaseUrl($config)
                . '/activate-structure.php?token='
                . rawurlencode((string) $invitation['token']);

            $platformAuditRepository->log(
                (int) $platformAdmin['id'],
                'STRUCTURE_INVITATION_CREATED',
                [
                    'invitation_id' => (int) $invitation['id'],
                    'recipient_email' => $invitation['recipient_email'],
                    'expires_at' => $invitation['expires_at'],
                ]
            );

            $message = 'Invitation créée. Le lien reste disponible dans le tableau tant que l’invitation est active.';
            $messageType = 'success';
        }
    } catch (StructureActivationValidationException $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
        $fieldErrors = $exception->errors();
    } catch (AuthException | InvalidArgumentException $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    } catch (Throwable $exception) {
        $message = (bool) ($config['app']['debug'] ?? false)
            ? $exception->getMessage()
            : 'Impossible de traiter cette demande.';
        $messageType = 'error';
    }
}

$invitations = array_map(
    static function (array $invitation) use ($config): array {
        $token = (string) ($invitation['token'] ?? '');
        $invitation['public_url'] = $token !== ''
            ? platformAbsoluteBaseUrl($config)
                . '/activate-structure.php?token='
                . rawurlencode($token)
            : null;

        return $invitation;
    },
    $repository->listRecent()
);
$csrfToken = Auth::csrfToken();
$defaultExpiry = (int) (
    $config['platform']['invitation_expiry_hours']
    ?? 72
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/marki-app-192.png" type="image/png">
    <title>Administration — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260731-setup1">
    <link rel="stylesheet" href="assets/css/platform-setup.css?v=20260802-platform4">
    <link rel="stylesheet" href="assets/css/desktop-density.css?v=20260803-final2">
    <link rel="stylesheet" href="assets/design-system/marki-theme.css?v=20260803-design-ready1">
    <link rel="stylesheet" href="assets/css/platform-admin.css?v=20261026-p5b">
</head>
<body class="platform-page">
    <main class="platform-shell">
        <header class="platform-header">
            <div>
                <p class="platform-eyebrow">Administration interne MARKI</p>
                <h1><?= platformE($platformTabs[$tab]['label'] ?? 'Administration') ?></h1>
                <p><?= platformE([
                    'invitations' => 'Générez un lien unique pour le médecin responsable d’un cabinet ou d’une clinique.',
                    'clinics' => 'Suivez les abonnements des structures et enregistrez les renouvellements.',
                    'feedback' => 'Les messages envoyés par les médecins avec le bouton « Donner mon avis ».',
                    'agents' => 'Les personnes qui travaillent avec MARKI : créer une structure et renouveler un abonnement.',
                ][$tab] ?? '') ?></p>
            </div>

            <div class="platform-header-actions">
                <a
                    class="platform-icon-button"
                    href="download-shortcut.php?type=platform"
                    aria-label="Installer le raccourci de l’administration MARKI"
                    title="Installer le raccourci MARKI sur le Bureau"
                    download
                >
                    <?= platformShortcutIcon() ?>
                </a>
                <div class="platform-admin-identity" aria-label="Compte administrateur connecté">
                    <strong><?= platformE((string) $platformAdmin['full_name']) ?></strong>
                    <small><?= platformE((string) $platformAdmin['email']) ?> · <?= ($platformAdmin['role'] ?? 'owner') === 'agent' ? 'Agent' : 'Propriétaire' ?></small>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                    <input type="hidden" name="action" value="logout_platform">
                    <button class="platform-button platform-button--ghost" type="submit">Se déconnecter</button>
                </form>
            </div>
        </header>

        <?php $newFeedbackCount = isset($platformTabs['feedback']) ? $feedbackRepository->countNew() : 0; ?>
        <nav class="platform-tabs" aria-label="Sections de l’administration">
            <?php foreach ($platformTabs as $tabKey => $tabInfo): ?>
                <a
                    href="?tab=<?= platformE($tabKey) ?>"
                    class="platform-tab<?= $tabKey === $tab ? ' is-active' : '' ?>"
                    <?= $tabKey === $tab ? 'aria-current="page"' : '' ?>
                >
                    <?= platformE($tabInfo['label']) ?>
                    <?php if ($tabKey === 'feedback' && $newFeedbackCount > 0): ?>
                        <span class="platform-tab__badge"><?= $newFeedbackCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($message !== ''): ?>
            <div class="platform-message is-<?= platformE($messageType) ?>" role="status">
                <?= platformE($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'clinics'): ?>
            <?php require __DIR__ . '/../app/platform/views/clinics.php'; ?>
        <?php elseif ($tab === 'feedback'): ?>
            <?php require __DIR__ . '/../app/platform/views/feedback.php'; ?>
        <?php elseif ($tab === 'agents'): ?>
            <?php require __DIR__ . '/../app/platform/views/agents.php'; ?>
        <?php else: ?>

        <?php if ($generatedLink !== ''): ?>
            <section class="platform-card platform-link-card" aria-labelledby="generated-link-title">
                <div>
                    <p class="platform-eyebrow">Lien créé</p>
                    <h2 id="generated-link-title">Invitation à transmettre</h2>
                </div>
                <div class="platform-copy-row">
                    <input id="generated-invitation-link" type="text" readonly value="<?= platformE($generatedLink) ?>">
                    <button type="button" class="platform-button" id="copy-invitation-link">Copier le lien</button>
                </div>
                <p class="platform-help">Toute personne qui possède ce lien peut créer une seule structure et son premier compte médecin administrateur avant son expiration.</p>
            </section>
        <?php endif; ?>

        <div class="platform-grid">
            <section class="platform-card" aria-labelledby="new-invitation-title">
                <p class="platform-eyebrow">Nouvelle invitation</p>
                <h2 id="new-invitation-title">Préparer l’accès du responsable</h2>

                <form method="post" class="platform-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">

                    <label>
                        <span>Nom du destinataire</span>
                        <input type="text" name="recipient_label" value="<?= platformE($_POST['recipient_label'] ?? '') ?>" placeholder="Ex. Dr Samir Haddad" required>
                        <?php if (isset($fieldErrors['recipient_label'])): ?>
                            <small class="platform-field-error"><?= platformE((string) $fieldErrors['recipient_label']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label>
                        <span>Courriel du destinataire</span>
                        <input type="email" name="recipient_email" value="<?= platformE($_POST['recipient_email'] ?? '') ?>" placeholder="docteur@cabinet.dz" required>
                        <?php if (isset($fieldErrors['recipient_email'])): ?>
                            <small class="platform-field-error"><?= platformE((string) $fieldErrors['recipient_email']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label>
                        <span>Validité du lien</span>
                        <select name="expiry_hours">
                            <?php foreach ([24, 48, 72, 120, 168] as $hours): ?>
                                <option value="<?= $hours ?>" <?= $hours === $defaultExpiry ? 'selected' : '' ?>>
                                    <?= $hours ?> heures
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="platform-button">Générer l’invitation</button>
                </form>
            </section>

            <section class="platform-card platform-card--explanation" aria-labelledby="logic-title">
                <p class="platform-eyebrow">Logique V1</p>
                <h2 id="logic-title">Ce que fera le médecin invité</h2>
                <ol class="platform-steps">
                    <li>Il ouvre le lien personnel.</li>
                    <li>Il renseigne son cabinet ou sa clinique.</li>
                    <li>Il crée son propre mot de passe.</li>
                    <li>MARKI crée son compte <strong>médecin administrateur</strong>.</li>
                    <li>Il se connecte ensuite et crée ses médecins et secrétaires dans <strong>Équipe et accès</strong>.</li>
                </ol>
            </section>
        </div>

        <section class="platform-card" aria-labelledby="recent-invitations-title">
            <div class="platform-card-header">
                <div>
                    <p class="platform-eyebrow">Suivi minimal</p>
                    <h2 id="recent-invitations-title">Invitations récentes</h2>
                </div>
            </div>

            <div class="platform-table-wrap">
                <table class="platform-table">
                    <thead>
                        <tr>
                            <th>Destinataire</th>
                            <th>Créée</th>
                            <th>Expiration</th>
                            <th>État</th>
                            <th>Structure créée</th>
                            <th>Lien</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invitations === []): ?>
                            <tr><td colspan="7">Aucune invitation.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invitations as $invitation): ?>
                                <?php
                                $statusLabels = [
                                    'active' => 'Active',
                                    'used' => 'Utilisée',
                                    'expired' => 'Expirée',
                                    'revoked' => 'Révoquée',
                                ];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= platformE($invitation['recipient_label'] ?: 'Non précisé') ?></strong>
                                        <small><?= platformE($invitation['recipient_email'] ?: '') ?></small>
                                    </td>
                                    <td><?= platformE(markiFormatFrenchDateTime((string) $invitation['created_at'])) ?></td>
                                    <td><?= platformE(markiFormatFrenchDateTime((string) $invitation['expires_at'])) ?></td>
                                    <td><span class="platform-status is-<?= platformE($invitation['status']) ?>"><?= platformE($statusLabels[$invitation['status']] ?? $invitation['status']) ?></span></td>
                                    <td>
                                        <?php if ($invitation['clinic_name']): ?>
                                            <strong><?= platformE($invitation['clinic_name']) ?></strong>
                                            <small><?= platformE($invitation['activated_by_name'] ?: '') ?></small>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitation['status'] === 'active' && $invitation['public_url']): ?>
                                            <button
                                                type="button"
                                                class="platform-button platform-button--ghost"
                                                data-copy-invitation-link="<?= platformE((string) $invitation['public_url']) ?>"
                                            >Copier</button>
                                        <?php elseif ($invitation['status'] === 'active'): ?>
                                            <small>Lien ancien non récupérable</small>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitation['status'] === 'active'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                                <input type="hidden" name="action" value="revoke">
                                                <input type="hidden" name="invitation_id" value="<?= (int) $invitation['id'] ?>">
                                                <button type="submit" class="platform-button platform-button--danger">Révoquer</button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <script nonce="<?= platformE($public['csp_nonce']) ?>">
        document.getElementById('copy-invitation-link')?.addEventListener('click', async () => {
            const input = document.getElementById('generated-invitation-link');
            if (!input) return;

            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                input.select();
                document.execCommand('copy');
            }

            const button = document.getElementById('copy-invitation-link');
            if (button) {
                const previous = button.textContent;
                button.textContent = 'Copié';
                window.setTimeout(() => {
                    button.textContent = previous;
                }, 1600);
            }
        });

        document.querySelectorAll('[data-copy-invitation-link]').forEach(button => {
            button.addEventListener('click', async () => {
                const value = button.dataset.copyInvitationLink || '';
                if (!value) return;

                try {
                    await navigator.clipboard.writeText(value);
                } catch (error) {
                    const input = document.createElement('textarea');
                    input.value = value;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.append(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                }

                const previous = button.textContent;
                button.textContent = 'Copié';
                window.setTimeout(() => {
                    button.textContent = previous;
                }, 1600);
            });
        });
    </script>
</body>
</html>
