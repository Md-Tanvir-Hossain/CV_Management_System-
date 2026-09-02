<?php

namespace App\Repository;

use App\Entity\PositionAttribute;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PositionAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionAttribute::class);
    }

    /** @return list<PositionAttribute> */
    public function findForPosition(Position $position): array
    {
        return $this->createQueryBuilder('positionAttribute')
            ->join('positionAttribute.attribute', 'attribute')->addSelect('attribute')
            ->where('positionAttribute.position = :position')->setParameter('position', $position)
            ->orderBy('attribute.name', 'ASC')->getQuery()->getResult();
    }
}