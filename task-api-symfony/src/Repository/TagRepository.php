<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @param list<string> $with
     */
    public function createListingQueryBuilder(array $with): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')->orderBy('g.name', 'ASC');

        if (in_array('tasks', $with, true)) {
            $qb->leftJoin('g.taskTags', 'tt')->addSelect('tt');
            $qb->leftJoin('tt.task', 't')->addSelect('t');
        }

        return $qb;
    }

    public function existsOtherWithName(string $name, ?int $ignoreId): bool
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.name = :n')
            ->setParameter('n', $name);

        if ($ignoreId !== null) {
            $qb->andWhere('g.id != :id')->setParameter('id', $ignoreId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
