<?php

namespace App\Entity;

use App\Repository\PositionAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionAttributeRepository::class)]
#[ORM\Table(name: 'position_attribute')]
#[ORM\UniqueConstraint(name: 'uniq_position_attribute', columns: ['position_id', 'attribute_id'])]
class PositionAttribute
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

    public function __construct(Position $position, Attribute $attribute)
    {
        $this->position = $position;
        $this->attribute = $attribute;
    }

    public function getId(): ?int { return $this->id; }
    public function getPosition(): Position { return $this->position; }
    public function getAttribute(): Attribute { return $this->attribute; }
}