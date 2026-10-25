<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/support.php';

$context = null;

/*
| Inscriptions pour les jours à venir (inscription QR à l'avance).
| Retourne les listes futures du médecin actif et leurs patients en attente.
*/
try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    Auth::requireCapability($context, 'queue.view');

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT
            q.id AS queue_id,
            q.queue_date,
            qe.id AS entry_id,
            qe.position_number,
            qe.display_name,
            qe.phone,
            qe.source,
            qe.created_at
         FROM queues q
         INNER JOIN queue_entries qe
            ON qe.queue_id = q.id
           AND qe.status IN ('waiting', 'called')
         WHERE q.clinic_id = :clinic_id
           AND q.doctor_id = :doctor_id
           AND q.queue_date > :today
         ORDER BY q.queue_date ASC, qe.position_number ASC
         LIMIT 500"
    );
    $stmt->execute([
        ':clinic_id' => (int) $context['clinic_id'],
        ':doctor_id' => (int) $context['doctor_id'],
        ':today' => (string) $context['today'],
    ]);

    $days = [];
    foreach ($stmt->fetchAll() as $row) {
        $date = (string) $row['queue_date'];
        $days[$date] ??= [
            'queue_id' => (int) $row['queue_id'],
            'date' => $date,
            'patients' => [],
        ];
        $days[$date]['patients'][] = [
            'id' => (int) $row['entry_id'],
            'position_number' => (int) $row['position_number'],
            'name' => (string) $row['display_name'],
            'phone' => $row['phone'],
            'source' => (string) $row['source'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'today' => (string) $context['today'],
            'days' => array_values($days),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    markiJsonException(
        'queues_upcoming',
        $exception,
        $config,
        'Impossible de charger les inscriptions à venir.',
        [
            'user_id' => $context['user_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
            'doctor_id' => $context['doctor_id'] ?? null,
        ]
    );
}
