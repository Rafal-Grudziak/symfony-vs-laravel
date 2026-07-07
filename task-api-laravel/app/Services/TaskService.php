<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(
        int $perPage,
        array $with,
        ?int $projectId,
        ?string $status,
        ?string $priority,
    ): LengthAwarePaginator {
        return Task::query()
            ->with($with)
            ->forProject($projectId)
            ->status($status)
            ->priority($priority)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $with
     */
    public function find(int $id, array $with): Task
    {
        return Task::query()
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        $tagIds = Arr::pull($data, 'tag_ids', []);

        return DB::transaction(function () use ($data, $tagIds): Task {
            $task = Task::query()->create($data);
            if ($tagIds !== []) {
                $task->tags()->sync($tagIds);
            }

            return $task->load('tags');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $tagIds = Arr::pull($data, 'tag_ids', null);

        return DB::transaction(function () use ($task, $data, $tagIds): Task {
            if (is_array($tagIds)) {
                $task->tags()->sync($tagIds);
            }
            if ($data !== []) {
                $task->fill($data);
                $task->save();
            }

            return $task->refresh()->load('tags');
        });
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
