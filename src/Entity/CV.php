<?php

namespace App\Entity;

use App\Enum\CvStatus;
use App\Repository\CvRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CvRepository::class)]
#[ORM\Table(name: 'cv')]
#[ORM\UniqueConstraint(name: 'uniq_cv_candidate_position', columns: ['candidate_id', 'position_id'])]
#[ORM\Index(name: 'IDX_CV_SEARCH', columns: ['search_vector'], options: ['using' => 'gin'])]
class CV
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $candidate;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\Column(type: Types::STRING, enumType: CvStatus::class, length: 20)]
    private CvStatus $status = CvStatus::Draft;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    #[ORM\Column(type: 'tsvector', nullable: false, columnDefinition: 'TSVECTOR')]
    private string $searchVector = '';

    public function __construct(User $candidate, Position $position)
    {
        $this->candidate = $candidate;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getCandidate(): User { return $this->candidate; }
    public function getPosition(): Position { return $this->position; }
    public function getStatus(): CvStatus { return $this->status; }
    public function publish(): static { $this->status = CvStatus::Published; $this->touch(); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getVersion(): int { return $this->version; }
}