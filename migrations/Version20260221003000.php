<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create saved_post table for bookmarked posts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saved_post (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, post_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_B0141A2DFB88E14F (utilisateur_id), INDEX IDX_B0141A2D4B89032C (post_id), UNIQUE INDEX uniq_saved_post_user_post (utilisateur_id, post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE saved_post ADD CONSTRAINT FK_B0141A2DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE saved_post ADD CONSTRAINT FK_B0141A2D4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE saved_post');
    }
}
