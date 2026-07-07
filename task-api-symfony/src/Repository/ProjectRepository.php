<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @param list<string> $with
     */
    public function createListingQueryBuilder(array $with, ?string $status, ?int $userId): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.id', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(p.user) = :uid')->setParameter('uid', $userId);
        }

        if (in_array('user', $with, true)) {
            $qb->leftJoin('p.user', 'u')->addSelect('u');
        }

        if (in_array('tasks', $with, true)) {
            $qb->leftJoin('p.tasks', 't')->addSelect('t');
        }

        return $qb;
    }
}
