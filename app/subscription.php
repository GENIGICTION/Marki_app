<?php

declare(strict_types=1);

/**
 * État de l'abonnement d'une structure.
 *
 * - none     : aucune date de fin (structure non soumise à abonnement) ;
 * - active   : abonnement en cours ;
 * - ending   : se termine dans 14 jours ou moins (bandeau d'alerte) ;
 * - grace    : date dépassée, encore utilisable pendant le délai de grâce ;
 * - expired  : délai de grâce dépassé → connexion et QR bloqués.
 */
function markiSubscriptionState(
    ?string $endsOn,
    string $today,
    int $graceDays = 7
): array {
    $endsOn = $endsOn !== null ? trim($endsOn) : '';

    if ($endsOn === '') {
        return [
            'status' => 'none',
            'ends_on' => null,
            'blocked_on' => null,
            'days_left' => null,
            'blocked' => false,
        ];
    }

    $todayDate = new DateTimeImmutable($today);
    $endDate = new DateTimeImmutable($endsOn);
    $blockedOn = $endDate->modify('+' . ($graceDays + 1) . ' days');
    $daysLeft = (int) $todayDate->diff($endDate)->format('%r%a');

    if ($todayDate >= $blockedOn) {
        $status = 'expired';
    } elseif ($daysLeft < 0) {
        $status = 'grace';
    } elseif ($daysLeft <= 14) {
        $status = 'ending';
    } else {
        $status = 'active';
    }

    return [
        'status' => $status,
        'ends_on' => $endDate->format('Y-m-d'),
        'blocked_on' => $blockedOn->format('Y-m-d'),
        'days_left' => $daysLeft,
        'blocked' => $status === 'expired',
    ];
}

function markiSubscriptionGraceDays(array $config): int
{
    return max(0, (int) ($config['subscription']['grace_days'] ?? 7));
}
