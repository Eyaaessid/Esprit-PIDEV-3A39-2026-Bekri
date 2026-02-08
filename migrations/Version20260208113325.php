<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208113325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question_evaluation (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, type_reponse VARCHAR(50) NOT NULL, option1 VARCHAR(255) NOT NULL, option2 VARCHAR(255) NOT NULL, option3 VARCHAR(255) NOT NULL, min_value INT DEFAULT NULL, max_value INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reponse_suivi (id INT AUTO_INCREMENT NOT NULL, valeur VARCHAR(255) NOT NULL, suivi_id INT NOT NULL, question_id INT NOT NULL, INDEX IDX_C155B1EC7FEA59C0 (suivi_id), INDEX IDX_C155B1EC1E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reponse_suivi ADD CONSTRAINT FK_C155B1EC7FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi_quotidien (id)');
        $this->addSql('ALTER TABLE reponse_suivi ADD CONSTRAINT FK_C155B1EC1E27F6BF FOREIGN KEY (question_id) REFERENCES question_evaluation (id)');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY `FK_B46C8203157D1AD4`');
        $this->addSql('DROP INDEX IDX_B46C8203157D1AD4 ON suivi_quotidien');
        $this->addSql('ALTER TABLE suivi_quotidien ADD utilisateur_id INT NOT NULL, DROP sommeil, DROP humeur, DROP energie, DROP poids, DROP created_at, DROP updated_at, DROP objectif_id, CHANGE nutrition commentaire LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT FK_B46C8203FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_B46C8203FB88E14F ON suivi_quotidien (utilisateur_id)');
        $this->addSql('ALTER TABLE utilisateur ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponse_suivi DROP FOREIGN KEY FK_C155B1EC7FEA59C0');
        $this->addSql('ALTER TABLE reponse_suivi DROP FOREIGN KEY FK_C155B1EC1E27F6BF');
        $this->addSql('DROP TABLE question_evaluation');
        $this->addSql('DROP TABLE reponse_suivi');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY FK_B46C8203FB88E14F');
        $this->addSql('DROP INDEX IDX_B46C8203FB88E14F ON suivi_quotidien');
        $this->addSql('ALTER TABLE suivi_quotidien ADD sommeil DOUBLE PRECISION NOT NULL, ADD energie INT NOT NULL, ADD poids DOUBLE PRECISION DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD objectif_id INT NOT NULL, CHANGE utilisateur_id humeur INT NOT NULL, CHANGE commentaire nutrition LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT `FK_B46C8203157D1AD4` FOREIGN KEY (objectif_id) REFERENCES objectif_bien_etre (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_B46C8203157D1AD4 ON suivi_quotidien (objectif_id)');
        $this->addSql('ALTER TABLE utilisateur DROP reset_token, DROP reset_token_expires_at');
    }
}
