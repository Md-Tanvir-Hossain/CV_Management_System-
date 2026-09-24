<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional attribute value tuning metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE attribute ADD tuning JSON NOT NULL DEFAULT '{}'::json");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute DROP tuning');
    }
}