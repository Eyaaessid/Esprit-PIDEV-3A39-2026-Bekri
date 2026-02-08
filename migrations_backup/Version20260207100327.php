<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207100327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_evaluation ADD option1 VARCHAR(255) NOT NULL, ADD option2 VARCHAR(255) NOT NULL, ADD option3 VARCHAR(255) NOT NULL, DROP options');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_evaluation ADD options JSON DEFAULT NULL, DROP option1, DROP option2, DROP option3');
    }
}
