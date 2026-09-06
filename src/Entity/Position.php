<?php

namespace App\Entity;

use App\Enum\PositionLevel;
use App\Repository\PositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'position')]
#[ORM\Index(name: 'IDX_POSITION_SEARCH', columns: ['search_vector'], options: ['using' => 'gin'])]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $shortDescription = '';

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(type: Types::STRING, enumType: PositionLevel::class, length: 20, nullable: true)]
    private ?PositionLevel $level = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    #[ORM\Column(type: Types::JSON)]
    private array $projectTags = [];

    #[ORM\Column]
    private int $maxProjects = 3;

    #[ORM\Column(type: 'tsvector', nullable: true, insertable: false, updatable: false, columnDefinition: 'TSVECTOR')]
    private ?string $searchVector = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getShortDescription(): string { return $this->shortDescription; }
    public function setShortDescription(string $description): static { $this->shortDescription = trim($description); return $this; }
    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $isPublic): static { $this->isPublic = $isPublic; return $this; }
    public function getCompany(): ?string { return $this->company; }
    public function setCompany(?string $company): static { $this->company = $company === null ? null : trim($company); return $this; }
    public function getLevel(): ?PositionLevel { return $this->level; }
    public function setLevel(?PositionLevel $level): static { $this->level = $level; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getVersion(): int { return $this->version; }
    public function getProjectTags(): array { return $this->projectTags; }
    public function setProjectTags(array $tags): static { $this->projectTags = array_values(array_unique(array_filter(array_map('trim', $tags)))); return $this; }
    public function getMaxProjects(): int { return $this->maxProjects; }
    public function setMaxProjects(int $maxProjects): static { $this->maxProjects = max(0, $maxProjects); return $this; }
}