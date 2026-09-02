<?php

namespace App\Repository;

use App\Entity\Position;
use App\Entity\PositionAccessRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PositionAccessRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionAccessRule::class);
    }

    /** @return list<PositionAccessRule> */
    public function findForPosition(Position $position): array
    {
        return $this->createQueryBuilder('rule')
            ->join('rule.attribute', 'attribute')->addSelect('attribute')
            ->where('rule.position = :position')->setParameter('position', $position)
            ->orderBy('attribute.name', 'ASC')->getQuery()->getResult();
    }

    /** @param list<Position> $positions @return list<\App\Entity\PositionAccessRule> */
    public function findForPositions(array $positions): array
    {
        if ($positions === []) {
            return [];
        }

        return $this->createQueryBuilder('rule')
            ->join('rule.attribute', 'attribute')->addSelect('attribute')
            ->where('rule.position IN (:positions)')->setParameter('positions', $positions)
            ->getQuery()->getResult();
    }
}