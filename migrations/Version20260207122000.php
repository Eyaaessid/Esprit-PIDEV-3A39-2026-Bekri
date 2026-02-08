<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_evaluation ADD created_at DATETIME NOT NULL, DROP type_reponse, DROP min_value, DROP max_value');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_evaluation ADD type_reponse VARCHAR(50) NOT NULL, ADD min_value INT DEFAULT NULL, ADD max_value INT DEFAULT NULL, DROP created_at');
    }
}
