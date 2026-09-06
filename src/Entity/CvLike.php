<?php

namespace App\Entity;

use App\Repository\CvLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CvLikeRepository::class)]
#[ORM\Table(name: 'cv_like')]
#[ORM\UniqueConstraint(name: 'uniq_cv_like', columns: ['cv_id', 'recruiter_id'])]
class CvLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CV::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CV $cv;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recruiter;

    public function __construct(CV $cv, User $recruiter)
    {
        $this->cv = $cv;
        $this->recruiter = $recruiter;
    }

    public function getCv(): CV { return $this->cv; }
    public function getRecruiter(): User { return $this->recruiter; }
}