<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates profil_psychologique table only (Initial Wellbeing Assessment).
 */
final class Version20260228120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create profil_psychologique table for initial wellbeing assessment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profil_psychologique (
            id INT AUTO_INCREMENT NOT NULL,
            score_global INT NOT NULL,
            profil_type VARCHAR(100) NOT NULL,
            date_evaluation DATETIME NOT NULL,
            utilisateur_id INT NOT NULL,
            UNIQUE INDEX UNIQ_C1A2049EFB88E14F (utilisateur_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE profil_psychologique ADD CONSTRAINT FK_C1A2049EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profil_psychologique DROP FOREIGN KEY FK_C1A2049EFB88E14F');
        $this->addSql('DROP TABLE profil_psychologique');
    }
}
