<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return list<Project> */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('project')
            ->where('project.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('project.periodEnd', 'DESC')
            ->addOrderBy('project.name', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<string> */
    public function findDistinctTags(): array
    {
        $tags = [];
        foreach ($this->createQueryBuilder('project')->select('project.tags')->getQuery()->getSingleColumnResult() as $projectTags) {
            foreach ((array) $projectTags as $tag) {
                $tags[$tag] = $tag;
            }
        }

        sort($tags);
        return array_values($tags);
    }
}