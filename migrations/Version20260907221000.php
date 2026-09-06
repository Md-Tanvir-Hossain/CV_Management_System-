<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align preference columns with entity defaults';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ALTER locale DROP DEFAULT');
        $this->addSql('ALTER TABLE app_user ALTER theme DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ALTER locale SET DEFAULT 'en'");
        $this->addSql("ALTER TABLE app_user ALTER theme SET DEFAULT 'light'");
    }
}
