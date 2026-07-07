<?php

declare(strict_types=1);

namespace App\Service;

use App\Api\ResourceSerializer;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final class ReportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceSerializer $serializer,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, user_id: int, status: string, tasks_count: int}>
     */
    public function tasksPerProject(): array
    {
        $sql = <<<'SQL'
SELECT p.id, p.name, p.user_id, p.status, COUNT(t.id) AS tasks_count
FROM projects p
LEFT JOIN tasks t ON t.project_id = p.id
GROUP BY p.id, p.name, p.user_id, p.status
ORDER BY tasks_count DESC
SQL;

        $rows = $this->em->getConnection()->fetchAllAssociative($sql);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'user_id' => (int) $row['user_id'],
                'status' => (string) $row['status'],
                'tasks_count' => (int) $row['tasks_count'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{project_id: int, name: string, tasks_count: int}>
     */
    public function topProjects(int $limit = 10): array
    {
        $sql = <<<'SQL'
SELECT projects.id AS project_id, projects.name, COUNT(tasks.id) AS tasks_count
FROM projects
INNER JOIN tasks ON tasks.project_id = projects.id
GROUP BY projects.id, projects.name
ORDER BY tasks_count DESC
LIMIT :lim
SQL;

        $conn = $this->em->getConnection();
        $rows = $conn->fetchAllAssociative($sql, ['lim' => $limit], ['lim' => \Doctrine\DBAL\ParameterType::INTEGER]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'project_id' => (int) $row['project_id'],
                'name' => (string) $row['name'],
                'tasks_count' => (int) $row['tasks_count'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function complexTaskOverview(int $limit = 50): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Task::class, 't')
            ->leftJoin('t.project', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults($limit);

        /** @var list<Task> $tasks */
        $tasks = $qb->getQuery()->getResult();

        if ($tasks === []) {
            return [];
        }

        $ids = array_map(static fn (Task $t) => (int) $t->getId(), $tasks);

        $commentCounts = $this->countsByTask('comments', 'task_id', $ids);
        $tagCounts = $this->countsByTask('task_tag', 'task_id', $ids);

        $out = [];
        foreach ($tasks as $t) {
            $id = (int) $t->getId();
            $out[] = $this->serializer->taskToArrayWithCounts($t, [
                'comments_count' => $commentCounts[$id] ?? 0,
                'tags_count' => $tagCounts[$id] ?? 0,
            ]);
        }

        return $out;
    }

    /**
     * @param list<int> $taskIds
     *
     * @return array<int, int>
     */
    private function countsByTask(string $table, string $column, array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $conn = $this->em->getConnection();
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "SELECT {$column}, COUNT(*) AS c FROM {$table} WHERE {$column} IN ({$placeholders}) GROUP BY {$column}";
        $rows = $conn->fetchAllAssociative($sql, $taskIds);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row[$column]] = (int) $row['c'];
        }

        return $map;
    }
}
