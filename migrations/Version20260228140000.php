<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add ai_feedback to profil_psychologique (AI Emotional Insight).
 */
final class Version20260228140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_feedback column to profil_psychologique for AI Emotional Insight Assistant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profil_psychologique ADD ai_feedback LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profil_psychologique DROP ai_feedback');
    }
}
