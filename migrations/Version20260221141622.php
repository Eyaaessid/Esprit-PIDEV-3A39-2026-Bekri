<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221141622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT FK_B46C8203FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_B46C8203FB88E14F ON suivi_quotidien (utilisateur_id)');
        $this->addSql('ALTER TABLE utilisateur ADD is_verified TINYINT DEFAULT 0 NOT NULL, ADD face_descriptor LONGTEXT DEFAULT NULL, ADD face_auth_enabled TINYINT DEFAULT 0 NOT NULL, ADD face_registered_at DATETIME DEFAULT NULL, ADD face_auth_failed_attempts INT DEFAULT 0 NOT NULL, ADD last_face_auth_attempt_at DATETIME DEFAULT NULL, ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD is_two_factor_enabled TINYINT DEFAULT 0 NOT NULL, ADD backup_codes LONGTEXT DEFAULT NULL, ADD two_factor_enabled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY FK_B46C8203FB88E14F');
        $this->addSql('DROP INDEX IDX_B46C8203FB88E14F ON suivi_quotidien');
        $this->addSql('ALTER TABLE utilisateur DROP is_verified, DROP face_descriptor, DROP face_auth_enabled, DROP face_registered_at, DROP face_auth_failed_attempts, DROP last_face_auth_attempt_at, DROP totp_secret, DROP is_two_factor_enabled, DROP backup_codes, DROP two_factor_enabled_at');
    }
}
