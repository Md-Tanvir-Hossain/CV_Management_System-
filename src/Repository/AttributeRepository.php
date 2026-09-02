<?php

namespace App\Repository;

use App\Entity\Attribute;
use App\Enum\AttributeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    /** @return list<Attribute> */
    public function findForLibrary(?string $query, ?AttributeCategory $category): array
    {
        $builder = $this->createQueryBuilder('attribute')
            ->orderBy('CASE WHEN attribute.lastUsedAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('attribute.lastUsedAt', 'DESC')
            ->addOrderBy('attribute.name', 'ASC');

        if ($query !== null && $query !== '') {
            $builder->andWhere('LOWER(attribute.name) LIKE :query')
                ->setParameter('query', strtolower($query).'%');
        }

        if ($category !== null) {
            $builder->andWhere('attribute.category = :category')
                ->setParameter('category', $category);
        }

        return $builder->getQuery()->getResult();
    }
}