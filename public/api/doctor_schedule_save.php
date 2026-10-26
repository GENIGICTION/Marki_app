<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/support.php';

$context = null;

/**
 * Actions :
 * - weekly           : { days: [{day, slots:[{open, close}]}], general_note, enforce_hours }
 * - exception_save   : { date, mode: closed|custom_hours|note, slots, note }
 * - exception_delete : { date }
 */
function scheduleValidationError(array $errors): never
{
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => reset($errors) ?: 'Vérifiez les informations saisies.',
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function scheduleDate(mixed $value, string $today): string
{
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if ($date === false || $date->format('Y-m-d') !== $value) {
        scheduleValidationError(['date' => 'Choisissez une date valide.']);
    }

    $max = (new DateTimeImmutable($today))->modify('+365 days')->format('Y-m-d');
    if ($value < $today || $value > $max) {
        scheduleValidationError(['date' => 'Choisissez une date entre aujourd’hui et dans un an.']);
    }

    return $value;
}

function scheduleNote(mixed $value): string
{
    $note = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

    if (mb_strlen($note, 'UTF-8') > DoctorScheduleRepository::NOTE_MAX_LENGTH) {
        scheduleValidationError([
            'note' => 'La note ne doit pas dépasser '
                . DoctorScheduleRepository::NOTE_MAX_LENGTH . ' caractères.',
        ]);
    }

    return $note;
}

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/public_registration/PublicRegistrationRepository.php';
    require_once __DIR__ . '/../../app/public_registration/DoctorScheduleRepository.php';

    Auth::requireCapability($context, 'settings.manage_doctor');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];

    $clinicId = (int) $context['clinic_id'];
    $doctorId = (int) $context['doctor_id'];
    $today = (string) $context['today'];
    $action = (string) ($input['action'] ?? '');

    $publicRepository = new PublicRegistrationRepository();
    $publicRepository->ensureDoctorConfiguration($clinicId, $doctorId);
    $repository = new DoctorScheduleRepository($publicRepository->pdo());

    if ($action === 'weekly') {
        $days = is_array($input['days'] ?? null) ? $input['days'] : [];
        $week = array_fill(1, 7, []);

        foreach ($days as $dayInput) {
            $day = (int) ($dayInput['day'] ?? 0);
            if ($day < 1 || $day > 7) {
                continue;
            }

            [$slots, $error] = DoctorScheduleRepository::validateSlots($dayInput['slots'] ?? []);
            if ($error !== null) {
                scheduleValidationError([
                    'day_' . $day => DoctorScheduleRepository::dayLabel($day) . ' : ' . $error,
                ]);
            }
            $week[$day] = $slots;
        }

        $generalNote = scheduleNote($input['general_note'] ?? '');
        $enforceHours = filter_var($input['enforce_hours'] ?? false, FILTER_VALIDATE_BOOL);

        $repository->saveWeekly($clinicId, $doctorId, $week, $generalNote, $enforceHours);
        $publicRepository->logActivity(
            $clinicId,
            (int) $context['user_id'],
            'DOCTOR_SCHEDULE_UPDATED',
            'doctor',
            $doctorId,
            ['enforce_hours' => $enforceHours]
        );
        $message = 'Les horaires ont été enregistrés.';
    } elseif ($action === 'exception_save') {
        $date = scheduleDate($input['date'] ?? '', $today);
        $mode = (string) ($input['mode'] ?? '');
        $note = scheduleNote($input['note'] ?? '');
        $slots = [];

        if (!in_array($mode, DoctorScheduleRepository::MODES, true)) {
            scheduleValidationError(['mode' => 'Choisissez ce qui change ce jour-là.']);
        }

        if ($mode === DoctorScheduleRepository::MODE_CUSTOM_HOURS) {
            [$slots, $error] = DoctorScheduleRepository::validateSlots($input['slots'] ?? []);
            if ($error !== null || $slots === []) {
                scheduleValidationError(['slots' => $error ?? 'Indiquez au moins un créneau.']);
            }
        }

        if ($mode === DoctorScheduleRepository::MODE_NOTE && $note === '') {
            scheduleValidationError(['note' => 'Écrivez la note à afficher aux patients.']);
        }

        $repository->saveException($clinicId, $doctorId, $date, $mode, $slots, $note);
        $publicRepository->logActivity(
            $clinicId,
            (int) $context['user_id'],
            'DOCTOR_SCHEDULE_DAY_SAVED',
            'doctor',
            $doctorId,
            ['date' => $date, 'mode' => $mode]
        );
        $message = 'Le jour particulier a été enregistré.';
    } elseif ($action === 'exception_delete') {
        $date = trim((string) ($input['date'] ?? ''));
        if (DateTimeImmutable::createFromFormat('!Y-m-d', $date) === false) {
            scheduleValidationError(['date' => 'Date invalide.']);
        }

        $repository->deleteException($clinicId, $doctorId, $date);
        $publicRepository->logActivity(
            $clinicId,
            (int) $context['user_id'],
            'DOCTOR_SCHEDULE_DAY_DELETED',
            'doctor',
            $doctorId,
            ['date' => $date]
        );
        $message = 'Le jour particulier a été supprimé.';
    } else {
        scheduleValidationError(['action' => 'Action inconnue.']);
    }

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $repository->adminSchedule($clinicId, $doctorId, $today),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    markiJsonException(
        'doctor_schedule_save',
        $exception,
        $config,
        'Impossible d’enregistrer les horaires.',
        [
            'user_id' => $context['user_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
            'doctor_id' => $context['doctor_id'] ?? null,
        ]
    );
}
