<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204161400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT NULL, CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE pays pays VARCHAR(100) DEFAULT NULL, CHANGE avatar avatar VARCHAR(500) DEFAULT NULL, CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
}
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
