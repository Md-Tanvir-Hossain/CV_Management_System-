<?php

namespace App\Repository;

use App\Entity\Position;
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
}