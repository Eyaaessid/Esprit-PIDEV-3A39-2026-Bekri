<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207095831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_template DROP FOREIGN KEY `FK_F468AF8FD4CB0BA5`');
        $this->addSql('ALTER TABLE suivi_reponse DROP FOREIGN KEY `FK_DA712A625DA0FB8`');
        $this->addSql('ALTER TABLE suivi_reponse DROP FOREIGN KEY `FK_DA712A62FB88E14F`');
        $this->addSql('DROP TABLE question_template');
        $this->addSql('DROP TABLE suivi_reponse');
        $this->addSql('DROP TABLE suivi_template');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question_template (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, position INT NOT NULL, suivi_template_id INT NOT NULL, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, options LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_F468AF8FD4CB0BA5 (suivi_template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE suivi_reponse (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, reponses JSON NOT NULL, submitted_at DATETIME NOT NULL, template_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_DA712A625DA0FB8 (template_id), INDEX IDX_DA712A62FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE suivi_template (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE question_template ADD CONSTRAINT `FK_F468AF8FD4CB0BA5` FOREIGN KEY (suivi_template_id) REFERENCES suivi_template (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE suivi_reponse ADD CONSTRAINT `FK_DA712A625DA0FB8` FOREIGN KEY (template_id) REFERENCES suivi_template (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE suivi_reponse ADD CONSTRAINT `FK_DA712A62FB88E14F` FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
