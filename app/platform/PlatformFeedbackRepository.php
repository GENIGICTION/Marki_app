<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

/**
 * Avis envoyés par les structures avec « Donner mon avis ».
 */
final class PlatformFeedbackRepository
{
    public const KINDS = [
        'idea' => 'Idée',
        'problem' => 'Problème',
        'question' => 'Question',
        'other' => 'Autre',
    ];

    public const STATUSES = [
        'new' => 'Nouveau',
        'read' => 'Lu',
        'done' => 'Traité',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function list(string $status = '', int $limit = 100): array
    {
        $where = '';
        if (array_key_exists($status, self::STATUSES)) {
            $where = 'WHERE f.status = :status';
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                f.id, f.kind, f.message, f.page, f.viewport, f.status,
                f.created_at,
                c.name AS clinic_name, c.slug AS clinic_slug, c.phone AS clinic_phone,
                u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone,
                dp.display_name AS doctor_name
             FROM app_feedback f
             INNER JOIN clinics c ON c.id = f.clinic_id
             LEFT JOIN users u ON u.id = f.user_id
             LEFT JOIN doctor_profiles dp ON dp.id = f.doctor_id
             {$where}
             ORDER BY f.status = 'new' DESC, f.created_at DESC
             LIMIT :limit"
        );
        if ($where !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countNew(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM app_feedback WHERE status = 'new'"
        )->fetchColumn();
    }

    public function setStatus(int $feedbackId, string $status): void
    {
        if (!array_key_exists($status, self::STATUSES)) {
            throw new InvalidArgumentException('État invalide.');
        }

        $this->pdo->prepare(
            'UPDATE app_feedback SET status = :status, updated_at = NOW() WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':id' => $feedbackId,
        ]);
    }
}
