<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220192453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add utilisateur.is_verified for email verification (keep existing users verified)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD is_verified TINYINT DEFAULT 0 NOT NULL');
        // Do not lock out existing users after introducing email verification
        $this->addSql('UPDATE utilisateur SET is_verified = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP is_verified');
    }
}
