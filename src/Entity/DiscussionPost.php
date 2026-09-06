<?php

namespace App\Entity;

use App\Repository\DiscussionPostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscussionPostRepository::class)]
#[ORM\Table(name: 'discussion_post')]
class DiscussionPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Position $position, User $author, string $content)
    {
        $this->position = $position;
        $this->author = $author;
        $this->content = trim($content);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPosition(): Position { return $this->position; }
    public function getAuthor(): User { return $this->author; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}