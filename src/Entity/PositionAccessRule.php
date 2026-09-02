<?php

namespace App\Entity;

use App\Enum\AccessRuleOperator;
use App\Repository\PositionAccessRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionAccessRuleRepository::class)]
#[ORM\Table(name: 'position_access_rule')]
class PositionAccessRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Attribute $attribute;

    #[ORM\Column(type: Types::STRING, enumType: AccessRuleOperator::class, length: 20)]
    private AccessRuleOperator $operator = AccessRuleOperator::Equals;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comparisonValue = null;

    public function __construct(Position $position, Attribute $attribute, AccessRuleOperator $operator = AccessRuleOperator::Equals, ?string $comparisonValue = null)
    {
        $this->position = $position;
        $this->attribute = $attribute;
        $this->operator = $operator;
        $this->comparisonValue = $comparisonValue;
    }

    public function getId(): ?int { return $this->id; }
    public function getPosition(): Position { return $this->position; }
    public function getAttribute(): Attribute { return $this->attribute; }
    public function getOperator(): AccessRuleOperator { return $this->operator; }
    public function setOperator(AccessRuleOperator $operator): static { $this->operator = $operator; return $this; }
    public function getComparisonValue(): ?string { return $this->comparisonValue; }
    public function setComparisonValue(?string $value): static { $this->comparisonValue = $value; return $this; }
}