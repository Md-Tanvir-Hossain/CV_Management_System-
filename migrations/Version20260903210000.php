<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add PostgreSQL full-text search vectors'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE position ADD search_vector TSVECTOR GENERATED ALWAYS AS (to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(company, '') || ' ' || coalesce(short_description, ''))) STORED");
        $this->addSql('CREATE INDEX IDX_POSITION_SEARCH ON position USING GIN (search_vector)');
        $this->addSql("ALTER TABLE cv ADD search_vector TSVECTOR NOT NULL DEFAULT ''::tsvector");
        $this->addSql('CREATE INDEX IDX_CV_SEARCH ON cv USING GIN (search_vector)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_POSITION_SEARCH');
        $this->addSql('DROP INDEX IDX_CV_SEARCH');
        $this->addSql('ALTER TABLE position DROP COLUMN search_vector');
        $this->addSql('ALTER TABLE cv DROP COLUMN search_vector');
    }
}