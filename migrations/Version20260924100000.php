<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification state to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD is_verified BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP is_verified');
    }
}