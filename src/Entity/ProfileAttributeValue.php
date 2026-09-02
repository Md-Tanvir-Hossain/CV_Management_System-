<?php

namespace App\Entity;

use App\Repository\ProfileAttributeValueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileAttributeValueRepository::class)]
#[ORM\Table(name: 'profile_attribute_value')]
#[ORM\UniqueConstraint(name: 'uniq_profile_attribute', columns: ['user_id', 'attribute_id'])]
class ProfileAttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Attribute $attribute;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    public function __construct(User $user, Attribute $attribute, ?string $value = null)
    {
        $this->user = $user;
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getAttribute(): Attribute { return $this->attribute; }
    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): static { $this->value = $value === null ? null : trim($value); return $this; }
    public function getVersion(): int { return $this->version; }
}