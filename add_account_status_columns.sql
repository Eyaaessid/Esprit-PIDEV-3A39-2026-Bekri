-- Fix: "Unknown column 'deactivated_at'" on login
-- Run this once in your MySQL client (phpMyAdmin, MySQL Workbench, or command line).

ALTER TABLE utilisateur 
  ADD deactivated_at DATETIME DEFAULT NULL,
  ADD deactivated_by VARCHAR(50) DEFAULT NULL,
  ADD reactivation_token VARCHAR(100) DEFAULT NULL,
  ADD reactivation_token_expires_at DATETIME DEFAULT NULL,
  ADD last_login_at DATETIME DEFAULT NULL;

-- Reactivation requests table (for admin-deactivated users)
CREATE TABLE IF NOT EXISTS reactivation_request (
  id INT AUTO_INCREMENT NOT NULL,
  utilisateur_id INT NOT NULL,
  reason LONGTEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  requested_at DATETIME NOT NULL,
  processed_at DATETIME DEFAULT NULL,
  admin_note LONGTEXT DEFAULT NULL,
  INDEX IDX_reactivation_utilisateur (utilisateur_id),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE reactivation_request 
  ADD CONSTRAINT FK_ReactivationRequest_utilisateur 
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE;
