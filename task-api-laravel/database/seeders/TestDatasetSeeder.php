<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Services\BenchmarkDataService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Creating 100 users...');
        User::factory(100)->create();

        $userIds = User::query()->pluck('id');

        $this->command?->info('Creating 50 projects...');
        for ($i = 0; $i < 50; $i++) {
            Project::factory()->create([
                'user_id' => $userIds->random(),
            ]);
        }

        $this->command?->info('Creating 100 tags...');
        Tag::factory(100)->create();

        $projectIds = Project::query()->pluck('id');
        $benchmark = app(BenchmarkDataService::class);

        $this->command?->info('Bulk inserting 10,000 tasks (200 per project)...');
        foreach ($projectIds as $projectId) {
            $benchmark->bulkInsertTasks((int) $projectId, 200);
        }

        $tmin = (int) DB::table('tasks')->min('id');
        $tmax = (int) DB::table('tasks')->max('id');

        $this->command?->info('Bulk inserting 50,000 comments...');
        $now = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < 50_000; $i++) {
            $rows[] = [
                'task_id' => random_int($tmin, $tmax),
                'content' => fake()->realText(rand(60, 280)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) >= 500) {
                DB::table('comments')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('comments')->insert($rows);
        }

        $tagIds = Tag::query()->pluck('id')->all();
        $this->command?->info('Attaching random tags to tasks (pivot rows)...');
        $pivotRows = [];
        for ($tid = $tmin; $tid <= $tmax; $tid++) {
            $pickCount = random_int(1, min(4, count($tagIds)));
            $picked = collect($tagIds)->shuffle()->take($pickCount)->all();
            foreach ($picked as $tagId) {
                $pivotRows[] = [
                    'task_id' => $tid,
                    'tag_id' => $tagId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (count($pivotRows) >= 1000) {
                DB::table('task_tag')->insertOrIgnore($pivotRows);
                $pivotRows = [];
            }
        }
        if ($pivotRows !== []) {
            DB::table('task_tag')->insertOrIgnore($pivotRows);
        }

        $this->command?->info('Test dataset seeding complete.');
    }
}
