<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903201000 extends AbstractMigration
{
    public function getDescription(): string { return 'Align discussion and CV-like indexes with Doctrine'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_discussion_position RENAME TO IDX_7FE4C0BBDD842E46');
        $this->addSql('ALTER INDEX idx_discussion_author RENAME TO IDX_7FE4C0BBF675F31B');
        $this->addSql('ALTER INDEX idx_cv_like_cv RENAME TO IDX_CD2DA06FCFE419E2');
        $this->addSql('ALTER INDEX idx_cv_like_recruiter RENAME TO IDX_CD2DA06F156BE243');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_7FE4C0BBDD842E46 RENAME TO idx_discussion_position');
        $this->addSql('ALTER INDEX IDX_7FE4C0BBF675F31B RENAME TO idx_discussion_author');
        $this->addSql('ALTER INDEX IDX_CD2DA06FCFE419E2 RENAME TO idx_cv_like_cv');
        $this->addSql('ALTER INDEX IDX_CD2DA06F156BE243 RENAME TO idx_cv_like_recruiter');
    }
}