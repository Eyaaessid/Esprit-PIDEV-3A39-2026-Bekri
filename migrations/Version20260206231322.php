<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206231322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_template DROP FOREIGN KEY `FK_F468AF8F5DA0FB8`');
        $this->addSql('DROP INDEX IDX_F468AF8F5DA0FB8 ON question_template');
        $this->addSql('ALTER TABLE question_template ADD type VARCHAR(50) NOT NULL, ADD options LONGTEXT DEFAULT NULL, CHANGE template_id suivi_template_id INT NOT NULL');
        $this->addSql('ALTER TABLE question_template ADD CONSTRAINT FK_F468AF8FD4CB0BA5 FOREIGN KEY (suivi_template_id) REFERENCES suivi_template (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F468AF8FD4CB0BA5 ON question_template (suivi_template_id)');
        $this->addSql('ALTER TABLE suivi_template DROP created_at, DROP updated_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_template DROP FOREIGN KEY FK_F468AF8FD4CB0BA5');
        $this->addSql('DROP INDEX IDX_F468AF8FD4CB0BA5 ON question_template');
        $this->addSql('ALTER TABLE question_template DROP type, DROP options, CHANGE suivi_template_id template_id INT NOT NULL');
        $this->addSql('ALTER TABLE question_template ADD CONSTRAINT `FK_F468AF8F5DA0FB8` FOREIGN KEY (template_id) REFERENCES suivi_template (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_F468AF8F5DA0FB8 ON question_template (template_id)');
        $this->addSql('ALTER TABLE suivi_template ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL');
    }
}
