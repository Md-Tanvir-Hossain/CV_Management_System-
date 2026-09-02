<?php

namespace App\Repository;

use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ProfileAttributeValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileAttributeValue::class);
    }

    /** @return list<ProfileAttributeValue> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('profileValue')
            ->join('profileValue.attribute', 'attribute')
            ->addSelect('attribute')
            ->where('profileValue.user = :user')
            ->setParameter('user', $user)
            ->orderBy('attribute.isBuiltin', 'DESC')
            ->addOrderBy('attribute.name', 'ASC')
            ->getQuery()->getResult();
    }
}