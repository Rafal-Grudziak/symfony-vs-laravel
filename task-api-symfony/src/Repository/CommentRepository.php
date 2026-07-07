<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @param list<string> $with
     */
    public function createListingQueryBuilder(array $with, ?int $taskId): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.id', 'DESC');

        if ($taskId !== null) {
            $qb->andWhere('IDENTITY(c.task) = :tid')->setParameter('tid', $taskId);
        }

        if (in_array('task', $with, true)) {
            $qb->leftJoin('c.task', 't')->addSelect('t');
        }

        return $qb;
    }
}
