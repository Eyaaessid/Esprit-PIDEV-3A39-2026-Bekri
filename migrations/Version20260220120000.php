<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post emotion/risk fields and unique like per post/user constraint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE post ADD emotion VARCHAR(50) DEFAULT NULL, ADD risk_level VARCHAR(20) NOT NULL DEFAULT 'low', ADD is_sensitive TINYINT(1) NOT NULL DEFAULT 0");
        $this->addSql('ALTER TABLE `like` ADD CONSTRAINT uniq_like_post_user UNIQUE (post_id, utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `like` DROP INDEX uniq_like_post_user');
        $this->addSql('ALTER TABLE post DROP emotion, DROP risk_level, DROP is_sensitive');
    }
}
