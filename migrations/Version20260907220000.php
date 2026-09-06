<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted locale and theme preferences to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD locale VARCHAR(10) NOT NULL DEFAULT 'en', ADD theme VARCHAR(10) NOT NULL DEFAULT 'light'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP locale, DROP theme');
    }
}