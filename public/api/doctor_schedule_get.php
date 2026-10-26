<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/support.php';

$context = null;

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/public_registration/DoctorScheduleRepository.php';

    Auth::requireCapability($context, 'settings.manage_doctor');

    $repository = new DoctorScheduleRepository();

    echo json_encode([
        'ok' => true,
        'data' => $repository->adminSchedule(
            (int) $context['clinic_id'],
            (int) $context['doctor_id'],
            (string) $context['today']
        ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    markiJsonException(
        'doctor_schedule_get',
        $exception,
        $config,
        'Impossible de charger les horaires.',
        [
            'user_id' => $context['user_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
            'doctor_id' => $context['doctor_id'] ?? null,
        ]
    );
}
