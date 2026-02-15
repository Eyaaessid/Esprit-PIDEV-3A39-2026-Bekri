<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Account status management: deactivated_at, deactivated_by, reactivation_token,
 * reactivation_token_expires_at, last_login_at.
 */
final class Version20260215000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Account status: utilisateur fields + reactivation_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD deactivated_at DATETIME DEFAULT NULL, ADD deactivated_by VARCHAR(50) DEFAULT NULL, ADD reactivation_token VARCHAR(100) DEFAULT NULL, ADD reactivation_token_expires_at DATETIME DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE reactivation_request (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, reason LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, requested_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, admin_note LONGTEXT DEFAULT NULL, INDEX IDX_reactivation_utilisateur (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reactivation_request ADD CONSTRAINT FK_ReactivationRequest_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reactivation_request DROP FOREIGN KEY FK_ReactivationRequest_utilisateur');
        $this->addSql('DROP TABLE reactivation_request');
        $this->addSql('ALTER TABLE utilisateur DROP deactivated_at, DROP deactivated_by, DROP reactivation_token, DROP reactivation_token_expires_at, DROP last_login_at');
    }
}
