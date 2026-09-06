<?php

namespace App\Repository;

use App\Entity\CV;
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
}