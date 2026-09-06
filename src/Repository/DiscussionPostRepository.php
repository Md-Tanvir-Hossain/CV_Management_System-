<?php

namespace App\Repository;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DiscussionPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionPost::class);
    }

    /** @return list<DiscussionPost> */
    public function findForPosition(Position $position, ?int $afterId = null): array
    {
        $query = $this->createQueryBuilder('post')
            ->join('post.author', 'author')->addSelect('author')
            ->where('post.position = :position')->setParameter('position', $position)
            ->orderBy('post.id', 'ASC');
        if ($afterId !== null) {
            $query->andWhere('post.id > :afterId')->setParameter('afterId', $afterId);
        }
        return $query->getQuery()->getResult();
    }
}