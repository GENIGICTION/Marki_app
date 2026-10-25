-- ==========================================================================
-- MARKI - Inscription QR plusieurs jours à l'avance
-- Date : 2026-10-24
-- Le médecin choisit jusqu'à combien de jours à l'avance les patients
-- peuvent s'inscrire avec le QR. 0 = aujourd'hui seulement (valeur par défaut).
-- Migration idempotente : peut être relancée sans erreur.
-- ==========================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS marki_add_advance_registration_days;

DELIMITER $$
CREATE PROCEDURE marki_add_advance_registration_days()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'doctor_public_registration_settings'
          AND column_name = 'advance_registration_days'
    ) THEN
        ALTER TABLE doctor_public_registration_settings
            ADD COLUMN advance_registration_days TINYINT UNSIGNED NOT NULL DEFAULT 0
            AFTER max_public_registrations_per_day;
    END IF;
END$$
DELIMITER ;

CALL marki_add_advance_registration_days();
DROP PROCEDURE IF EXISTS marki_add_advance_registration_days;
