<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207092235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question_evaluation (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, type_reponse VARCHAR(50) NOT NULL, options JSON DEFAULT NULL, min_value INT DEFAULT NULL, max_value INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE reponse_suivi (id INT AUTO_INCREMENT NOT NULL, valeur VARCHAR(255) NOT NULL, suivi_id INT NOT NULL, question_id INT NOT NULL, INDEX IDX_C155B1EC7FEA59C0 (suivi_id), INDEX IDX_C155B1EC1E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE suivi_quotidien (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, commentaire LONGTEXT DEFAULT NULL, utilisateur_id INT NOT NULL, INDEX IDX_B46C8203FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE reponse_suivi ADD CONSTRAINT FK_C155B1EC7FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi_quotidien (id)');
        $this->addSql('ALTER TABLE reponse_suivi ADD CONSTRAINT FK_C155B1EC1E27F6BF FOREIGN KEY (question_id) REFERENCES question_evaluation (id)');
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT FK_B46C8203FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponse_suivi DROP FOREIGN KEY FK_C155B1EC7FEA59C0');
        $this->addSql('ALTER TABLE reponse_suivi DROP FOREIGN KEY FK_C155B1EC1E27F6BF');
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY FK_B46C8203FB88E14F');
        $this->addSql('DROP TABLE question_evaluation');
        $this->addSql('DROP TABLE reponse_suivi');
        $this->addSql('DROP TABLE suivi_quotidien');
    }
}
