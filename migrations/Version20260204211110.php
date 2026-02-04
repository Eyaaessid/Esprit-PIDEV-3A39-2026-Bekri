<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204211110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement ADD titre VARCHAR(255) NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD date_debut DATETIME NOT NULL, ADD date_fin DATETIME DEFAULT NULL, ADD type VARCHAR(50) NOT NULL, ADD capacite_max INT NOT NULL, ADD statut VARCHAR(50) NOT NULL, ADD lien_session VARCHAR(500) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD coach_id INT NOT NULL');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E3C105691 FOREIGN KEY (coach_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_B26681E3C105691 ON evenement (coach_id)');
        $this->addSql('ALTER TABLE `like` DROP datetime');
        $this->addSql('ALTER TABLE objectif_bien_etre ADD valeur_cible DOUBLE PRECISION NOT NULL, DROP utilisateur, CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE utilisateur_id utilisateur_id INT NOT NULL, CHANGE status statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT NULL, CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE question ADD contenu LONGTEXT NOT NULL, ADD choix_a VARCHAR(255) NOT NULL, ADD choix_b VARCHAR(255) NOT NULL, ADD choix_c VARCHAR(255) NOT NULL, ADD bonne_reponse VARCHAR(1) NOT NULL, ADD test_mental_id INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E562B3943 FOREIGN KEY (test_mental_id) REFERENCES test_mental (id)');
        $this->addSql('CREATE INDEX IDX_B6F7494E562B3943 ON question (test_mental_id)');
        $this->addSql('ALTER TABLE suivi_quotidien ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, DROP datetime, CHANGE poids poids DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE test_mental ADD titre VARCHAR(255) NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD type_test VARCHAR(100) NOT NULL, ADD niveau VARCHAR(50) NOT NULL, ADD duree INT NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE pays pays VARCHAR(100) DEFAULT NULL, CHANGE avatar avatar VARCHAR(500) DEFAULT NULL, CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E3C105691');
        $this->addSql('DROP INDEX IDX_B26681E3C105691 ON evenement');
        $this->addSql('ALTER TABLE evenement DROP titre, DROP description, DROP date_debut, DROP date_fin, DROP type, DROP capacite_max, DROP statut, DROP lien_session, DROP created_at, DROP coach_id');
        $this->addSql('ALTER TABLE `like` ADD datetime VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE objectif_bien_etre ADD utilisateur VARCHAR(255) NOT NULL, DROP valeur_cible, CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL, CHANGE statut status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT \'NULL\', CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E562B3943');
        $this->addSql('DROP INDEX IDX_B6F7494E562B3943 ON question');
        $this->addSql('ALTER TABLE question DROP contenu, DROP choix_a, DROP choix_b, DROP choix_c, DROP bonne_reponse, DROP test_mental_id');
        $this->addSql('ALTER TABLE suivi_quotidien ADD datetime DATETIME DEFAULT \'NULL\', DROP created_at, DROP updated_at, CHANGE poids poids DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_mental DROP titre, DROP description, DROP type_test, DROP niveau, DROP duree');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE pays pays VARCHAR(100) DEFAULT \'NULL\', CHANGE avatar avatar VARCHAR(500) DEFAULT \'NULL\', CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
    }
}
