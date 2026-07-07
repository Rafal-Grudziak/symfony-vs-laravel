<?php

namespace App\Services;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BenchmarkDataService
{
    /**
     * Dodaje wiele zadań jednocześnie za pomocą surowego zapytania SQL
     * (bez wykorzystania zdarzeń modelu). Wykorzystywane podczas testów wydajności.
     */
    public function bulkInsertTasks(int $projectId, int $count): int
    {
        $now = CarbonImmutable::now()->toDateTimeString();
        $rows = [];
        $inserted = 0;

        for ($i = 0; $i < $count; $i++) {
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

            if (count($rows) >= 500) {
                DB::table('tasks')->insert($rows);
                $inserted += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('tasks')->insert($rows);
            $inserted += count($rows);
        }

        return $inserted;
    }

    /**
     * Dodaje wiele komentarzy do wskazanego zadania za pomocą
     * surowego zapytania SQL. Wykorzystywane podczas testów wydajności.
     */
    public function bulkInsertComments(int $taskId, int $count): int
    {
        $now = CarbonImmutable::now()->toDateTimeString();
        $rows = [];
        $inserted = 0;

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'task_id' => $taskId,
                'content' => 'Benchmark comment '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('comments')->insert($rows);
                $inserted += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('comments')->insert($rows);
            $inserted += count($rows);
        }

        return $inserted;
    }
}
