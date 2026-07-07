<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @param list<string> $with
     */
    public function createListingQueryBuilder(array $with, ?int $projectId, ?string $status, ?string $priority): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')->orderBy('t.id', 'DESC');

        if ($projectId !== null) {
            $qb->andWhere('IDENTITY(t.project) = :pid')->setParameter('pid', $projectId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        if ($priority !== null && $priority !== '') {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }

        if (in_array('project', $with, true)) {
            $qb->leftJoin('t.project', 'p')->addSelect('p');
        }

        if (in_array('comments', $with, true)) {
            $qb->leftJoin('t.comments', 'c')->addSelect('c');
        }

        if (in_array('tags', $with, true)) {
            $qb->leftJoin('t.taskTags', 'tt')->addSelect('tt');
            $qb->leftJoin('tt.tag', 'tg')->addSelect('tg');
        }

        return $qb;
    }

    /**
     * @param list<string> $with
     */
    public function createShowQueryBuilder(int $id, array $with): QueryBuilder
    {
        return $this->createListingQueryBuilder($with, null, null, null)
            ->andWhere('t.id = :show_task_id')
            ->setParameter('show_task_id', $id);
    }
}
