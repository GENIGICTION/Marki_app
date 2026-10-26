<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/support.php';

$context = null;

/*
| « Donner mon avis » : enregistre le message pour l'équipe MARKI
| (lu dans l'administration interne, onglet Avis).
*/
try {
    $context = require __DIR__ . '/../../app/bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];

    $message = trim((string) ($input['message'] ?? ''));
    $kind = (string) ($input['kind'] ?? 'other');
    if (!in_array($kind, ['idea', 'problem', 'question', 'other'], true)) {
        $kind = 'other';
    }

    if (mb_strlen($message, 'UTF-8') < 3) {
        Auth::jsonError(422, 'Écrivez quelques mots avant d’envoyer.');
    }
    if (mb_strlen($message, 'UTF-8') > 2000) {
        Auth::jsonError(422, 'Le message ne doit pas dépasser 2000 caractères.');
    }

    $pdo = db();

    // Garde-fou : 20 messages par heure et par utilisateur.
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM app_feedback
         WHERE user_id = :user_id
           AND created_at >= (NOW() - INTERVAL 1 HOUR)"
    );
    $countStmt->execute([':user_id' => (int) $context['user_id']]);
    if ((int) $countStmt->fetchColumn() >= 20) {
        Auth::jsonError(429, 'Vous avez envoyé beaucoup de messages. Réessayez dans une heure.');
    }

    $clientCreatedAt = null;
    $rawCreatedAt = trim((string) ($input['created_at'] ?? ''));
    if ($rawCreatedAt !== '') {
        try {
            $clientCreatedAt = (new DateTimeImmutable($rawCreatedAt))
                ->setTimezone(new DateTimeZone((string) $context['timezone']))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $clientCreatedAt = null;
        }
    }

    $doctorId = (int) ($input['doctor_id'] ?? 0);
    $allowedDoctorIds = array_map(
        static fn(array $doctor): int => (int) $doctor['id'],
        $context['doctors'] ?? []
    );

    $stmt = $pdo->prepare(
        "INSERT INTO app_feedback (
            clinic_id, user_id, doctor_id, kind, message,
            page, viewport, user_agent, status, client_created_at,
            created_at, updated_at
         ) VALUES (
            :clinic_id, :user_id, :doctor_id, :kind, :message,
            :page, :viewport, :user_agent, 'new', :client_created_at,
            NOW(), NOW()
         )"
    );
    $stmt->execute([
        ':clinic_id' => (int) $context['clinic_id'],
        ':user_id' => (int) $context['user_id'],
        ':doctor_id' => in_array($doctorId, $allowedDoctorIds, true) ? $doctorId : null,
        ':kind' => $kind,
        ':message' => $message,
        ':page' => mb_substr(trim((string) ($input['page'] ?? '')), 0, 50, 'UTF-8') ?: null,
        ':viewport' => mb_substr(trim((string) ($input['viewport'] ?? '')), 0, 20, 'UTF-8') ?: null,
        ':user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8') ?: null,
        ':client_created_at' => $clientCreatedAt,
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Merci ! Votre message a bien été envoyé à l’équipe MARKI.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    markiJsonException(
        'feedback_submit',
        $exception,
        $config,
        'Impossible d’envoyer votre message.',
        [
            'user_id' => $context['user_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
        ]
    );
}
