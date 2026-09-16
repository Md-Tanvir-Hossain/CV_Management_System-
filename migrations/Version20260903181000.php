<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align CV foreign-key index names with Doctrine (safe no-op if already correctly named)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'idx_cv_candidate') THEN
                    ALTER INDEX idx_cv_candidate RENAME TO "IDX_B66FFE9291BD8781";
                END IF;
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'idx_cv_position') THEN
                    ALTER INDEX idx_cv_position RENAME TO "IDX_B66FFE92DD842E46";
                END IF;
            END $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'IDX_B66FFE9291BD8781') THEN
                    ALTER INDEX "IDX_B66FFE9291BD8781" RENAME TO idx_cv_candidate;
                END IF;
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'IDX_B66FFE92DD842E46') THEN
                    ALTER INDEX "IDX_B66FFE92DD842E46" RENAME TO idx_cv_position;
                END IF;
            END $$;
        SQL);
    }
}