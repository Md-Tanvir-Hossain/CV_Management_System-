<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Position index names and timestamp metadata with Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN \"position\".created_at IS ''");
        $this->addSql("COMMENT ON COLUMN \"position\".updated_at IS ''");
        $this->addSql('ALTER INDEX idx_position_rule_position RENAME TO IDX_5D97A8C6DD842E46');
        $this->addSql('ALTER INDEX idx_position_rule_attribute RENAME TO IDX_5D97A8C6B6E62EFA');
        $this->addSql('ALTER INDEX idx_position_attribute_position RENAME TO IDX_AF5BEE86DD842E46');
        $this->addSql('ALTER INDEX idx_position_attribute_attribute RENAME TO IDX_AF5BEE86B6E62EFA');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN \"position\".created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN \"position\".updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER INDEX IDX_5D97A8C6DD842E46 RENAME TO idx_position_rule_position');
        $this->addSql('ALTER INDEX IDX_5D97A8C6B6E62EFA RENAME TO idx_position_rule_attribute');
        $this->addSql('ALTER INDEX IDX_AF5BEE86DD842E46 RENAME TO idx_position_attribute_position');
        $this->addSql('ALTER INDEX IDX_AF5BEE86B6E62EFA RENAME TO idx_position_attribute_attribute');
    }
}