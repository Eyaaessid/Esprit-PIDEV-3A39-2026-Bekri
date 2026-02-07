<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207192053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, post_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_67F068BC4B89032C (post_id), INDEX IDX_67F068BCFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME DEFAULT NULL, type VARCHAR(50) NOT NULL, capacite_max INT NOT NULL, statut VARCHAR(50) NOT NULL, lien_session VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, coach_id INT NOT NULL, INDEX IDX_B26681E3C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `like` (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_AC6340B34B89032C (post_id), INDEX IDX_AC6340B3FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE objectif_bien_etre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, type VARCHAR(100) NOT NULL, valeur_cible DOUBLE PRECISION NOT NULL, valeur_actuelle DOUBLE PRECISION DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, utilisateur_id INT NOT NULL, INDEX IDX_319ACD66FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participation_evenement (id INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, evenement_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_65A14675FD02F13 (evenement_id), INDEX IDX_65A14675FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, media_url VARCHAR(500) DEFAULT NULL, categorie VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, utilisateur_id INT NOT NULL, INDEX IDX_5A8A6C8DFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, choix_a VARCHAR(255) NOT NULL, choix_b VARCHAR(255) NOT NULL, choix_c VARCHAR(255) NOT NULL, bonne_reponse VARCHAR(1) NOT NULL, test_mental_id INT NOT NULL, INDEX IDX_B6F7494E562B3943 (test_mental_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resultat_test (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, interpretation LONGTEXT DEFAULT NULL, date_passage DATETIME NOT NULL, utilisateur_id INT NOT NULL, test_mental_id INT NOT NULL, INDEX IDX_7ECAF22DFB88E14F (utilisateur_id), INDEX IDX_7ECAF22D562B3943 (test_mental_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE suivi_quotidien (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, sommeil DOUBLE PRECISION NOT NULL, humeur INT NOT NULL, energie INT NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, nutrition LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, objectif_id INT NOT NULL, INDEX IDX_B46C8203157D1AD4 (objectif_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE test_mental (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type_test VARCHAR(100) NOT NULL, niveau VARCHAR(50) NOT NULL, duree INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, date_naissance DATE NOT NULL, pays VARCHAR(100) DEFAULT NULL, avatar VARCHAR(500) DEFAULT NULL, role VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, score_initial INT DEFAULT NULL, date_evaluation_initiale DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E3C105691 FOREIGN KEY (coach_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE `like` ADD CONSTRAINT FK_AC6340B34B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE `like` ADD CONSTRAINT FK_AC6340B3FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE objectif_bien_etre ADD CONSTRAINT FK_319ACD66FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE participation_evenement ADD CONSTRAINT FK_65A14675FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
        $this->addSql('ALTER TABLE participation_evenement ADD CONSTRAINT FK_65A14675FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E562B3943 FOREIGN KEY (test_mental_id) REFERENCES test_mental (id)');
        $this->addSql('ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22D562B3943 FOREIGN KEY (test_mental_id) REFERENCES test_mental (id)');
        $this->addSql('ALTER TABLE suivi_quotidien ADD CONSTRAINT FK_B46C8203157D1AD4 FOREIGN KEY (objectif_id) REFERENCES objectif_bien_etre (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC4B89032C');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCFB88E14F');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E3C105691');
        $this->addSql('ALTER TABLE `like` DROP FOREIGN KEY FK_AC6340B34B89032C');
        $this->addSql('ALTER TABLE `like` DROP FOREIGN KEY FK_AC6340B3FB88E14F');
        $this->addSql('ALTER TABLE objectif_bien_etre DROP FOREIGN KEY FK_319ACD66FB88E14F');
        $this->addSql('ALTER TABLE participation_evenement DROP FOREIGN KEY FK_65A14675FD02F13');
        $this->addSql('ALTER TABLE participation_evenement DROP FOREIGN KEY FK_65A14675FB88E14F');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DFB88E14F');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E562B3943');
        $this->addSql('ALTER TABLE resultat_test DROP FOREIGN KEY FK_7ECAF22DFB88E14F');
        $this->addSql('ALTER TABLE resultat_test DROP FOREIGN KEY FK_7ECAF22D562B3943');
        $this->addSql('ALTER TABLE suivi_quotidien DROP FOREIGN KEY FK_B46C8203157D1AD4');
        $this->addSql('DROP TABLE commentaire');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE `like`');
        $this->addSql('DROP TABLE objectif_bien_etre');
        $this->addSql('DROP TABLE participation_evenement');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE resultat_test');
        $this->addSql('DROP TABLE suivi_quotidien');
        $this->addSql('DROP TABLE test_mental');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
