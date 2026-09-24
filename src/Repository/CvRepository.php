<?php

namespace App\Repository;

use App\Entity\CV;
use App\Entity\Position;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CV::class);
    }

    /** @return list<CV> */
    public function findForCandidate(User $candidate): array
    {
        return $this->createQueryBuilder('cv')
            ->join('cv.position', 'position')->addSelect('position')
            ->where('cv.candidate = :candidate')->setParameter('candidate', $candidate)
            ->orderBy('cv.updatedAt', 'DESC')->getQuery()->getResult();
    }

    public function countForCandidate(User $candidate): int
    {
        return (int) $this->createQueryBuilder('cv')
            ->select('COUNT(cv.id)')
            ->where('cv.candidate = :candidate')
            ->setParameter('candidate', $candidate)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<CV> */
    public function findPublishedForPosition(Position $position): array
    {
        return $this->createQueryBuilder('cv')
            ->join('cv.candidate', 'candidate')->addSelect('candidate')
            ->where('cv.position = :position')->setParameter('position', $position)
            ->andWhere('cv.status = :status')->setParameter('status', 'published')
            ->orderBy('cv.updatedAt', 'DESC')->getQuery()->getResult();
    }

    /** @return list<CV> */
    public function findPublishedForCandidate(User $candidate): array
    {
        return $this->createQueryBuilder('cv')
            ->join('cv.position', 'position')->addSelect('position')
            ->where('cv.candidate = :candidate')->setParameter('candidate', $candidate)
            ->andWhere('cv.status = :status')->setParameter('status', 'published')
            ->orderBy('cv.updatedAt', 'DESC')->getQuery()->getResult();
    }
}