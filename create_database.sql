-- ============================================
-- Bekri Wellbeing Platform - Database Creation Script
-- Date: 2026-02-22
-- Description: Complete database schema with all fixes applied
-- Usage: Run this script in phpMyAdmin
-- ============================================

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS bekri_wellbeing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE bekri_wellbeing;

-- ============================================
-- TABLE: utilisateur
-- ============================================
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `telephone` VARCHAR(20) DEFAULT NULL,
  `date_naissance` DATE NOT NULL,
  `avatar` VARCHAR(500) DEFAULT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'user',
  `statut` VARCHAR(50) NOT NULL DEFAULT 'actif',
  `score_initial` INT DEFAULT NULL,
  `date_evaluation_initiale` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  -- Password reset fields
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_token_expires_at` DATETIME DEFAULT NULL,
  -- Account status fields
  `deactivated_at` DATETIME DEFAULT NULL,
  `deactivated_by` VARCHAR(50) DEFAULT NULL,
  `reactivation_token` VARCHAR(100) DEFAULT NULL,
  `reactivation_token_expires_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  -- Email verification
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  -- Facial recognition fields
  `face_descriptor` TEXT DEFAULT NULL,
  `face_auth_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `face_registered_at` DATETIME DEFAULT NULL,
  `face_auth_failed_attempts` INT NOT NULL DEFAULT 0,
  `last_face_auth_attempt_at` DATETIME DEFAULT NULL,
  -- Two-factor authentication fields
  `totp_secret` VARCHAR(255) DEFAULT NULL,
  `is_two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `backup_codes` TEXT DEFAULT NULL,
  `two_factor_enabled_at` DATETIME DEFAULT NULL,
  UNIQUE INDEX `UNIQ_1D1C63B3E7927C74` (`email`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: evenement (WITH FIXES APPLIED)
-- ============================================
CREATE TABLE IF NOT EXISTS `evenement` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `coach_id` INT NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `description` LONGTEXT DEFAULT NULL,
  `date_debut` DATETIME NOT NULL,
  `date_fin` DATETIME DEFAULT NULL,
  `lieu` VARCHAR(255) DEFAULT NULL, -- ✅ FIXED: Added missing column
  `capacite_max` INT NOT NULL,
  `type` VARCHAR(100) NOT NULL COMMENT 'atelier, méditation, défi santé, etc.',
  `statut` VARCHAR(50) NOT NULL DEFAULT 'ouvert' COMMENT 'ouvert, complet, annulé, terminé',
  `image` VARCHAR(255) DEFAULT NULL, -- ✅ FIXED: Added missing column
  `created_at` DATETIME NOT NULL,
  INDEX `IDX_B26681E3C105691` (`coach_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_EVENEMENT_COACH` FOREIGN KEY (`coach_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: participation_evenement (WITH FIXES APPLIED)
-- ============================================
CREATE TABLE IF NOT EXISTS `participation_evenement` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `evenement_id` INT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `date_inscription` DATETIME NOT NULL,
  `statut` VARCHAR(50) NOT NULL DEFAULT 'confirmé' COMMENT 'confirmé, annulé, en attente',
  `commentaire` LONGTEXT DEFAULT NULL, -- ✅ FIXED: Added missing column
  INDEX `IDX_65A14675FD02F13` (`evenement_id`),
  INDEX `IDX_65A14675FB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_PARTICIPATION_EVENEMENT` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_PARTICIPATION_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: post
-- ============================================
CREATE TABLE IF NOT EXISTS `post` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `contenu` LONGTEXT NOT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `categorie` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  INDEX `IDX_5A8A6C8DFB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_POST_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: commentaire
-- ============================================
CREATE TABLE IF NOT EXISTS `commentaire` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `post_id` INT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `contenu` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  INDEX `IDX_67F068BC4B89032C` (`post_id`),
  INDEX `IDX_67F068BCFB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_COMMENTAIRE_POST` FOREIGN KEY (`post_id`) REFERENCES `post` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_COMMENTAIRE_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: like
-- ============================================
CREATE TABLE IF NOT EXISTS `like` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `post_id` INT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `IDX_AC6340B34B89032C` (`post_id`),
  INDEX `IDX_AC6340B3FB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_LIKE_POST` FOREIGN KEY (`post_id`) REFERENCES `post` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_LIKE_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: post_notification
-- ============================================
CREATE TABLE IF NOT EXISTS `post_notification` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `recipient_id` INT NOT NULL,
  `actor_id` INT NOT NULL,
  `post_id` INT NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'like, comment',
  `message` LONGTEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  INDEX `IDX_F4B24A2EE92F8F78` (`recipient_id`),
  INDEX `IDX_F4B24A2E10DAF24A` (`actor_id`),
  INDEX `IDX_F4B24A2E4B89032C` (`post_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_NOTIFICATION_RECIPIENT` FOREIGN KEY (`recipient_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_NOTIFICATION_ACTOR` FOREIGN KEY (`actor_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_NOTIFICATION_POST` FOREIGN KEY (`post_id`) REFERENCES `post` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: objectif_bien_etre
-- ============================================
CREATE TABLE IF NOT EXISTS `objectif_bien_etre` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `valeur_cible` DOUBLE PRECISION NOT NULL,
  `valeur_actuelle` DOUBLE PRECISION DEFAULT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `statut` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  INDEX `IDX_319ACD66FB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_OBJECTIF_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: suivi_quotidien
-- ============================================
CREATE TABLE IF NOT EXISTS `suivi_quotidien` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `commentaire` LONGTEXT DEFAULT NULL,
  INDEX `IDX_B46C8203FB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_SUIVI_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: test_mental
-- ============================================
CREATE TABLE IF NOT EXISTS `test_mental` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `description` LONGTEXT DEFAULT NULL,
  `type_test` VARCHAR(100) NOT NULL,
  `niveau` VARCHAR(50) NOT NULL,
  `duree` INT NOT NULL COMMENT 'Duration in minutes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: question
-- ============================================
CREATE TABLE IF NOT EXISTS `question` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `test_mental_id` INT NOT NULL,
  `contenu` LONGTEXT NOT NULL,
  `choix_a` VARCHAR(255) NOT NULL,
  `choix_b` VARCHAR(255) NOT NULL,
  `choix_c` VARCHAR(255) NOT NULL,
  `bonne_reponse` VARCHAR(1) NOT NULL,
  INDEX `IDX_B6F7494E562B3943` (`test_mental_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_QUESTION_TEST` FOREIGN KEY (`test_mental_id`) REFERENCES `test_mental` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: resultat_test
-- ============================================
CREATE TABLE IF NOT EXISTS `resultat_test` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `test_mental_id` INT NOT NULL,
  `score` INT NOT NULL,
  `interpretation` LONGTEXT DEFAULT NULL,
  `date_passage` DATETIME NOT NULL,
  INDEX `IDX_7ECAF22DFB88E14F` (`utilisateur_id`),
  INDEX `IDX_7ECAF22D562B3943` (`test_mental_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_RESULTAT_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_RESULTAT_TEST` FOREIGN KEY (`test_mental_id`) REFERENCES `test_mental` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: messenger_messages (Symfony Messenger)
-- ============================================
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` BIGINT AUTO_INCREMENT NOT NULL,
  `body` LONGTEXT NOT NULL,
  `headers` LONGTEXT NOT NULL,
  `queue_name` VARCHAR(190) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `available_at` DATETIME NOT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  INDEX `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`, `available_at`, `delivered_at`, `id`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================

-- Insert a test admin user (password: admin123)
INSERT INTO `utilisateur` (
  `nom`, `prenom`, `email`, `mot_de_passe`, `date_naissance`, 
  `role`, `statut`, `created_at`, `is_verified`
) VALUES (
  'Admin', 'System', 'admin@bekri.local', 
  '$2y$13$YourHashedPasswordHere', -- Replace with actual hashed password
  '1990-01-01', 'admin', 'actif', NOW(), 1
);

-- Insert a test coach user (password: coach123)
INSERT INTO `utilisateur` (
  `nom`, `prenom`, `email`, `mot_de_passe`, `date_naissance`, 
  `role`, `statut`, `created_at`, `is_verified`
) VALUES (
  'Dupont', 'Marie', 'coach@bekri.local', 
  '$2y$13$YourHashedPasswordHere', -- Replace with actual hashed password
  '1985-05-15', 'coach', 'actif', NOW(), 1
);

-- Insert a test regular user (password: user123)
INSERT INTO `utilisateur` (
  `nom`, `prenom`, `email`, `mot_de_passe`, `date_naissance`, 
  `role`, `statut`, `created_at`, `is_verified`
) VALUES (
  'Martin', 'Jean', 'user@bekri.local', 
  '$2y$13$YourHashedPasswordHere', -- Replace with actual hashed password
  '1995-08-20', 'user', 'actif', NOW(), 1
);

-- Insert a sample event (assuming coach_id = 2)
INSERT INTO `evenement` (
  `coach_id`, `titre`, `description`, `date_debut`, `date_fin`, 
  `lieu`, `capacite_max`, `type`, `statut`, `created_at`
) VALUES (
  2, 'Atelier Méditation Pleine Conscience', 
  'Découvrez les bienfaits de la méditation pour réduire le stress et améliorer votre bien-être mental.',
  '2026-03-15 10:00:00', '2026-03-15 12:00:00',
  'Salle de Yoga, Centre Bekri', 20, 'atelier', 'ouvert', NOW()
);

INSERT INTO `evenement` (
  `coach_id`, `titre`, `description`, `date_debut`, `date_fin`, 
  `lieu`, `capacite_max`, `type`, `statut`, `created_at`
) VALUES (
  2, 'Défi Santé 30 Jours', 
  'Rejoignez notre défi santé de 30 jours pour adopter de meilleures habitudes alimentaires et sportives.',
  '2026-04-01 09:00:00', '2026-04-30 18:00:00',
  'En ligne', 50, 'défi santé', 'ouvert', NOW()
);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check all tables were created
-- SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'bekri_wellbeing';

-- Check utilisateur table
-- SELECT * FROM utilisateur;

-- Check evenement table structure
-- DESCRIBE evenement;

-- Check participation_evenement table structure
-- DESCRIBE participation_evenement;

-- ============================================
-- NOTES
-- ============================================
-- 1. Replace 'bekri_wellbeing' with your actual database name
-- 2. Replace hashed passwords with actual bcrypt hashes
-- 3. All foreign keys have ON DELETE CASCADE for data integrity
-- 4. All tables use utf8mb4 for full Unicode support (including emojis)
-- 5. The script is idempotent (can be run multiple times safely with IF NOT EXISTS)
-- 6. Sample data is optional - remove if not needed

-- ============================================
-- PASSWORD GENERATION (for testing)
-- ============================================
-- To generate password hashes, use PHP:
-- php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
-- php -r "echo password_hash('coach123', PASSWORD_BCRYPT);"
-- php -r "echo password_hash('user123', PASSWORD_BCRYPT);"
