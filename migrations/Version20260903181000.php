<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align CV foreign-key index names with Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_cv_candidate RENAME TO IDX_B66FFE9291BD8781');
        $this->addSql('ALTER INDEX idx_cv_position RENAME TO IDX_B66FFE92DD842E46');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_B66FFE9291BD8781 RENAME TO idx_cv_candidate');
        $this->addSql('ALTER INDEX IDX_B66FFE92DD842E46 RENAME TO idx_cv_position');
    }
}