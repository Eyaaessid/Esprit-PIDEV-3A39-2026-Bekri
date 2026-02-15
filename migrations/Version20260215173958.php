<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215173958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT NULL, CHANGE lien_session lien_session VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT NULL, CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD deactivated_at DATETIME DEFAULT NULL, ADD deactivated_by VARCHAR(50) DEFAULT NULL, ADD reactivation_token VARCHAR(100) DEFAULT NULL, ADD reactivation_token_expires_at DATETIME DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE avatar avatar VARCHAR(500) DEFAULT NULL, CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE reset_token reset_token VARCHAR(100) DEFAULT NULL, CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT \'NULL\', CHANGE lien_session lien_session VARCHAR(500) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE objectif_bien_etre CHANGE valeur_actuelle valeur_actuelle DOUBLE PRECISION DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE post CHANGE media_url media_url VARCHAR(500) DEFAULT \'NULL\', CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE deleted_at deleted_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE utilisateur DROP deactivated_at, DROP deactivated_by, DROP reactivation_token, DROP reactivation_token_expires_at, DROP last_login_at, CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE avatar avatar VARCHAR(500) DEFAULT \'NULL\', CHANGE date_evaluation_initiale date_evaluation_initiale DATE DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE reset_token reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT \'NULL\'');
    }
}
