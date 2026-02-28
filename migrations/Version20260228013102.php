<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228013102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profil_psychologique (id INT AUTO_INCREMENT NOT NULL, score_global INT NOT NULL, profil_type VARCHAR(100) NOT NULL, date_evaluation DATETIME NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_C1A2049EFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE profil_psychologique ADD CONSTRAINT FK_C1A2049EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT NULL, CHANGE lien_session lien_session VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT NULL, CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL, CHANGE emotion emotion VARCHAR(50) DEFAULT NULL, CHANGE risk_level risk_level VARCHAR(20) DEFAULT \'low\' NOT NULL');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_f4b24a2ee92f8f78 TO IDX_14690B19E92F8F78');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_f4b24a2e10daf24a TO IDX_14690B1910DAF24A');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_f4b24a2e4b89032c TO IDX_14690B194B89032C');
        $this->addSql('ALTER TABLE reactivation_request CHANGE processed_at processed_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_saved_post_user_post ON saved_post');
        $this->addSql('ALTER TABLE saved_post RENAME INDEX idx_b0141a2dfb88e14f TO IDX_54B59E98FB88E14F');
        $this->addSql('ALTER TABLE saved_post RENAME INDEX idx_b0141a2d4b89032c TO IDX_54B59E984B89032C');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE avatar avatar VARCHAR(500) DEFAULT NULL, CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE reset_token reset_token VARCHAR(100) DEFAULT NULL, CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT NULL, CHANGE deactivated_at deactivated_at DATETIME DEFAULT NULL, CHANGE deactivated_by deactivated_by VARCHAR(50) DEFAULT NULL, CHANGE reactivation_token reactivation_token VARCHAR(100) DEFAULT NULL, CHANGE reactivation_token_expires_at reactivation_token_expires_at DATETIME DEFAULT NULL, CHANGE last_login_at last_login_at DATETIME DEFAULT NULL, CHANGE face_registered_at face_registered_at DATETIME DEFAULT NULL, CHANGE last_face_auth_attempt_at last_face_auth_attempt_at DATETIME DEFAULT NULL, CHANGE totp_secret totp_secret VARCHAR(255) DEFAULT NULL, CHANGE two_factor_enabled_at two_factor_enabled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profil_psychologique DROP FOREIGN KEY FK_C1A2049EFB88E14F');
        $this->addSql('DROP TABLE profil_psychologique');
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT \'NULL\', CHANGE lien_session lien_session VARCHAR(500) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT \'NULL\', CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\', CHANGE emotion emotion VARCHAR(50) DEFAULT \'NULL\', CHANGE risk_level risk_level VARCHAR(20) DEFAULT \'\'\'low\'\'\' NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_14690b19e92f8f78 TO IDX_F4B24A2EE92F8F78');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_14690b1910daf24a TO IDX_F4B24A2E10DAF24A');
        $this->addSql('ALTER TABLE post_notification RENAME INDEX idx_14690b194b89032c TO IDX_F4B24A2E4B89032C');
        $this->addSql('ALTER TABLE reactivation_request CHANGE processed_at processed_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_saved_post_user_post ON saved_post (utilisateur_id, post_id)');
        $this->addSql('ALTER TABLE saved_post RENAME INDEX idx_54b59e98fb88e14f TO IDX_B0141A2DFB88E14F');
        $this->addSql('ALTER TABLE saved_post RENAME INDEX idx_54b59e984b89032c TO IDX_B0141A2D4B89032C');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE avatar avatar VARCHAR(500) DEFAULT \'NULL\', CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE reset_token reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE deactivated_at deactivated_at DATETIME DEFAULT \'NULL\', CHANGE deactivated_by deactivated_by VARCHAR(50) DEFAULT \'NULL\', CHANGE reactivation_token reactivation_token VARCHAR(100) DEFAULT \'NULL\', CHANGE reactivation_token_expires_at reactivation_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE last_login_at last_login_at DATETIME DEFAULT \'NULL\', CHANGE face_registered_at face_registered_at DATETIME DEFAULT \'NULL\', CHANGE last_face_auth_attempt_at last_face_auth_attempt_at DATETIME DEFAULT \'NULL\', CHANGE totp_secret totp_secret VARCHAR(255) DEFAULT \'NULL\', CHANGE two_factor_enabled_at two_factor_enabled_at DATETIME DEFAULT \'NULL\'');
    }
}
