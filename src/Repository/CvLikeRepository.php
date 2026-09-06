<?php

namespace App\Repository;

use App\Entity\CV;
use App\Entity\CvLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CvLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvLike::class);
    }

    public function countForCv(CV $cv): int
    {
        return (int) $this->createQueryBuilder('cvLike')->select('COUNT(cvLike.id)')->where('cvLike.cv = :cv')->setParameter('cv', $cv)->getQuery()->getSingleScalarResult();
    }

    public function isLikedBy(CV $cv, User $recruiter): bool
    {
        return $this->findOneBy(['cv' => $cv, 'recruiter' => $recruiter]) instanceof CvLike;
    }

    /** @param list<CV> $cvs @return array<int, int> */
    public function countsForCvs(array $cvs): array
    {
        if ($cvs === []) {
            return [];
        }
        $rows = $this->createQueryBuilder('cvLike')
            ->select('IDENTITY(cvLike.cv) AS cvId, COUNT(cvLike.id) AS total')
            ->where('cvLike.cv IN (:cvs)')->setParameter('cvs', $cvs)
            ->groupBy('cvLike.cv')->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['cvId']] = (int) $row['total'];
        }
        return $counts;
    }
}