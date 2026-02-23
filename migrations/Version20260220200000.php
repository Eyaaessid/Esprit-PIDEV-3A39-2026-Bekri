<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add two-factor authentication fields to utilisateur table
 */
final class Version20260220200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-factor authentication fields (totp_secret, is_two_factor_enabled, backup_codes, two_factor_enabled_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD is_two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD backup_codes LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD two_factor_enabled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP totp_secret');
        $this->addSql('ALTER TABLE utilisateur DROP is_two_factor_enabled');
        $this->addSql('ALTER TABLE utilisateur DROP backup_codes');
        $this->addSql('ALTER TABLE utilisateur DROP two_factor_enabled_at');
    }
}
