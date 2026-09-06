<?php

namespace App\Repository;

use App\Entity\Position;
use App\Entity\CV;
use App\Enum\CvStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /** @return list<Position> */
    public function findForLibrary(?string $query = null): array
    {
        $builder = $this->createQueryBuilder('position')
            ->orderBy('position.updatedAt', 'DESC')
            ->addOrderBy('position.title', 'ASC');
        if ($query !== null && trim($query) !== '') {
            $builder->andWhere('LOWER(position.title) LIKE :query OR LOWER(position.company) LIKE :query')
                ->setParameter('query', '%'.strtolower(trim($query)).'%');
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<Position> */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('position')->orderBy('position.updatedAt', 'DESC')->addOrderBy('position.title', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return list<Position> */
    public function findMostPopular(int $limit = 5): array
    {
        return $this->createQueryBuilder('position')
            ->leftJoin(CV::class, 'cv', 'WITH', 'cv.position = position AND cv.status = :published')->setParameter('published', CvStatus::Published)
            ->groupBy('position.id')->orderBy('COUNT(cv.id)', 'DESC')->addOrderBy('position.title', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }
}