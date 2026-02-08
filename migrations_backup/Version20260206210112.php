<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206210112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question_template (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, options JSON DEFAULT NULL, position INT NOT NULL, template_id INT NOT NULL, INDEX IDX_F468AF8F5DA0FB8 (template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE suivi_reponse (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, reponses JSON NOT NULL, submitted_at DATETIME NOT NULL, template_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_DA712A625DA0FB8 (template_id), INDEX IDX_DA712A62FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE suivi_template (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE question_template ADD CONSTRAINT FK_F468AF8F5DA0FB8 FOREIGN KEY (template_id) REFERENCES suivi_template (id)');
        $this->addSql('ALTER TABLE suivi_reponse ADD CONSTRAINT FK_DA712A625DA0FB8 FOREIGN KEY (template_id) REFERENCES suivi_template (id)');
        $this->addSql('ALTER TABLE suivi_reponse ADD CONSTRAINT FK_DA712A62FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY `FK_B46C8203157D1AD4`');
        $this->addSql('DROP TABLE suivi_quotidien');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE description description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE suivi_quotidien (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, sommeil DOUBLE PRECISION NOT NULL, humeur INT NOT NULL, energie INT NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, nutrition LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, objectif_id INT NOT NULL, INDEX IDX_B46C8203157D1AD4 (objectif_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT `FK_B46C8203157D1AD4` FOREIGN KEY (objectif_id) REFERENCES objectif_bien_etre (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE question_template DROP FOREIGN KEY FK_F468AF8F5DA0FB8');
        $this->addSql('ALTER TABLE suivi_reponse DROP FOREIGN KEY FK_DA712A625DA0FB8');
        $this->addSql('ALTER TABLE suivi_reponse DROP FOREIGN KEY FK_DA712A62FB88E14F');
        $this->addSql('DROP TABLE question_template');
        $this->addSql('DROP TABLE suivi_reponse');
        $this->addSql('DROP TABLE suivi_template');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE description description LONGTEXT NOT NULL');
    }
}
