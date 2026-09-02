<?php

namespace App\Entity;

use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Repository\AttributeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributeRepository::class)]
#[ORM\Table(name: 'attribute')]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, enumType: AttributeCategory::class, length: 30)]
    private AttributeCategory $category = AttributeCategory::Other;

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::STRING, enumType: AttributeType::class, length: 20)]
    private AttributeType $type = AttributeType::String;

    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column]
    private bool $isBuiltin = false;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getCategory(): AttributeCategory { return $this->category; }
    public function setCategory(AttributeCategory $category): static { $this->category = $category; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = trim($description); return $this; }
    public function getType(): AttributeType { return $this->type; }
    public function setType(AttributeType $type): static { $this->type = $type; return $this; }
    public function getOptions(): array { return $this->options; }
    public function setOptions(array $options): static { $this->options = array_values($options); return $this; }
    public function isBuiltin(): bool { return $this->isBuiltin; }
    public function setIsBuiltin(bool $isBuiltin): static { $this->isBuiltin = $isBuiltin; return $this; }
    public function getVersion(): int { return $this->version; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function markUsed(): static { $this->lastUsedAt = new \DateTimeImmutable(); return $this; }
}