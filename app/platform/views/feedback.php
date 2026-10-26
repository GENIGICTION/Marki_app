<?php
/**
 * Administration MARKI > Avis des médecins.
 * Variables attendues : $feedbackRepository, $csrfToken.
 */

declare(strict_types=1);

$feedbackFilter = (string) ($_GET['status'] ?? '');
$feedbackItems = $feedbackRepository->list($feedbackFilter);
$kindIcons = ['idea' => '💡', 'problem' => '⚠️', 'question' => '❓', 'other' => '💬'];
$pageLabels = [
    'dashboard' => 'Liste du jour',
    'patients' => 'Patients',
    'lists' => 'Toutes les listes',
    'settings' => 'Paramètres',
];
?>
<section class="platform-card" aria-labelledby="feedback-title">
    <div class="platform-card-header">
        <div>
            <p class="platform-eyebrow">Bouton « Donner mon avis »</p>
            <h2 id="feedback-title">Messages reçus</h2>
        </div>
        <nav class="platform-filter" aria-label="Filtrer les avis">
            <a href="?tab=feedback" class="<?= $feedbackFilter === '' ? 'is-active' : '' ?>">Tous</a>
            <?php foreach (PlatformFeedbackRepository::STATUSES as $statusKey => $statusLabel): ?>
                <a href="?tab=feedback&amp;status=<?= platformE($statusKey) ?>" class="<?= $feedbackFilter === $statusKey ? 'is-active' : '' ?>"><?= platformE($statusLabel) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($feedbackItems === []): ?>
        <p class="platform-help">Aucun message pour le moment.</p>
    <?php endif; ?>

    <ul class="platform-feedback-list">
        <?php foreach ($feedbackItems as $item): ?>
            <li class="platform-feedback is-<?= platformE($item['status']) ?>">
                <header class="platform-feedback__head">
                    <span class="platform-feedback__kind"><?= $kindIcons[$item['kind']] ?? '💬' ?> <?= platformE(PlatformFeedbackRepository::KINDS[$item['kind']] ?? $item['kind']) ?></span>
                    <span class="platform-status is-<?= $item['status'] === 'new' ? 'active' : 'used' ?>"><?= platformE(PlatformFeedbackRepository::STATUSES[$item['status']] ?? $item['status']) ?></span>
                    <time><?= platformE(markiFormatFrenchDateTime((string) $item['created_at'])) ?></time>
                </header>

                <p class="platform-feedback__message"><?= nl2br(platformE($item['message'])) ?></p>

                <p class="platform-feedback__from">
                    <strong><?= platformE($item['user_name'] ?: 'Utilisateur supprimé') ?></strong>
                    · <?= platformE($item['clinic_name']) ?>
                    <?php if ($item['doctor_name']): ?> · <?= platformE($item['doctor_name']) ?><?php endif; ?>
                    <?php if ($item['page']): ?> · page « <?= platformE($pageLabels[$item['page']] ?? $item['page']) ?> »<?php endif; ?>
                    <?php if ($item['viewport']): ?> · écran <?= platformE($item['viewport']) ?><?php endif; ?>
                    <br>
                    <?php if ($item['user_email']): ?><a href="mailto:<?= platformE($item['user_email']) ?>"><?= platformE($item['user_email']) ?></a><?php endif; ?>
                    <?php if ($item['user_phone']): ?> · <?= platformE($item['user_phone']) ?><?php endif; ?>
                </p>

                <div class="platform-feedback__actions">
                    <?php foreach (['read' => 'Marquer lu', 'done' => 'Marquer traité', 'new' => 'Remettre en nouveau'] as $nextStatus => $actionLabel): ?>
                        <?php if ($nextStatus !== $item['status']): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                <input type="hidden" name="action" value="feedback_status">
                                <input type="hidden" name="tab" value="feedback">
                                <input type="hidden" name="feedback_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="status" value="<?= platformE($nextStatus) ?>">
                                <button type="submit" class="platform-button platform-button--ghost"><?= platformE($actionLabel) ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
