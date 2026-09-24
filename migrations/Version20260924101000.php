<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve access for users created before email verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE app_user SET is_verified = TRUE');
    }

    public function down(Schema $schema): void
    {
    }
}