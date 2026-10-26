<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

/**
 * Horaires du médecin, jours particuliers et notes affichées aux patients.
 *
 * Tables utilisées (déjà présentes dans le schéma) :
 * - doctor_public_registration_hours      : horaires de la semaine (1 = lundi … 7 = dimanche)
 * - doctor_public_registration_exceptions : une date précise (fermé, horaires spéciaux ou simple note)
 * - doctor_public_registration_messages   : note générale (code schedule_general_note)
 * - doctor_public_registration_settings   : automatic_schedule_enabled (bloquer hors horaires)
 */
final class DoctorScheduleRepository
{
    public const MODE_CLOSED = 'closed';
    public const MODE_CUSTOM_HOURS = 'custom_hours';
    public const MODE_NOTE = 'note';

    public const MODES = [
        self::MODE_CLOSED,
        self::MODE_CUSTOM_HOURS,
        self::MODE_NOTE,
    ];

    public const GENERAL_NOTE_CODE = 'schedule_general_note';
    public const MAX_SLOTS_PER_DAY = 2;
    public const NOTE_MAX_LENGTH = 500;

    private const DAY_LABELS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    private const MONTH_LABELS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    /* =======================================================
       LECTURE
       ======================================================= */

    /**
     * Horaires de la semaine : [1 => [['open' => '08:00', 'close' => '12:00']], …, 7 => []].
     */
    public function weeklyHours(int $clinicId, int $doctorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT day_of_week, registration_open_time, registration_close_time
             FROM doctor_public_registration_hours
             WHERE clinic_id = :clinic_id
               AND doctor_id = :doctor_id
               AND is_active = 1
             ORDER BY day_of_week ASC, slot_order ASC"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        $week = array_fill_keys(array_keys(self::DAY_LABELS), []);
        foreach ($stmt->fetchAll() as $row) {
            $day = (int) $row['day_of_week'];
            if (!isset($week[$day])) {
                continue;
            }

            $week[$day][] = [
                'open' => self::shortTime((string) $row['registration_open_time']),
                'close' => self::shortTime((string) $row['registration_close_time']),
            ];
        }

        return $week;
    }

    /**
     * Jours particuliers entre deux dates incluses, indexés par date (Y-m-d).
     */
    public function exceptionsBetween(
        int $clinicId,
        int $doctorId,
        string $fromDate,
        string $toDate
    ): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                exception_date,
                mode,
                slot_order,
                registration_open_time,
                registration_close_time,
                reason_code,
                public_message_override
             FROM doctor_public_registration_exceptions
             WHERE clinic_id = :clinic_id
               AND doctor_id = :doctor_id
               AND is_active = 1
               AND exception_date BETWEEN :from_date AND :to_date
             ORDER BY exception_date ASC, slot_order ASC"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        $exceptions = [];
        foreach ($stmt->fetchAll() as $row) {
            $date = (string) $row['exception_date'];

            if (!isset($exceptions[$date])) {
                $exceptions[$date] = [
                    'date' => $date,
                    'mode' => self::normalizeMode((string) $row['mode']),
                    'slots' => [],
                    'reason_code' => $row['reason_code'],
                    'note' => trim((string) ($row['public_message_override'] ?? '')),
                ];
            }

            if (
                $row['registration_open_time'] !== null
                && $row['registration_close_time'] !== null
            ) {
                $exceptions[$date]['slots'][] = [
                    'open' => self::shortTime((string) $row['registration_open_time']),
                    'close' => self::shortTime((string) $row['registration_close_time']),
                ];
            }

            if (
                $exceptions[$date]['note'] === ''
                && trim((string) ($row['public_message_override'] ?? '')) !== ''
            ) {
                $exceptions[$date]['note'] =
                    trim((string) $row['public_message_override']);
            }
        }

        return $exceptions;
    }

    public function generalNote(int $clinicId, int $doctorId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT message_text
             FROM doctor_public_registration_messages
             WHERE clinic_id = :clinic_id
               AND doctor_id = :doctor_id
               AND message_code = :code
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':code' => self::GENERAL_NOTE_CODE,
        ]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    public function enforceHours(int $clinicId, int $doctorId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT automatic_schedule_enabled
             FROM doctor_public_registration_settings
             WHERE clinic_id = :clinic_id
               AND doctor_id = :doctor_id
             LIMIT 1"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Tout ce dont l'écran Paramètres a besoin.
     */
    public function adminSchedule(
        int $clinicId,
        int $doctorId,
        string $today
    ): array {
        $week = $this->weeklyHours($clinicId, $doctorId);
        $until = (new DateTimeImmutable($today))->modify('+365 days')->format('Y-m-d');

        return [
            'today' => $today,
            'weekly' => self::weekForOutput($week),
            'exceptions' => array_values(
                $this->exceptionsBetween($clinicId, $doctorId, $today, $until)
            ),
            'general_note' => $this->generalNote($clinicId, $doctorId),
            'enforce_hours' => $this->enforceHours($clinicId, $doctorId),
        ];
    }

    /**
     * Résumé public : aujourd'hui, demain, jours particuliers à venir,
     * horaires de la semaine et note générale.
     */
    public function publicSchedule(
        int $clinicId,
        int $doctorId,
        string $today,
        int $horizonDays = 14
    ): array {
        $week = $this->weeklyHours($clinicId, $doctorId);
        $todayDate = new DateTimeImmutable($today);
        $tomorrow = $todayDate->modify('+1 day')->format('Y-m-d');
        $until = $todayDate->modify('+' . $horizonDays . ' days')->format('Y-m-d');
        $exceptions = $this->exceptionsBetween($clinicId, $doctorId, $today, $until);
        $hasWeeklyHours = self::hasAnySlot($week);

        $upcoming = [];
        foreach ($exceptions as $date => $exception) {
            if ($date === $today || $date === $tomorrow) {
                continue;
            }
            $upcoming[] = $this->dayStatus($date, $week, $exceptions, $hasWeeklyHours);
        }

        $generalNote = $this->generalNote($clinicId, $doctorId);

        return [
            'configured' => $hasWeeklyHours || $exceptions !== [] || $generalNote !== '',
            'has_weekly_hours' => $hasWeeklyHours,
            'today' => $this->dayStatus($today, $week, $exceptions, $hasWeeklyHours),
            'tomorrow' => $this->dayStatus($tomorrow, $week, $exceptions, $hasWeeklyHours),
            'upcoming' => $upcoming,
            'weekly' => $hasWeeklyHours ? self::weekForOutput($week) : [],
            'general_note' => $generalNote,
        ];
    }

    /**
     * État d'une date : open, closed ou unknown (aucun horaire renseigné).
     */
    public function dayStatus(
        string $date,
        array $week,
        array $exceptions,
        bool $hasWeeklyHours
    ): array {
        $day = (int) (new DateTimeImmutable($date))->format('N');
        $exception = $exceptions[$date] ?? null;
        $slots = $week[$day] ?? [];
        $status = $hasWeeklyHours ? ($slots !== [] ? 'open' : 'closed') : 'unknown';
        $special = false;

        if ($exception !== null) {
            if ($exception['mode'] === self::MODE_CLOSED) {
                $status = 'closed';
                $slots = [];
                $special = true;
            } elseif ($exception['mode'] === self::MODE_CUSTOM_HOURS && $exception['slots'] !== []) {
                $status = 'open';
                $slots = $exception['slots'];
                $special = true;
            }
        }

        return [
            'date' => $date,
            'label' => self::dateLabel($date),
            'status' => $status,
            'slots' => $slots,
            'special_hours' => $special,
            'mode' => $exception['mode'] ?? null,
            'note' => $exception['note'] ?? '',
        ];
    }

    /**
     * États des jours $fromDate … $fromDate + $days, indexés par date.
     */
    public function dayStatuses(
        int $clinicId,
        int $doctorId,
        string $fromDate,
        int $days
    ): array {
        $week = $this->weeklyHours($clinicId, $doctorId);
        $start = new DateTimeImmutable($fromDate);
        $until = $start->modify('+' . $days . ' days')->format('Y-m-d');
        $exceptions = $this->exceptionsBetween($clinicId, $doctorId, $fromDate, $until);
        $hasWeeklyHours = self::hasAnySlot($week);

        $statuses = [];
        for ($offset = 0; $offset <= $days; $offset++) {
            $date = $start->modify('+' . $offset . ' days')->format('Y-m-d');
            $statuses[$date] = $this->dayStatus($date, $week, $exceptions, $hasWeeklyHours);
        }

        return $statuses;
    }

    /**
     * Utilisé pour bloquer l'inscription hors horaires (si activé).
     * Sans aucun horaire renseigné, on ne bloque pas.
     */
    public function isOpenAt(
        int $clinicId,
        int $doctorId,
        string $date,
        string $time
    ): bool {
        $week = $this->weeklyHours($clinicId, $doctorId);
        $exceptions = $this->exceptionsBetween($clinicId, $doctorId, $date, $date);
        $status = $this->dayStatus($date, $week, $exceptions, self::hasAnySlot($week));

        if ($status['status'] === 'unknown') {
            return true;
        }

        $time = substr($time, 0, 5);
        foreach ($status['slots'] as $slot) {
            if ($time >= $slot['open'] && $time <= $slot['close']) {
                return true;
            }
        }

        return false;
    }

    public function isClosedOn(int $clinicId, int $doctorId, string $date): ?string
    {
        $exception = $this->exceptionsBetween($clinicId, $doctorId, $date, $date)[$date] ?? null;

        if ($exception === null || $exception['mode'] !== self::MODE_CLOSED) {
            return null;
        }

        return $exception['note'];
    }

    /* =======================================================
       ÉCRITURE
       ======================================================= */

    /**
     * Remplace les horaires de la semaine.
     * $week : [1 => [['open' => '08:00', 'close' => '12:00'], …], …] (déjà validé).
     */
    public function saveWeekly(
        int $clinicId,
        int $doctorId,
        array $week,
        string $generalNote,
        bool $enforceHours
    ): void {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                "DELETE FROM doctor_public_registration_hours
                 WHERE clinic_id = :clinic_id
                   AND doctor_id = :doctor_id"
            )->execute([
                ':clinic_id' => $clinicId,
                ':doctor_id' => $doctorId,
            ]);

            $insert = $this->pdo->prepare(
                "INSERT INTO doctor_public_registration_hours (
                    clinic_id, doctor_id, day_of_week, slot_order,
                    registration_open_time, registration_close_time,
                    is_active, created_at, updated_at
                 ) VALUES (
                    :clinic_id, :doctor_id, :day_of_week, :slot_order,
                    :open_time, :close_time,
                    1, NOW(), NOW()
                 )"
            );

            foreach ($week as $day => $slots) {
                foreach (array_values($slots) as $index => $slot) {
                    $insert->execute([
                        ':clinic_id' => $clinicId,
                        ':doctor_id' => $doctorId,
                        ':day_of_week' => (int) $day,
                        ':slot_order' => $index + 1,
                        ':open_time' => $slot['open'] . ':00',
                        ':close_time' => $slot['close'] . ':00',
                    ]);
                }
            }

            $this->saveGeneralNote($clinicId, $doctorId, $generalNote);

            $this->pdo->prepare(
                "UPDATE doctor_public_registration_settings
                 SET automatic_schedule_enabled = :enforce, updated_at = NOW()
                 WHERE clinic_id = :clinic_id
                   AND doctor_id = :doctor_id
                 LIMIT 1"
            )->execute([
                ':enforce' => $enforceHours ? 1 : 0,
                ':clinic_id' => $clinicId,
                ':doctor_id' => $doctorId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Crée ou remplace le jour particulier d'une date (déjà validé).
     */
    public function saveException(
        int $clinicId,
        int $doctorId,
        string $date,
        string $mode,
        array $slots,
        string $note
    ): void {
        $this->pdo->beginTransaction();

        try {
            $this->deleteExceptionRows($clinicId, $doctorId, $date);

            $insert = $this->pdo->prepare(
                "INSERT INTO doctor_public_registration_exceptions (
                    clinic_id, doctor_id, exception_date, mode, slot_order,
                    registration_open_time, registration_close_time,
                    reason_code, public_message_override,
                    is_active, created_at, updated_at
                 ) VALUES (
                    :clinic_id, :doctor_id, :exception_date, :mode, :slot_order,
                    :open_time, :close_time,
                    NULL, :note,
                    1, NOW(), NOW()
                 )"
            );

            $rows = $mode === self::MODE_CUSTOM_HOURS ? array_values($slots) : [null];
            foreach ($rows as $index => $slot) {
                $insert->execute([
                    ':clinic_id' => $clinicId,
                    ':doctor_id' => $doctorId,
                    ':exception_date' => $date,
                    ':mode' => $mode,
                    ':slot_order' => $index + 1,
                    ':open_time' => $slot !== null ? $slot['open'] . ':00' : null,
                    ':close_time' => $slot !== null ? $slot['close'] . ':00' : null,
                    // La note n'est portée que par la première ligne de la date.
                    ':note' => $index === 0 && $note !== '' ? $note : null,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteException(int $clinicId, int $doctorId, string $date): void
    {
        $this->deleteExceptionRows($clinicId, $doctorId, $date);
    }

    /* =======================================================
       VALIDATION (partagée par les API)
       ======================================================= */

    /**
     * Valide une liste de créneaux. Retourne [créneaux normalisés, message d'erreur|null].
     */
    public static function validateSlots(mixed $slots): array
    {
        if (!is_array($slots)) {
            return [[], 'Créneaux invalides.'];
        }

        if (count($slots) > self::MAX_SLOTS_PER_DAY) {
            return [[], 'Deux créneaux maximum par jour (matin et après-midi).'];
        }

        $normalized = [];
        foreach ($slots as $slot) {
            $open = self::parseTime($slot['open'] ?? null);
            $close = self::parseTime($slot['close'] ?? null);

            if ($open === null || $close === null) {
                return [[], 'Indiquez une heure de début et de fin (HH:MM).'];
            }
            if ($open >= $close) {
                return [[], 'L’heure de fin doit être après l’heure de début.'];
            }

            $normalized[] = ['open' => $open, 'close' => $close];
        }

        usort($normalized, static fn(array $a, array $b): int => strcmp($a['open'], $b['open']));

        for ($i = 1, $count = count($normalized); $i < $count; $i++) {
            if ($normalized[$i]['open'] < $normalized[$i - 1]['close']) {
                return [[], 'Les deux créneaux se chevauchent.'];
            }
        }

        return [$normalized, null];
    }

    public static function parseTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {
            return null;
        }

        return $value;
    }

    public static function dayLabel(int $day): string
    {
        return self::DAY_LABELS[$day] ?? '';
    }

    /* =======================================================
       OUTILS INTERNES
       ======================================================= */

    private function saveGeneralNote(int $clinicId, int $doctorId, string $note): void
    {
        $this->pdo->prepare(
            "INSERT INTO doctor_public_registration_messages (
                clinic_id, doctor_id, message_code, message_text,
                is_active, created_at, updated_at
             ) VALUES (
                :clinic_id, :doctor_id, :code, :text,
                :is_active, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                message_text = VALUES(message_text),
                is_active = VALUES(is_active),
                updated_at = NOW()"
        )->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':code' => self::GENERAL_NOTE_CODE,
            ':text' => $note,
            ':is_active' => $note !== '' ? 1 : 0,
        ]);
    }

    private function deleteExceptionRows(int $clinicId, int $doctorId, string $date): void
    {
        $this->pdo->prepare(
            "DELETE FROM doctor_public_registration_exceptions
             WHERE clinic_id = :clinic_id
               AND doctor_id = :doctor_id
               AND exception_date = :exception_date"
        )->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':exception_date' => $date,
        ]);
    }

    private static function weekForOutput(array $week): array
    {
        $output = [];
        foreach ($week as $day => $slots) {
            $output[] = [
                'day' => (int) $day,
                'label' => self::DAY_LABELS[$day],
                'slots' => $slots,
            ];
        }

        return $output;
    }

    private static function hasAnySlot(array $week): bool
    {
        foreach ($week as $slots) {
            if ($slots !== []) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        // Valeurs historiques : « unavailable » valait « fermé ».
        if ($mode === 'unavailable') {
            return self::MODE_CLOSED;
        }

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_CUSTOM_HOURS;
    }

    private static function shortTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    private static function dateLabel(string $date): string
    {
        $value = new DateTimeImmutable($date);

        return sprintf(
            '%s %d %s',
            self::DAY_LABELS[(int) $value->format('N')],
            (int) $value->format('j'),
            self::MONTH_LABELS[(int) $value->format('n')]
        );
    }
}
