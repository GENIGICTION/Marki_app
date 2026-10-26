<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../subscription.php';

/**
 * Structures clientes et abonnements (administration MARKI).
 * L'abonnement est par structure : clinics.subscription_ends_on porte la
 * date de fin courante, clinic_subscriptions garde chaque paiement.
 */
final class ClinicSubscriptionRepository
{
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'transfer' => 'Virement',
        'ccp' => 'CCP / BaridiMob',
        'card' => 'Carte',
        'other' => 'Autre',
        'free' => 'Offert',
    ];

    public const DURATIONS = [1, 3, 6, 12];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function listClinics(string $today, int $graceDays, string $search = ''): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE c.name LIKE :search OR c.slug LIKE :search OR c.city LIKE :search OR c.wilaya LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                c.id, c.name, c.slug, c.city, c.wilaya, c.phone, c.status,
                c.subscription_ends_on, c.created_at,
                (SELECT COUNT(*) FROM doctor_profiles dp
                  WHERE dp.clinic_id = c.id AND dp.is_active = 1) AS doctors_count,
                (SELECT COUNT(*) FROM users u
                  WHERE u.clinic_id = c.id AND u.status = 'active') AS users_count,
                (SELECT MAX(u.last_login_at) FROM users u
                  WHERE u.clinic_id = c.id) AS last_login_at
             FROM clinics c
             {$where}
             ORDER BY
                c.subscription_ends_on IS NULL,
                c.subscription_ends_on ASC,
                c.name ASC
             LIMIT 300"
        );
        $stmt->execute($params);

        return array_map(
            static function (array $row) use ($today, $graceDays): array {
                $row['subscription'] = markiSubscriptionState(
                    $row['subscription_ends_on'],
                    $today,
                    $graceDays
                );
                return $row;
            },
            $stmt->fetchAll()
        );
    }

    public function findClinic(int $clinicId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, status, subscription_ends_on FROM clinics WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clinicId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Prolonge l'abonnement de $months mois.
     * Départ : la date de fin actuelle si elle est encore à venir,
     * sinon aujourd'hui (un abonnement expiré repart de zéro).
     */
    public function renew(
        int $clinicId,
        int $months,
        ?float $amount,
        string $paymentMethod,
        string $note,
        int $adminId,
        string $today
    ): array {
        if (!in_array($months, self::DURATIONS, true)) {
            throw new InvalidArgumentException('Durée invalide.');
        }
        if (!array_key_exists($paymentMethod, self::PAYMENT_METHODS)) {
            throw new InvalidArgumentException('Mode de paiement invalide.');
        }
        if ($amount !== null && ($amount < 0 || $amount > 10000000)) {
            throw new InvalidArgumentException('Montant invalide.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, subscription_ends_on FROM clinics WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $clinicId]);
            $clinic = $stmt->fetch();

            if (!$clinic) {
                throw new InvalidArgumentException('Structure introuvable.');
            }

            $currentEnd = $clinic['subscription_ends_on'];
            $startsOn = ($currentEnd !== null && $currentEnd >= $today)
                ? (new DateTimeImmutable($currentEnd))->modify('+1 day')
                : new DateTimeImmutable($today);
            $endsOn = $startsOn->modify('+' . $months . ' months')->modify('-1 day');

            $this->pdo->prepare(
                "INSERT INTO clinic_subscriptions (
                    clinic_id, starts_on, ends_on, months, amount, currency,
                    payment_method, note, recorded_by_admin_id, created_at
                 ) VALUES (
                    :clinic_id, :starts_on, :ends_on, :months, :amount, 'DZD',
                    :payment_method, :note, :admin_id, NOW()
                 )"
            )->execute([
                ':clinic_id' => $clinicId,
                ':starts_on' => $startsOn->format('Y-m-d'),
                ':ends_on' => $endsOn->format('Y-m-d'),
                ':months' => $months,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':note' => $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
                ':admin_id' => $adminId,
            ]);

            $this->pdo->prepare(
                'UPDATE clinics SET subscription_ends_on = :ends_on, updated_at = NOW() WHERE id = :id'
            )->execute([
                ':ends_on' => $endsOn->format('Y-m-d'),
                ':id' => $clinicId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => $endsOn->format('Y-m-d'),
        ];
    }

    /**
     * Correction manuelle de la date de fin (propriétaire uniquement).
     * NULL = structure sans date de fin (jamais bloquée).
     */
    public function setEndDate(int $clinicId, ?string $endsOn): void
    {
        $this->pdo->prepare(
            'UPDATE clinics SET subscription_ends_on = :ends_on, updated_at = NOW() WHERE id = :id'
        )->execute([
            ':ends_on' => $endsOn,
            ':id' => $clinicId,
        ]);
    }

    public function setStatus(int $clinicId, bool $active): void
    {
        $this->pdo->prepare(
            'UPDATE clinics SET status = :status, updated_at = NOW() WHERE id = :id'
        )->execute([
            ':status' => $active ? 'active' : 'suspended',
            ':id' => $clinicId,
        ]);
    }

    public function recentPayments(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                cs.*, c.name AS clinic_name,
                pa.full_name AS admin_name, pa.role AS admin_role
             FROM clinic_subscriptions cs
             INNER JOIN clinics c ON c.id = cs.clinic_id
             LEFT JOIN platform_admins pa ON pa.id = cs.recorded_by_admin_id
             ORDER BY cs.created_at DESC, cs.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
