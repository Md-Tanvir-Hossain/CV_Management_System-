<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903211000 extends AbstractMigration
{
    public function getDescription(): string { return 'Align M8 search index names with Doctrine'; }

    public function up(Schema $schema): void {}

    public function down(Schema $schema): void {}
}