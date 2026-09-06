<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903212000 extends AbstractMigration
{
    public function getDescription(): string { return 'Remove obsolete CV search vector default'; }

    public function up(Schema $schema): void { $this->addSql("ALTER TABLE cv ALTER search_vector DROP DEFAULT"); }
    public function down(Schema $schema): void { $this->addSql("ALTER TABLE cv ALTER search_vector SET DEFAULT ''::tsvector"); }
}