<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create post_notification table for in-app likes/comments notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post_notification (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, actor_id INT NOT NULL, post_id INT NOT NULL, type VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, INDEX IDX_F4B24A2EE92F8F78 (recipient_id), INDEX IDX_F4B24A2E10DAF24A (actor_id), INDEX IDX_F4B24A2E4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_notification ADD CONSTRAINT FK_F4B24A2EE92F8F78 FOREIGN KEY (recipient_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_notification ADD CONSTRAINT FK_F4B24A2E10DAF24A FOREIGN KEY (actor_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_notification ADD CONSTRAINT FK_F4B24A2E4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post_notification');
    }
}
