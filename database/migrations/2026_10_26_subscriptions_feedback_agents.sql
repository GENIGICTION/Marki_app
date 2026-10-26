-- ==========================================================================
-- MARKI - Abonnements des structures, avis des médecins, agents MARKI
-- Date : 2026-10-26
-- - clinics.subscription_ends_on : date de fin d'abonnement (NULL = pas de
--   date de fin, jamais bloqué). 7 jours de grâce après cette date.
-- - clinic_subscriptions : historique des paiements / renouvellements.
-- - app_feedback : avis envoyés avec le bouton « Donner mon avis ».
-- - platform_admins.role : owner (tous les droits) ou agent (renouveler un
--   abonnement, créer une structure).
-- Migration idempotente : peut être relancée sans erreur.
-- ==========================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS marki_phase5_columns;

DELIMITER $$
CREATE PROCEDURE marki_phase5_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'clinics'
          AND column_name = 'subscription_ends_on'
    ) THEN
        ALTER TABLE clinics
            ADD COLUMN subscription_ends_on DATE NULL DEFAULT NULL AFTER status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'platform_admins'
          AND column_name = 'role'
    ) THEN
        ALTER TABLE platform_admins
            ADD COLUMN role ENUM('owner', 'agent') NOT NULL DEFAULT 'owner' AFTER full_name;
    END IF;
END$$
DELIMITER ;

CALL marki_phase5_columns();
DROP PROCEDURE IF EXISTS marki_phase5_columns;

CREATE TABLE IF NOT EXISTS clinic_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    months SMALLINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'DZD',
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    note VARCHAR(255) NULL,
    recorded_by_admin_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_cs_clinic_end (clinic_id, ends_on),
    KEY ix_cs_admin (recorded_by_admin_id),
    CONSTRAINT fk_cs_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cs_admin FOREIGN KEY (recorded_by_admin_id) REFERENCES platform_admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    doctor_id BIGINT UNSIGNED NULL,
    kind ENUM('idea', 'problem', 'question', 'other') NOT NULL DEFAULT 'other',
    message TEXT NOT NULL,
    page VARCHAR(50) NULL,
    viewport VARCHAR(20) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new', 'read', 'done') NOT NULL DEFAULT 'new',
    client_created_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_af_status_created (status, created_at),
    KEY ix_af_clinic (clinic_id),
    CONSTRAINT fk_af_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE CASCADE,
    CONSTRAINT fk_af_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
