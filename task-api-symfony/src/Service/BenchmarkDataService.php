<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class BenchmarkDataService
{
    private const CHUNK = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function bulkInsertTasks(int $projectId, int $count): int
    {
        $conn = $this->em->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rows = [];
        $inserted = 0;

        for ($i = 0; $i < $count; ++$i) {
            $rows[] = [
                'project_id' => $projectId,
                'title' => 'Benchmark task '.$i,
                'description' => null,
                'status' => Task::STATUS_TODO,
                'priority' => Task::PRIORITY_MEDIUM,
                'due_date' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= self::CHUNK) {
                $this->insertTasksChunk($conn, $rows);
                $inserted += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertTasksChunk($conn, $rows);
            $inserted += count($rows);
        }

        return $inserted;
    }

    public function bulkInsertComments(int $taskId, int $count): int
    {
        $conn = $this->em->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rows = [];
        $inserted = 0;

        for ($i = 0; $i < $count; ++$i) {
            $rows[] = [
                'task_id' => $taskId,
                'content' => 'Benchmark comment '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= self::CHUNK) {
                $this->insertCommentsChunk($conn, $rows);
                $inserted += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertCommentsChunk($conn, $rows);
            $inserted += count($rows);
        }

        return $inserted;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertTasksChunk(Connection $conn, array $rows): void
    {
        $this->multiInsert(
            $conn,
            'tasks',
            ['project_id', 'title', 'description', 'status', 'priority', 'due_date', 'created_at', 'updated_at'],
            $rows,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertCommentsChunk(Connection $conn, array $rows): void
    {
        $this->multiInsert(
            $conn,
            'comments',
            ['task_id', 'content', 'created_at', 'updated_at'],
            $rows,
        );
    }

    /**
     * @param list<string>              $columns
     * @param list<array<string, mixed>> $rows
     */
    private function multiInsert(Connection $conn, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $quotedTable = $conn->quoteIdentifier($table);
        $quotedCols = array_map($conn->quoteIdentifier(...), $columns);
        $rowPlaceholder = '('.implode(',', array_fill(0, count($columns), '?')).')';
        $valuesSql = implode(',', array_fill(0, count($rows), $rowPlaceholder));
        $sql = 'INSERT INTO '.$quotedTable.' ('.implode(',', $quotedCols).') VALUES '.$valuesSql;

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $c) {
                $params[] = $row[$c];
            }
        }

        $conn->executeStatement($sql, $params);
    }
}
